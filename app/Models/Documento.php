<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia Android: DocumentoEntity → tabla documentos
 * Relación hasOne desde Fiscalizacion (unique constraint en fiscalizacion_id).
 *
 * Representa el documento PDF del acta generada.
 *
 * @property int              $id
 * @property int              $fiscalizacion_id
 * @property string|null      $nombre_archivo
 * @property string|null      $ruta_archivo
 * @property \Carbon\Carbon|null $fecha_generacion
 * @property int|null         $numero_paginas
 * @property string|null      $estado
 */
class Documento extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscalizacion_id',
        'nombre_archivo',
        'ruta_archivo',
        'fecha_generacion',
        'numero_paginas',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_generacion' => 'datetime',
            'numero_paginas'   => 'integer',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }
}
