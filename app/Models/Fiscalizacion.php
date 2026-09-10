<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Equivalencia Android: FiscalizacionEntity → tabla fiscalizaciones
 * Entidad central del sistema PRICE.
 *
 * Estados: BORRADOR | EN_PROCESO | FINALIZADA | ACTA_GENERADA
 *
 * Nota: agente_fiscalizado, codigo_osinergmin, direccion, etc. son
 * copias desnormalizadas del establecimiento al momento de la diligencia.
 * Esto es intencional (ver FiscalizacionRepository Android).
 *
 * @property int         $id
 * @property int         $establecimiento_id
 * @property int         $user_id
 * @property string|null $numero_expediente
 * @property string|null $agente_fiscalizado
 * @property string|null $codigo_osinergmin
 * @property string|null $registro_hidrocarburos
 * @property string      $fecha_diligencia
 * @property string      $hora_apertura
 * @property string|null $hora_cierre
 * @property string|null $direccion
 * @property string|null $distrito
 * @property string|null $provincia
 * @property string|null $departamento
 * @property string|null $ruc_dni
 * @property string|null $telefono_fax
 * @property string      $estado
 */
class Fiscalizacion extends Model
{
    use HasFactory;

    protected $table = 'fiscalizaciones';

    protected $fillable = [
        'establecimiento_id',
        'user_id',
        'numero_expediente',
        'agente_fiscalizado',
        'codigo_osinergmin',
        'registro_hidrocarburos',
        'fecha_diligencia',
        'hora_apertura',
        'hora_cierre',
        'direccion',
        'distrito',
        'provincia',
        'departamento',
        'ruc_dni',
        'telefono_fax',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_diligencia' => 'date:Y-m-d',
        ];
    }

    // ─── Constantes de estado ─────────────────────────────────────────────────

    const ESTADO_BORRADOR      = 'BORRADOR';
    const ESTADO_EN_PROCESO    = 'EN_PROCESO';
    const ESTADO_FINALIZADA    = 'FINALIZADA';
    const ESTADO_ACTA_GENERADA = 'ACTA_GENERADA';

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    /** Fiscalizador responsable */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class);
    }

    public function verificacion(): HasOne
    {
        return $this->hasOne(Verificacion::class);
    }

    public function observacion(): HasOne
    {
        return $this->hasOne(Observacion::class);
    }

    public function firmas(): HasMany
    {
        return $this->hasMany(Firma::class);
    }

    public function documento(): HasOne
    {
        return $this->hasOne(Documento::class);
    }

    public function fiscalizacionIncumplimientos(): HasMany
    {
        return $this->hasMany(FiscalizacionIncumplimiento::class);
    }

    /** Acceso directo al catálogo de incumplimientos via pivote */
    public function incumplimientos(): BelongsToMany
    {
        return $this->belongsToMany(
            IncumplimientoCatalogo::class,
            'fiscalizacion_incumplimientos',
            'fiscalizacion_id',
            'incumplimiento_catalogo_id'
        )->withPivot(['seleccionado', 'observacion'])
         ->withTimestamps();
    }

    public function hechosVerificados(): HasMany
    {
        return $this->hasMany(HechoVerificado::class);
    }
}
