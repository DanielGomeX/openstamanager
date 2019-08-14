<?php

switch ($resource) {
    case 'preventivi':
        if (isset($superselect['idanagrafica'])) {
            $query = 'SELECT co_preventivi.id AS id, an_anagrafiche.idanagrafica, CONCAT("Preventivo ", numero, " del ", DATE_FORMAT(data_bozza, "%d/%m/%Y"), " - ", co_preventivi.nome, " [", (SELECT `descrizione` FROM `co_statipreventivi` WHERE `co_statipreventivi`.`id` = `id_stato`) , "]") AS descrizione, co_preventivi.id_tipo_intervento, (SELECT descrizione FROM in_tipiintervento WHERE in_tipiintervento.id = co_preventivi.id_tipo_intervento) AS id_tipo_intervento_descrizione, (SELECT tempo_standard FROM in_tipiintervento WHERE in_tipiintervento.idtipointervento = co_preventivi.idtipointervento) AS tempo_standard, (SELECT SUM(subtotale) FROM co_righe_preventivi WHERE idpreventivo=co_preventivi.id GROUP BY idpreventivo) AS totale, (SELECT SUM(sconto) FROM co_righe_preventivi WHERE idpreventivo=co_preventivi.id GROUP BY idpreventivo) AS sconto FROM co_preventivi INNER JOIN an_anagrafiche ON co_preventivi.idanagrafica=an_anagrafiche.idanagrafica |where| ORDER BY id';

            foreach ($elements as $element) {
                $filter[] = 'id='.prepare($element);
            }

            if (empty($elements)) {
                $where[] = 'an_anagrafiche.idanagrafica='.prepare($superselect['idanagrafica']);
                $where[] = 'co_preventivi.default_revision=1';

                $stato = !empty($superselect['stato']) ? $superselect['stato'] : 'is_fatturabile';
                $where[] = 'idstato IN (SELECT `id` FROM `co_statipreventivi` WHERE '.$stato.' = 1)';
            }

            if (!empty($search)) {
                $search_fields[] = 'nome LIKE '.prepare('%'.$search.'%');
            }

            $custom['id_tipo_intervento'] = 'id_tipo_intervento';
            $custom['id_tipo_intervento_descrizione'] = 'id_tipo_intervento_descrizione';
            $custom['tempo_standard'] = 'tempo_standard';
            $custom['totale'] = 'totale';
            $custom['sconto'] = 'sconto';
        }

        break;

    case 'preventivi_aperti':
        $query = 'SELECT co_preventivi.id AS id, CONCAT(numero, " ", co_preventivi.nome, " (", ragione_sociale, ")") AS descrizione FROM co_preventivi INNER JOIN an_anagrafiche ON co_preventivi.idanagrafica=an_anagrafiche.idanagrafica |where| ORDER BY id';

        foreach ($elements as $element) {
            $filter[] = 'idpreventivo='.prepare($element);
        }
        $where[] = 'id_stato IN (1)';
        if (!empty($search)) {
            $search_fields[] = 'nome LIKE '.prepare('%'.$search.'%');
        }

        break;
}
