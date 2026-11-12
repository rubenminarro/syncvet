<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(
        string $message = 'OK',
        mixed $data = null,
        int $status = 200,
        mixed $meta = null
    ): JsonResponse {
        return response()->json([
            'success'   => true,
            'status'    => $status,
            'message'   => $message,
            'data'      => $data,
            'errors'    => null,
            'meta'      => $meta,
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    protected function errorResponse(
        string $message = 'Error',
        int $status = 422,
        ?string $errorCode = null,
        ?array $errors = null
    ): JsonResponse {
        return response()->json([
            'success'   => false,
            'status'    => $status,
            'message'   => $message,
            'data'      => null,
            'errors'    => $errors,
            'error_code' => $errorCode,
        ], $status);
    }

    /**
     * meta de todo listado paginado, en snake_case como el resto de la API (entrega
     * front-00, B3). El único lugar que arma meta.pagination.
     *
     * @return array{pagination: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
