<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equivalencia Android: FiscalizacionIncumplimientoEntity → tabla fiscalizacion_incumplimientos
 * Tabla pivote entre fiscalizaciones e incumplimientos_catalogo.
 *
 * Expuesta como modelo propio (no solo como pivot) porque:
 * 1. Tiene campos propios: seleccionado, observacion
 * 2. HechoVerificado tiene FK a esta tabla
 * 3. Android borra y reinserta todos los registros al editar la selección
 *
 * @property int         $id
 * @property int         $fiscalizacion_id
 * @property int         $incumplimiento_catalogo_id
 * @property bool        $seleccionado
 * @property string|null $observacion
 */
class FiscalizacionIncumplimiento extends Model
{
    use HasFactory;

    protected $table = 'fiscalizacion_incumplimientos';

    protected $fillable = [
        'fiscalizacion_id',
        'incumplimiento_catalogo_id',
        'seleccionado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'seleccionado' => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }

    public function incumplimientoCatalogo(): BelongsTo
    {
        return $this->belongsTo(IncumplimientoCatalogo::class, 'incumplimiento_catalogo_id');
    }

    /** Hechos verificados asociados a este incumplimiento en la fiscalización */
    public function hechosVerificados(): HasMany
    {
        return $this->hasMany(HechoVerificado::class, 'fiscalizacion_incumplimiento_id');
    }
}
