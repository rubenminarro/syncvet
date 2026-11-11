<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Exceptions\AppException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Http\Exceptions\HttpResponseException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Global (antes del ruteo) para que también los 404/405 de /api respondan JSON.
        $middleware->prepend(\App\Http\Middleware\EnsureJsonResponse::class);


        // La clínica se fija antes del route model binding: un id de otra clínica da 404 (D1).
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetCurrentClinic::class,
        );

        $middleware->alias([
            'clinic' => \App\Http\Middleware\SetCurrentClinic::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Los errores de negocio (AppException y sus subclases) son respuestas esperadas, no
        // fallas: no van al log. Así un login fallido no deja nada ahí. ClinicContextMissingException
        // no es una AppException y sí se reporta, porque es un bug.
        $exceptions->dontReport(AppException::class);

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'No autenticado.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Los datos proporcionados no son válidos.',
                    'data' => null,
                    'errors' => $e->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422);
            }
        });
        
        $exceptions->render(function (AppException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => $e->getStatus(),
                    'message' => $e->getMessage(),
                    'data' => null,
                    'errors' => $e->getErrors(),
                    'error_code' => $e->getErrorCode(),
                ], $e->getStatus(), $e->getHeaders());
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Recurso no encontrado.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Recurso no encontrado.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 405,
                    'message' => 'Método HTTP no permitido.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ], 405);
            }
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'No tenés permisos para realizar esta acción.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'FORBIDDEN',
                ], 403);
            }
        });

        $exceptions->render(function (BadRequestHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Solicitud incorrecta.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'BAD_REQUEST',
                ], 400);
            }
        });

        $exceptions->render(function (UnsupportedMediaTypeHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 415,
                    'message' => 'Tipo de contenido no soportado.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'UNSUPPORTED_MEDIA_TYPE',
                ], 415);
            }
        });

        $exceptions->render(function (NotAcceptableHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 406,
                    'message' => 'Formato de respuesta no aceptable.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'NOT_ACCEPTABLE',
                ], 406);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 429,
                    'message' => 'Demasiadas solicitudes.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'TOO_MANY_REQUESTS',
                ], 429);
            }
        });

        $exceptions->render(function (ServiceUnavailableHttpException $e, Request $request) 
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 503,
                    'message' => 'Servicio no disponible.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'SERVICE_UNAVAILABLE',
                ], 503);
            }
        });

        // Una respuesta ya armada (abort(response(...))) se devuelve tal cual.
        $exceptions->render(function (HttpResponseException $e, Request $request) {
            return $e->getResponse();
        });

        // Resto de los errores HTTP (abort(403), una AuthorizationException, abort(409)…): conservan su
        // código en lugar de caer en el 500 de abajo. Los tipos con renderer propio ya respondieron arriba.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->expectsJson()) {
                $status = $e->getStatusCode();

                [$message, $errorCode] = match ($status) {
                    400 => ['Solicitud incorrecta.', 'BAD_REQUEST'],
                    401 => ['No autenticado.', 'UNAUTHENTICATED'],
                    403 => ['No tenés permisos para realizar esta acción.', 'FORBIDDEN'],
                    404 => ['Recurso no encontrado.', 'RESOURCE_NOT_FOUND'],
                    405 => ['Método HTTP no permitido.', 'METHOD_NOT_ALLOWED'],
                    406 => ['Formato de respuesta no aceptable.', 'NOT_ACCEPTABLE'],
                    409 => ['La solicitud entra en conflicto con el estado actual del recurso.', 'CONFLICT'],
                    // post_max_size de PHP superado (PostTooLargeException; FASE 1, entrega 1.5).
                    413 => ['Los datos enviados superan el tamaño permitido.', 'PAYLOAD_TOO_LARGE'],
                    415 => ['Tipo de contenido no soportado.', 'UNSUPPORTED_MEDIA_TYPE'],
                    429 => ['Demasiadas solicitudes.', 'TOO_MANY_REQUESTS'],
                    503 => ['Servicio no disponible.', 'SERVICE_UNAVAILABLE'],
                    default => $status >= 500
                        ? ['Error interno del servidor.', 'INTERNAL_SERVER_ERROR']
                        : ['No se pudo procesar la solicitud.', 'HTTP_ERROR'],
                };

                return response()->json([
                    'success' => false,
                    'status' => $status,
                    'message' => $message,
                    'data' => null,
                    'errors' => null,
                    'error_code' => $errorCode,
                ], $status, $e->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request)
        {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 500,
                    'message' => 'Error interno del servidor.',
                    'data' => null,
                    'errors' => null,
                    'error_code' => 'INTERNAL_SERVER_ERROR',
                ], 500);
            }
        });

    })->create();
