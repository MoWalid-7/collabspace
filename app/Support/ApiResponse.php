<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::resolveResources($data),
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function ok(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return self::success($data, $message, 200);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
    ): JsonResponse {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    private static function resolveResources(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return self::resolveResources($data->resolve(request()));
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::resolveResources($value);
            }
        }

        return $data;
    }
}
