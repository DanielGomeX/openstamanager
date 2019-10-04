<?php

// Presenza di documenti associati
if ($record['doc_associati'] > 0) {
    echo '
<div class="alert alert-warning">'.tr('Non puoi eliminare questo categoria documento!').' '.tr('Ci sono _NUM_ documenti associati!', [
    '_NUM_' => $record['doc_associati'],
]).'</div>';
}

?><form action="" method="post" id="edit-form">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="op" value="update">

	<div class="row">

		<div class="col-md-12">
			{[ "type": "text", "label": "<?php echo tr('Descrizione'); ?>", "name": "descrizione", "required": 1, "value": "$descrizione$", "extra": "" ]}
		</div>

	</div>
</form>

<?php

// Presenza di documenti associati
if ($record['doc_associati'] == 0) {
    echo '
<a href="#" class="btn btn-danger ask" data-backto="record-list">
    <i class="fa fa-trash"></i> '.tr('Elimina').'
</a>';
}
