<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia Android: ObservacionEntity → tabla observaciones
 * Relación 1-a-1 con Fiscalizacion.
 *
 * Registra las observaciones finales del acta: ocurrencias, documentación,
 * manifestaciones del agente y negativas (identificación, suscripción, recepción).
 *
 * @property int         $id
 * @property int         $fiscalizacion_id
 * @property string|null $otras_ocurrencias
 * @property string|null $documentacion_recabada
 * @property string|null $manifestaciones_agente
 * @property bool        $negativa_identificacion
 * @property bool        $negativa_suscripcion
 * @property bool        $negativa_recepcion
 * @property string|null $observaciones_generales
 */
class Observacion extends Model
{
    use HasFactory;

    protected $table = 'observaciones';

    protected $fillable = [
        'fiscalizacion_id',
        'otras_ocurrencias',
        'documentacion_recabada',
        'manifestaciones_agente',
        'negativa_identificacion',
        'negativa_suscripcion',
        'negativa_recepcion',
        'observaciones_generales',
    ];

    protected function casts(): array
    {
        return [
            'negativa_identificacion' => 'boolean',
            'negativa_suscripcion'    => 'boolean',
            'negativa_recepcion'      => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }
}
