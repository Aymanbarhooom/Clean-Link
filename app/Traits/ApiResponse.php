<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

trait ApiResponse
{
    
    protected function successResponse(mixed $data = [], string $message = 'Operation successful', int $status = 200): JsonResponse
    {
        $response = [
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ];

        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof AbstractPaginator) {
            $paginated = $data->resource->toArray();
            
            $response['data']  = $data->resolve(); 
            $response['links'] = [
                'first' => $paginated['first_page_url'] ?? null,
                'last'  => $paginated['last_page_url'] ?? null,
                'prev'  => $paginated['prev_page_url'] ?? null,
                'next'  => $paginated['next_page_url'] ?? null,
            ];
            $response['meta']  = [
                'current_page' => $paginated['current_page'] ?? null,
                'from'         => $paginated['from'] ?? null,
                'last_page'    => $paginated['last_page'] ?? null,
                'per_page'     => $paginated['per_page'] ?? null,
                'to'           => $paginated['to'] ?? null,
                'total'        => $paginated['total'] ?? null,
            ];
        }
        elseif ($data instanceof AbstractPaginator) {
            $paginated = $data->toArray();
            
            $response['data']  = $paginated['data'];
            $response['links'] = [
                'first' => $paginated['first_page_url'] ?? null,
                'last'  => $paginated['last_page_url'] ?? null,
                'prev'  => $paginated['prev_page_url'] ?? null,
                'next'  => $paginated['next_page_url'] ?? null,
            ];
            $response['meta']  = [
                'current_page' => $paginated['current_page'] ?? null,
                'from'         => $paginated['from'] ?? null,
                'last_page'    => $paginated['last_page'] ?? null,
                'per_page'     => $paginated['per_page'] ?? null,
                'to'           => $paginated['to'] ?? null,
                'total'        => $paginated['total'] ?? null,
            ];
        }

        return response()->json($response, $status);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function errorResponse(string $message = 'An error occurred', int $status = 400, mixed $errors = []): JsonResponse
    {
        $response = [
            'status'  => $status,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
