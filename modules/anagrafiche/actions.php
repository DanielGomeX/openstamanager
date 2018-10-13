<?php

include_once __DIR__.'/../../core.php';

use Modules\Anagrafiche\Anagrafica;

$id_azienda = $dbo->fetchOne("SELECT id FROM an_tipianagrafiche WHERE descrizione='Azienda'")['id'];
$id_cliente = $dbo->fetchOne("SELECT id FROM an_tipianagrafiche WHERE descrizione='Cliente'")['id'];
$id_fornitore = $dbo->fetchOne("SELECT id FROM an_tipianagrafiche WHERE descrizione='Fornitore'")['id'];
$id_tecnico = $dbo->fetchOne("SELECT id FROM an_tipianagrafiche WHERE descrizione='Tecnico'")['id'];

switch (post('op')) {
    case 'update':
        // Informazioni sull'anagrafica
        $anagrafica->codice = post('codice');
        $anagrafica->ragione_sociale = post('ragione_sociale');
        $anagrafica->tipo = post('tipo');
        $anagrafica->data_nascita = post('data_nascita');
        $anagrafica->luogo_nascita = post('luogo_nascita');
        $anagrafica->sesso = post('sesso');
        $anagrafica->capitale_sociale = post('capitale_sociale');
        $anagrafica->pec = post('pec');
        $anagrafica->idsede_fatturazione = post('idsede_fatturazione');
        $anagrafica->note = post('note');
        $anagrafica->codiceri = post('codiceri');
        $anagrafica->codicerea = post('codicerea');
        $anagrafica->appoggiobancario = post('appoggiobancario');
        $anagrafica->filiale = post('filiale');
        $anagrafica->codiceiban = post('codiceiban');
        $anagrafica->bic = post('bic');
        $anagrafica->diciturafissafattura = post('diciturafissafattura');
        $anagrafica->idpagamento_acquisti = post('idpagamento_acquisti');
        $anagrafica->idpagamento_vendite = post('idpagamento_vendite');
        $anagrafica->idlistino_acquisti = post('idlistino_acquisti');
        $anagrafica->idlistino_vendite = post('idlistino_vendite');
        $anagrafica->idiva_acquisti = post('idiva_acquisti');
        $anagrafica->idiva_vendite = post('idiva_vendite');
        $anagrafica->idbanca_acquisti = post('idbanca_acquisti');
        $anagrafica->idbanca_vendite = post('idbanca_vendite');
        $anagrafica->settore = post('settore');
        $anagrafica->marche = post('marche');
        $anagrafica->dipendenti = post('dipendenti');
        $anagrafica->macchine = post('macchine');
        $anagrafica->idagente = post('idagente');
        $anagrafica->idrelazione = post('idrelazione');
        $anagrafica->sitoweb = post('sitoweb');
        $anagrafica->nome_cognome = post('nome_cognome');
        $anagrafica->iscrizione_tribunale = post('iscrizione_tribunale');
        $anagrafica->cciaa = post('cciaa');
        $anagrafica->cciaa_citta = post('cciaa_citta');
        $anagrafica->n_alboartigiani = post('n_alboartigiani');
        $anagrafica->foro_competenza = post('foro_competenza');
        $anagrafica->colore = post('colore');
        $anagrafica->id_tipo_intervento_default = post('id_tipo_intervento_default') ?: null;
        $anagrafica->id_ritenuta_acconto_acquisti = post('id_ritenuta_acconto_acquisti');
        $anagrafica->id_ritenuta_acconto_vendite = post('id_ritenuta_acconto_vendite');

        $anagrafica->updateTipologie((array) post('id_tipo_anagrafica'));

        $anagrafica->save();

        // Informazioni sulla sede
        $sede = $anagrafica->sedeLegale();
        $sede->partita_iva = post('piva');
        $sede->codice_fiscale = post('codice_fiscale');
        $sede->indirizzo = post('indirizzo');
        $sede->indirizzo2 = post('indirizzo2');
        $sede->citta = post('citta');
        $sede->cap = post('cap');
        $sede->provincia = post('provincia');
        $sede->km = post('km');
        $sede->id_nazione = post('id_nazione') ?: null;
        $sede->gaddress = post('gaddress');
        $sede->lat = post('lat');
        $sede->lng = post('lng');
        $sede->telefono = post('telefono');
        $sede->cellulare = post('cellulare');
        $sede->fax = post('fax');
        $sede->idzona = post('idzona');
        $sede->email = post('email');

        $sede->save();

        flash()->info(str_replace('_NAME_', '"'.post('ragione_sociale').'"', "Informazioni per l'anagrafica _NAME_ salvate correttamente!"));

        // Validazione della Partita IVA
        $partita_iva = $anagrafica->partita_iva;
        $check_vat_number = Validate::isValidVatNumber($partita_iva);
        if (empty($check_vat_number)) {
            flash()->error(tr('Attenzione: la partita IVA _IVA_ sembra non essere valida', [
                '_IVA_' => $partita_iva,
            ]));
        }

        // Aggiorno il codice anagrafica se non è già presente, altrimenti lo ignoro
        if ($anagrafica->codice != post('codice')) {
            flash()->error(tr("Il codice anagrafica inserito esiste già! Inserirne un'altro..."));
        }

        // Aggiorno gli agenti collegati
        $dbo->sync('an_anagrafiche_agenti', ['idanagrafica' => $id_record], ['idagente' => (array) post('idagenti')]);

        // Se l'agente di default è stato elencato anche tra gli agenti secondari lo rimuovo
        if (!empty(post('idagente'))) {
            $dbo->query('DELETE FROM an_anagrafiche_agenti WHERE idanagrafica='.prepare($id_record).' AND idagente='.prepare(post('idagente')));
        }

        break;

    case 'add':
        $id_tipo_anagrafica = post('id_tipo_anagrafica');
        $ragione_sociale = post('ragione_sociale');

        $anagrafica = Anagrafica::make($ragione_sociale, $id_tipo_anagrafica);
        $id_record = $anagrafica->id;

        // Se ad aggiungere un cliente è un agente, lo imposto come agente di quel cliente
        // Lettura tipologia dell'utente loggato
        $agente_is_logged = false;

        $rs = $dbo->fetchArray('SELECT descrizione FROM an_tipianagrafiche INNER JOIN an_tipianagrafiche_anagrafiche ON an_tipianagrafiche.id = an_tipianagrafiche_anagrafiche.id_tipo_anagrafica WHERE idanagrafica = '.prepare($user['idanagrafica']));

        for ($i = 0; $i < count($rs); ++$i) {
            if ($rs[$i]['descrizione'] == 'Agente') {
                $agente_is_logged = true;
                $i = count($rs);
            }
        }

        $idagente = ($agente_is_logged && in_array($id_cliente, $id_tipo_anagrafica)) ? $user['idanagrafica'] : 0;

        $anagrafica->partita_iva = post('piva');
        $anagrafica->codice_fiscale = post('codice_fiscale');
        $anagrafica->indirizzo = post('indirizzo');
        $anagrafica->citta = post('citta');
        $anagrafica->cap = post('cap');
        $anagrafica->provincia = post('provincia');
        $anagrafica->telefono = post('telefono');
        $anagrafica->cellulare = post('cellulare');
        $anagrafica->email = post('email');
        $anagrafica->idrelazione = post('idrelazione');
        $anagrafica->idagente = $idagente;

        $anagrafica->save();

        if ($anagrafica->isAzienda()) {
            flash()->info(tr('Anagrafica Azienda impostata come predefinita').'. '.tr('Per ulteriori informazionioni, visitare "Strumenti -> Impostazioni -> Generali"'));
        }

        // Lettura tipologia della nuova anagrafica
        $descrizioni_tipi = $anagrafica->tipi()->get()->pluck('descrizione')->toArray();
        if (isAjaxRequest() && in_array(post('tipoanagrafica'), $descrizioni_tipi)) {
            echo json_encode(['id' => $id_record, 'text' => $ragione_sociale]);
        }

        flash()->info(tr('Aggiunta nuova anagrafica di tipo _TYPE_', [
            '_TYPE_' => '"'.implode(', ', $descrizioni_tipi).'"',
        ]));

        break;

    case 'delete':
        // Se l'anagrafica non è l'azienda principale, la disattivo
        if (!in_array($id_azienda, $tipi_anagrafica)) {
            $dbo->query('UPDATE an_anagrafiche SET deleted_at = NOW() WHERE idanagrafica = '.prepare($id_record).Modules::getAdditionalsQuery($id_module));

            // Se l'anagrafica è collegata ad un utente lo disabilito
            $dbo->query('UPDATE zz_users SET enabled = 0 WHERE idanagrafica = '.prepare($id_record).Modules::getAdditionalsQuery($id_module));

            flash()->info(tr('Anagrafica eliminata!'));
        }

        break;
}

// Operazioni aggiuntive per il logo
if (filter('op') == 'link_file') {
    $nome = 'Logo stampe';

    if (setting('Azienda predefinita') == $id_record && filter('nome_allegato') == $nome) {
        Settings::setValue($nome, $upload);
    }
}
