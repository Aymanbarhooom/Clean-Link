<?php

// app/Traits/ApiResponse.php
namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    protected function successResponse(mixed $data = [], string $message = 'Operation successful', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function errorResponse(string $message = 'An error occurred', int $status = 400, mixed $errors = []): JsonResponse
    {
        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
