<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia Android: VerificacionEntity → tabla verificaciones
 * Relación 1-a-1 con Fiscalizacion (DAO Android usa LIMIT 1).
 *
 * Registra datos de verificación de teléfono/horario del establecimiento.
 *
 * @property int         $id
 * @property int         $fiscalizacion_id
 * @property string|null $telefono_publicado
 * @property string|null $telefono_actualizado_price
 * @property string|null $horario_publicado
 * @property string|null $observaciones
 */
class Verificacion extends Model
{
    use HasFactory;

    protected $table = 'verificaciones';

    protected $fillable = [
        'fiscalizacion_id',
        'telefono_publicado',
        'telefono_actualizado_price',
        'horario_publicado',
        'observaciones',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }
}
