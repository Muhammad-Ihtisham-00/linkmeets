<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Success response
     */
    protected function successResponse(string $message, $data = null, int $statusCode = 200)
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Error response (general, including validation)
     */
    protected function errorResponse(string $message, $error = null, int $statusCode = 400)
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $error,
        ], $statusCode);
    }
}
