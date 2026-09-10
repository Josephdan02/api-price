<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de rate limiting del login — FASE SEGURIDAD.
 *
 * POST /api/login está limitado por throttle:login a 5 intentos/minuto
 * por combinación DNI + IP (ver AppServiceProvider::boot()).
 *
 * phpunit.xml usa CACHE_STORE=array → los contadores del limiter son
 * aislados por test (nueva instancia de app en cada test).
 *
 * BD: price_api_test (phpunit.xml)
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_URL = '/api/login';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']); // contadores aislados y deterministas
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function crearUsuario(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'dni'      => '12345678',
            'password' => bcrypt('password123'),
            'activo'   => true,
            'rol'      => 'FISCALIZADOR',
        ], $attrs));
    }

    private function loginFallido(string $dni = '99999999'): void
    {
        $this->postJson(self::LOGIN_URL, ['dni' => $dni, 'password' => 'incorrecta'])
             ->assertStatus(401);
    }

    // ── Test 1: el login funciona normalmente dentro del límite ─────────────

    #[Test]
    public function login_correcto_funciona_dentro_del_limite(): void
    {
        $user = $this->crearUsuario();

        $this->postJson(self::LOGIN_URL, [
            'dni'      => $user->dni,
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    // ── Test 2: cinco intentos fallidos (el límite) aún no bloquean ─────────

    #[Test]
    public function cinco_intentos_fallidos_no_superan_el_limite(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido(); // 401, nunca 429
        }
    }

    // ── Test 3: el sexto intento devuelve HTTP 429 ───────────────────────────

    #[Test]
    public function sexto_intento_devuelve_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido();
        }

        $this->postJson(self::LOGIN_URL, ['dni' => '99999999', 'password' => 'incorrecta'])
             ->assertStatus(429);
    }

    // ── Test 4: la clave considera el DNI (distinto DNI, misma IP) ───────────

    #[Test]
    public function el_limite_considera_el_dni_del_intento(): void
    {
        // Agotar el límite con un DNI inexistente
        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido('99999999');
        }

        // Misma IP, DNI distinto con credenciales válidas → NO debe bloquearse
        $user = $this->crearUsuario();
        $this->postJson(self::LOGIN_URL, [
            'dni'      => $user->dni,
            'password' => 'password123',
        ])->assertStatus(200);
    }

    // ── Test 5: la ventana se reinicia después de 1 minuto ──────────────────

    #[Test]
    public function la_ventana_se_reinicia_tras_transcurrir_un_minuto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->loginFallido('99999999');
        }

        $this->postJson(self::LOGIN_URL, ['dni' => '99999999', 'password' => 'incorrecta'])
             ->assertStatus(429);

        $this->travel(61)->minutes();

        $user = $this->crearUsuario();
        $this->postJson(self::LOGIN_URL, [
            'dni'      => $user->dni,
            'password' => 'password123',
        ])->assertStatus(200);
    }

    protected function tearDown(): void
    {
        if (\Carbon\Carbon::hasTestNow()) {
            $this->travelBack();
        }
        parent::tearDown();
    }

    // ── Test 6: el throttle del login no afecta a otros endpoints ───────────

    #[Test]
    public function el_throttle_no_afecta_otros_endpoints_protegidos(): void
    {
        // Agotar el límite de login
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::LOGIN_URL, ['dni' => '99999999', 'password' => 'incorrecta']);
        }

        // Un endpoint autenticado sigue respondiendo 200
        $user = $this->crearUsuario();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')
             ->assertStatus(200)->assertJsonPath('success', true);
    }
}
