<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia Android: PrecioEntity → tabla precios
 *
 * Cuatro tipos de precio registrados por fiscalización + producto:
 *   - precio_price:     precio en sistema PRICE
 *   - precio_publicado: precio en letrero del establecimiento
 *   - precio_surtidor:  precio en surtidor físico
 *   - precio_descuento: precio con descuento aplicado
 *
 * Constraint BD: precios DECIMAL UNSIGNED (no negativos).
 * Un producto no puede repetirse en la misma fiscalización (unique constraint).
 *
 * @property int         $id
 * @property int         $fiscalizacion_id
 * @property int         $producto_id
 * @property string|null $precio_price
 * @property string|null $precio_publicado
 * @property string|null $precio_surtidor
 * @property string|null $precio_descuento
 * @property bool        $tiene_descuento
 * @property string|null $observacion
 */
class Precio extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscalizacion_id',
        'producto_id',
        'precio_price',
        'precio_publicado',
        'precio_surtidor',
        'precio_descuento',
        'tiene_descuento',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'precio_price'     => 'decimal:4',
            'precio_publicado' => 'decimal:4',
            'precio_surtidor'  => 'decimal:4',
            'precio_descuento' => 'decimal:4',
            'tiene_descuento'  => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizacion(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
