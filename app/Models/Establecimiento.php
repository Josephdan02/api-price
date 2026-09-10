<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equivalencia Android: EstablecimientoEntity → tabla establecimientos
 *
 * @property int         $id
 * @property string|null $codigo_osinergmin
 * @property string|null $registro_hidrocarburos
 * @property string      $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $ruc_dni
 * @property string|null $telefono
 * @property string|null $fax
 * @property string|null $direccion
 * @property string|null $distrito
 * @property string|null $provincia
 * @property string|null $departamento
 * @property float|null  $latitud
 * @property float|null  $longitud
 * @property bool        $activo
 */
class Establecimiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo_osinergmin',
        'registro_hidrocarburos',
        'razon_social',
        'nombre_comercial',
        'ruc_dni',
        'telefono',
        'fax',
        'direccion',
        'distrito',
        'provincia',
        'departamento',
        'latitud',
        'longitud',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'latitud'  => 'decimal:7',
            'longitud' => 'decimal:7',
            'activo'   => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function fiscalizaciones(): HasMany
    {
        return $this->hasMany(Fiscalizacion::class);
    }
}
