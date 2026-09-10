<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equivalencia Android: IncumplimientoCatalogoEntity → tabla incumplimientos_catalogo
 * Catálogo institucional de 6 tipos de incumplimiento (I-01 a I-06).
 *
 * @property int         $id
 * @property string      $codigo     (I-01 … I-06)
 * @property string      $descripcion
 * @property string|null $base_legal
 * @property bool        $activo
 */
class IncumplimientoCatalogo extends Model
{
    use HasFactory;

    protected $table = 'incumplimientos_catalogo';

    protected $fillable = [
        'codigo',
        'descripcion',
        'base_legal',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacionIncumplimientos(): HasMany
    {
        return $this->hasMany(FiscalizacionIncumplimiento::class, 'incumplimiento_catalogo_id');
    }

    /** Fiscalizaciones que tienen este incumplimiento */
    public function fiscalizaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Fiscalizacion::class,
            'fiscalizacion_incumplimientos',
            'incumplimiento_catalogo_id',
            'fiscalizacion_id'
        )->withPivot(['seleccionado', 'observacion'])
         ->withTimestamps();
    }
}
