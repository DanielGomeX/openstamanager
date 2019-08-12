
<?php

$messages = flash()->getMessages();

if (!Auth::check() && (!empty($messages['info']) || !empty($messages['warning']) || !empty($messages['error']))) {
    echo '
<div class="card card-outline card-warning card-center">
    <div class="card-header text-center">
        <h3 class="card-title">'.tr('Informazioni').'</h3>
    </div>

    <div class="card-body">';
}

// Infomazioni
if (!empty($messages['info'])) {
    foreach ($messages['info'] as $value) {
        echo '
        <div class="alert alert-success push">
            <i class="fa fa-check"></i> '.$value.'
        </div>';
    }
}

// Errori
if (!empty($messages['error'])) {
    foreach ($messages['error'] as $value) {
        echo '
        <div class="alert alert-danger push">
            <i class="fa fa-times"></i> '.$value.'
        </div>';
    }
}

// Avvisi
if (!empty($messages['warning'])) {
    foreach ($messages['warning'] as $value) {
        echo '
        <div class="alert alert-warning push">
            <i class="fa fa-warning"></i>
            '.$value.'
        </div>';
    }
}

if (!Auth::check() && (!empty($messages['info']) || !empty($messages['warning']) || !empty($messages['error']))) {
    echo '
    </div>
</div>';
}
