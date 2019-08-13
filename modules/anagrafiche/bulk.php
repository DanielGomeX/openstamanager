<?php

switch (post('op')) {
    case 'delete-bulk':

        $id_tipo_anagrafica_azienda = $dbo->fetchOne("SELECT id FROM an_tipianagrafiche WHERE descrizione='Azienda'")['id'];

        foreach ($id_records as $id) {
            $anagrafica = $dbo->fetchArray('SELECT an_tipianagrafiche.id FROM an_tipianagrafiche INNER JOIN an_tipianagrafiche_anagrafiche ON an_tipianagrafiche.id=an_tipianagrafiche_anagrafiche.id_tipo_anagrafica WHERE idanagrafica='.prepare($id));
            $tipi = array_column($anagrafica, 'id');

            // Se l'anagrafica non è di tipo Azienda
            if (!in_array($id_tipo_anagrafica_azienda, $tipi)) {
                $dbo->query('UPDATE an_anagrafiche SET deleted_at = NOW() WHERE idanagrafica = '.prepare($id).Modules::getAdditionalsQuery($id_module));
            }
        }

        flash()->info(tr('Anagrafiche eliminate!'));

        break;
}

if (App::debug()) {
    $operations = [
        'delete-bulk' => tr('Elimina selezionati'),
    ];
}

return $operations;
