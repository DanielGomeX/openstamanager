<?php

namespace Modules\Fatture;

use Base\Description;

class Descrizione extends Description
{
    protected $table = 'co_righe_documenti';

    /**
     * Crea una nuova riga collegata ad una fattura.
     *
     * @param Fattura $fattura
     *
     * @return self
     */
    public static function new(Fattura $fattura)
    {
        $model = parent::new();

        $model->fattura()->associate($fattura);

        return $model;
    }

    public function fattura()
    {
        return $this->belongsTo(Fattura::class, 'iddocumento');
    }
}