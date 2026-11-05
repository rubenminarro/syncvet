<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Los errores HTTP sin renderer propio en bootstrap/app.php conservan su código y
 * usan el contrato de error, en lugar de caer en el 500 genérico. Las rutas son
 * de prueba: viven bajo /api para que EnsureJsonResponse las trate como API.
 */
class ErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('test-never', fn ($user = null) => false);

        Route::get('api/_test/gate-denied', fn () => Gate::authorize('test-never'));
        Route::get('api/_test/abort-403', fn () => abort(403));
        Route::get('api/_test/abort-409', fn () => abort(409));
        Route::get('api/_test/abort-410', fn () => abort(410));
        Route::get('api/_test/abort-429', fn () => abort(429, '', ['Retry-After' => '60']));
        Route::get('api/_test/runtime', fn () => throw new RuntimeException('detalle interno'));
    }

    public function test_denied_authorization_responds_403_forbidden(): void
    {
        $this->get('api/_test/gate-denied')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'status' => 403,
                'message' => 'No tenés permisos para realizar esta acción.',
                'data' => null,
                'errors' => null,
                'error_code' => 'FORBIDDEN',
            ]);
    }

    public function test_abort_403_responds_forbidden(): void
    {
        $this->get('api/_test/abort-403')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'status' => 403,
                'message' => 'No tenés permisos para realizar esta acción.',
                'error_code' => 'FORBIDDEN',
            ]);
    }

    public function test_abort_409_responds_conflict(): void
    {
        $this->get('api/_test/abort-409')
            ->assertConflict()
            ->assertJson(['success' => false, 'status' => 409, 'error_code' => 'CONFLICT']);
    }

    public function test_other_client_error_keeps_its_status(): void
    {
        $this->get('api/_test/abort-410')
            ->assertStatus(410)
            ->assertJson(['success' => false, 'status' => 410, 'error_code' => 'HTTP_ERROR']);
    }

    public function test_http_exception_headers_are_kept(): void
    {
        $this->get('api/_test/abort-429')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertJson(['error_code' => 'TOO_MANY_REQUESTS']);
    }

    /**
     * La que lanza una acción ya la atrapa Route::run; al handler solo llega la de un
     * middleware. Se prueba el handler directamente.
     */
    public function test_prebuilt_response_is_returned_as_is(): void
    {
        $request = Request::create('/api/_test', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $exception = new HttpResponseException(response()->json(['ok' => true], 202));

        $response = TestResponse::fromBaseResponse($this->app->make(ExceptionHandler::class)->render($request, $exception));

        $response->assertStatus(202)->assertExactJson(['ok' => true]);
    }

    public function test_unexpected_exception_still_responds_500_without_details(): void
    {
        $this->get('api/_test/runtime')
            ->assertStatus(500)
            ->assertJson(['success' => false, 'status' => 500, 'error_code' => 'INTERNAL_SERVER_ERROR'])
            ->assertDontSee('detalle interno');
    }
}
