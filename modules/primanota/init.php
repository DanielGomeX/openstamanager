<?php

if (isset($id_record)) {
    $record = $dbo->fetchOne('SELECT * FROM co_movimenti WHERE idmastrino='.prepare($id_record));
}
