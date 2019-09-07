<?php

switch (post('op')) {
    // Aggiunta articolo
    case 'add':
        //Se non specifico il codice articolo lo imposto uguale all'id della riga
        if (empty(post('codice'))) {
            $codice = $dbo->fetchOne('SELECT (MAX(id)+1) as codice FROM mg_articoli')['codice'];
        } else {
            $codice = post('codice');
        }

        // Inserisco l'articolo e avviso se esiste un altro articolo con stesso codice.
        if ($n = $dbo->fetchNum('SELECT * FROM mg_articoli WHERE codice='.prepare($codice)) > 0) {
            flash()->warning(tr('Attenzione: il codice _CODICE_ è già stato utilizzato _N_ volta', [
                '_CODICE_' => $codice,
                '_N_' => $n,
            ]));
        }

        $dbo->insert('mg_articoli', [
            'codice' => $codice,
            'descrizione' => post('descrizione'),
            'id_categoria' => post('categoria'),
            'id_sottocategoria' => post('subcategoria'),
            'attivo' => 1,
        ]);
        $id_record = $dbo->lastInsertedID();

        if (isAjaxRequest()) {
            echo json_encode(['id' => $id_record, 'text' => post('descrizione')]);
        }

        flash()->info(tr('Aggiunto un nuovo articolo'));

        break;

    // Modifica articolo
    case 'update':
        $componente = post('componente_filename');
        $qta = post('qta', true);

        // Inserisco l'articolo e avviso se esiste un altro articolo con stesso codice.
        if ($n = $dbo->fetchNum('SELECT * FROM mg_articoli WHERE codice='.prepare(post('codice')).' AND id != '.$id_record.'') > 0) {
            flash()->warning(tr('Attenzione: il codice _CODICE_ è già stato utilizzato _N_ volta', [
                '_CODICE_' => post('codice'),
                '_N_' => $n,
            ]));
        }

        $dbo->update('mg_articoli', [
            'codice' => post('codice'),
            'descrizione' => post('descrizione'),
            'um' => post('um'),
            'id_categoria' => post('categoria'),
            'id_sottocategoria' => post('subcategoria'),
            'abilita_serial' => post('abilita_serial'),
            'threshold_qta' => post('threshold_qta'),
            'prezzo_vendita' => post('prezzo_vendita', true),
            'prezzo_acquisto' => post('prezzo_acquisto', true),
            'idconto_vendita' => post('idconto_vendita'),
            'idconto_acquisto' => post('idconto_acquisto'),
            'idiva_vendita' => post('idiva_vendita'),
            'gg_garanzia' => post('gg_garanzia'),
            'servizio' => post('servizio'),
            'volume' => post('volume'),
            'peso_lordo' => post('peso_lordo'),
            'componente_filename' => $componente,
            'attivo' => post('attivo'),
            'note' => post('note'),
        ], ['id' => $id_record]);

        // Leggo la quantità attuale per capire se l'ho modificata
        $old_qta = $record['qta'];
        $movimento = $qta - $old_qta;

        if ($movimento != 0) {
            $descrizione_movimento = post('descrizione_movimento');
            $data_movimento = post('data_movimento', true);

            add_movimento_magazzino($id_record, $movimento, [], $descrizione_movimento, $data_movimento);
        }

        // Salvataggio info componente (campo `contenuto`)
        if (!empty($componente)) {
            $contenuto = \Util\Ini::write(file_get_contents($docroot.'/files/my_impianti/'.$componente), $post);

            $dbo->query('UPDATE mg_articoli SET contenuto='.prepare($contenuto).' WHERE id='.prepare($id_record));
        }

        // Upload file
        if (!empty($_FILES) && !empty($_FILES['immagine']['name'])) {
            $filename = Uploads::upload($_FILES['immagine'], [
                'name' => 'Immagine',
                'id_module' => $id_module,
                'id_record' => $id_record,
            ], [
                'thumbnails' => true,
            ]);

            if (!empty($filename)) {
                $dbo->update('mg_articoli', [
                    'immagine' => $filename,
                ], [
                    'id' => $id_record,
                ]);
            } else {
                flash()->warning(tr('Errore durante il caricamento del file in _DIR_!', [
                    '_DIR_' => $upload_dir,
                ]));
            }
        }

        // Eliminazione file
        if (post('delete_immagine') !== null) {
            Uploads::delete($record['immagine'], [
                'id_module' => $id_module,
                'id_record' => $id_record,
            ]);

            $dbo->update('mg_articoli', [
                'immagine' => null,
            ], [
                'id' => $id_record,
            ]);
        }

        flash()->info(tr('Informazioni salvate correttamente!'));

        break;

    // Duplica articolo
    case 'copy':
        $new = $articolo->replicate();
        $new->qta = 0;
        $new->save();

        flash()->info(tr('Articolo duplicato correttamente!'));

    break;

    // Generazione seriali in sequenza
    case 'generate_serials':
        // Seriali
        $serial_start = post('serial_start');
        $serial_end = post('serial_end');

        preg_match("/(.*?)([\d]*$)/", $serial_start, $m);
        $numero_start = intval($m[2]);
        preg_match("/(.*?)([\d]*$)/", $serial_end, $m);
        $numero_end = intval($m[2]);
        $totale = abs($numero_end - $numero_start) + 1;

        $prefix = rtrim($serial_end, $numero_end);
        $pad_length = strlen($serial_end) - strlen($prefix);

        // Combinazione di seriali
        $serials = [];
        for ($s = 0; $s < $totale; ++$s) {
            $serial = $prefix.(str_pad($numero_start + $s, $pad_length, '0', STR_PAD_LEFT));

            $serials[] = $serial;
        }

        // no break
    case 'add_serials':
        $serials = $serials ?: filter('serials');

        $count = $dbo->attach('mg_prodotti', ['id_articolo' => $id_record, 'dir' => 'uscita'], ['serial' => $serials]);

        // Movimento il magazzino se l'ho specificato nelle impostazioni
        if (setting("Movimenta il magazzino durante l'inserimento o eliminazione dei lotti/serial number")) {
            add_movimento_magazzino($id_record, $count, [], tr('Carico magazzino con serial da _INIZIO_ a _FINE_', [
                '_INIZIO_' => $serial_start,
                '_FINE_' => $serial_end,
            ]));
        }

        flash()->info(tr('Aggiunti _NUM_ seriali!', [
            '_NUM_' => $count,
        ]));

        if ($count != $totale) {
            flash()->warning(tr('Alcuni seriali erano già presenti').'...');
        }

        break;

    case 'delprodotto':
        $idprodotto = post('idprodotto');

        // Leggo info prodotto per descrizione mg_movimenti
        $rs = $dbo->fetchArray('SELECT lotto, serial, altro FROM mg_prodotti WHERE id='.prepare($idprodotto));

        $query = 'DELETE FROM mg_prodotti WHERE id='.prepare($idprodotto);
        if ($dbo->query($query)) {
            // Movimento il magazzino se l'ho specificato nelle impostazioni
            if (setting("Movimenta il magazzino durante l'inserimento o eliminazione dei lotti/serial number")) {
                add_movimento_magazzino($id_record, -1, [], tr('Eliminazione dal magazzino del prodotto con serial _SERIAL_', [
                    '_SERIAL_' => $rs[0]['serial'],
                ]));
            }

            flash()->info(tr('Prodotto rimosso!'));
        }
        break;

    case 'delmovimento':
        $idmovimento = post('idmovimento');

        // Lettura qtà movimento
        $rs = $dbo->fetchArray('SELECT idarticolo, qta FROM mg_movimenti WHERE id='.prepare($idmovimento));
        $qta = $rs[0]['qta'];
        $idarticolo = $rs[0]['idarticolo'];

        // Aggiorno la quantità dell'articolo
        $dbo->query('UPDATE mg_articoli SET qta=qta-'.$qta.' WHERE id='.prepare($idarticolo));

        $query = 'DELETE FROM mg_movimenti WHERE id='.prepare($idmovimento);
        if ($dbo->query($query)) {
            flash()->info(tr('Movimento rimosso!'));
        }
        break;

    case 'delete':
        $articolo->delete();

        flash()->info(tr('Articolo eliminato!'));
        break;
}

// Operazioni aggiuntive per l'immagine
if (filter('op') == 'unlink_file' && filter('filename') == $record['immagine']) {
    $dbo->update('mg_articoli', [
        'immagine' => null,
    ], [
        'id' => $id_record,
    ]);
} elseif (filter('op') == 'link_file' && filter('nome_allegato') == 'Immagine') {
    $dbo->update('mg_articoli', [
        'immagine' => $upload,
    ], [
        'id' => $id_record,
    ]);
}
