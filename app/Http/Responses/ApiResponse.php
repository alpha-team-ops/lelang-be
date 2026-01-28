<?php

namespace App\Http\Responses;

class ApiResponse
{
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
        ];
    }

    public static function error(string $error, string $code = null, int $statusCode = 400): array
    {
        return [
            'success' => false,
            'error' => $error,
            'code' => $code,
        ];
    }

    public static function paginated($items, $total, $page, $perPage, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'lastPage' => ceil($total / $perPage),
            ],
            'message' => $message,
        ];
    }
}
