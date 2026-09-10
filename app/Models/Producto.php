<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equivalencia Android: ProductoEntity → tabla productos
 * Catálogo de 11 combustibles/productos PRICE.
 * IDs 1-11 usados de forma fija por Android (PrecioRepository.initializeCatalog).
 *
 * Categorías: Líquidos | GLP | Envasado | Otros
 *
 * @property int    $id
 * @property string $nombre
 * @property string $categoria
 * @property string $unidad
 * @property bool   $activo
 */
class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'categoria',
        'unidad',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class);
    }
}
