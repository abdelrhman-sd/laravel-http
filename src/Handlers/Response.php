<?php

namespace NightCommit\PHP\Frameworks\Laravel\Http\Handlers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as IlluminateResponse;

class Response
{
    public static function response(
        ?string     $message    = null,
        mixed       $data       = null,
        int         $status     = 200,
        array       $headers    = [],
    ): JsonResponse {

        $response = ['success' => true];

        is_null($message)
            ?: $response['message'] = $message;

        $response['status'] = $status;

        is_null($data)
            ?: $response['data']    = $data;

        return new JsonResponse($response, $status, $headers);
    }

    public static function ok(mixed $data = null, array $headers = []): JsonResponse
    {
        return static::response(data: $data, headers: $headers);
    }

    public static function created(mixed $data = null, ?string $resourceName = null, array $headers = []): JsonResponse
    {
        return static::response(
            __('http-response.created', ['Resource' => $resourceName]),
            $data,
            IlluminateResponse::HTTP_CREATED,
            $headers
        );
    }

    public static function updated(mixed $data = null, ?string $resourceName = null, array $headers = []): JsonResponse
    {
        return static::response(
            __('http-response.updated', ['Resource' => $resourceName]),
            $data,
            IlluminateResponse::HTTP_OK,
            $headers
        );
    }

    public static function deleted(mixed $data = null, ?string $resourceName = null, array $headers = []): JsonResponse
    {
        return static::response(
            __('http-response.deleted', ['Resource' => $resourceName]),
            $data,
            IlluminateResponse::HTTP_GONE,
            $headers
        );
    }
}
