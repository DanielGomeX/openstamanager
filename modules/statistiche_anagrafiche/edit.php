<?php

include_once __DIR__.'/../../core.php';

echo '
<hr>
<div class="card card-outline card-warning collapsed-card">
    <div class="card-header">
        <h4 class="card-title">
            '.tr('Periodi temporali').'
        </h4>
        <div class="card-tools float-right">
            <button class="btn btn-warning btn-sm" onclick="add_calendar()">
                <i class="fa fa-plus"></i> '.tr('Aggiungi periodo').'
            </button>
            <button type="button" class="btn btn-card-tool" data-card-widget="collapse">
                <i class="fa fa-minus"></i>
            </button>
        </div>
    </div>

    <div class="card-body" id="calendars">

    </div>
</div>

<div id="widgets">

</div>';

$statistiche = module('Statistiche');
/*
echo '
<script src="'.$statistiche->fileurl('js/functions.js').'"></script>
<script src="'.$statistiche->fileurl('js/manager.js').'"></script>
<script src="'.$statistiche->fileurl('js/calendar.js').'"></script>
<script src="'.$statistiche->fileurl('js/stat.js').'"></script>
<script src="'.$statistiche->fileurl('js/stats/table.js').'"></script>
<script src="'.$statistiche->fileurl('js/stats/widget.js').'"></script>';

<script>
var local_url = "'.str_replace('edit.php', '', $structure->fileurl('edit.php')).'";

function init_calendar(calendar) {
    var widgets = new Widget(calendar, "info.php", {}, "#widgets");

    calendar.addElement(widgets);
}
</script>

<script src="'.$statistiche->fileurl('js/init.js').'"></script>';
*/
