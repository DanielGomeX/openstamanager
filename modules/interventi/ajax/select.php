<?php

switch ($resource) {
    case 'tipiintervento':
        $query = 'SELECT id, CASE WHEN ISNULL(tempo_standard) OR tempo_standard <= 0 THEN descrizione WHEN tempo_standard > 0 THEN  CONCAT(descrizione, \' (\', REPLACE(FORMAT(tempo_standard, 2), \'.\', \',\'), \' ore)\') END AS descrizione, tempo_standard FROM in_tipiintervento |where| ORDER BY id';

        foreach ($elements as $element) {
            $filter[] = 'id_tipo_intervento='.prepare($element);
        }
        if (!empty($search)) {
            $search_fields[] = 'descrizione LIKE '.prepare('%'.$search.'%');
        }

        $custom['tempo_standard'] = 'tempo_standard';

        break;
}
