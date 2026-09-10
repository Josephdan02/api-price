<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia Android: FirmaEntity → tabla firmas
 *
 * Dos tipos de firma: FISCALIZADOR | AGENTE
 * imagen_firma almacena base64 o path (LONGTEXT en BD).
 *
 * @property int         $id
 * @property int         $fiscalizacion_id
 * @property string      $tipo_firma       (FISCALIZADOR | AGENTE)
 * @property string|null $nombre_completo
 * @property string|null $dni
 * @property string|null $relacion_agente
 * @property string|null $imagen_firma
 * @property string|null $fecha_firma
 */
class Firma extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscalizacion_id',
        'tipo_firma',
        'nombre_completo',
        'dni',
        'relacion_agente',
        'imagen_firma',
        'fecha_firma',
    ];

    protected function casts(): array
    {
        return [
            'fecha_firma' => 'date:Y-m-d',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }
}
