<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia Android: HechoVerificadoEntity → tabla hechos_verificados
 *
 * Un hecho verificado documenta evidencia específica de un incumplimiento
 * dentro de una fiscalización. Tiene FK a FiscalizacionIncumplimiento
 * (no directamente a IncumplimientoCatalogo) porque pertenece a la
 * selección específica de un incumplimiento en esa fiscalización.
 *
 * Al borrar un FiscalizacionIncumplimiento se eliminan en cascada sus hechos.
 *
 * @property int         $id
 * @property int         $fiscalizacion_id
 * @property int         $fiscalizacion_incumplimiento_id
 * @property int|null    $user_id
 * @property string      $descripcion
 * @property string|null $fecha_registro
 */
class HechoVerificado extends Model
{
    use HasFactory;

    protected $table = 'hechos_verificados';

    protected $fillable = [
        'fiscalizacion_id',
        'fiscalizacion_incumplimiento_id',
        'user_id',
        'descripcion',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'date:Y-m-d',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }

    public function fiscalizacionIncumplimiento(): BelongsTo
    {
        return $this->belongsTo(FiscalizacionIncumplimiento::class, 'fiscalizacion_incumplimiento_id');
    }

    /** Usuario que registró el hecho */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
