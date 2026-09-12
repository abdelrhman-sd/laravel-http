<?php

namespace NightCommit\PHP\Frameworks\Laravel\Http\Handlers;

use Closure;
use Throwable;
use Illuminate\Http\Request;

use Illuminate\Database\QueryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;


class HttpExceptionHandler
{
    /** @var array<class-string<Throwable>, Closure(Throwable,Request):array> */
    protected array $exceptionHandlers = [];
    protected Throwable $e;

    public function __construct()
    {
        $this->registerExceptionHandlers();
    }

    public function extend(string $exceptionHandler, Closure $resolver): self
    {
        $this->exceptionHandlers[$exceptionHandler] = $resolver;

        return $this;
    }

    public function response(string $message, int $status, ?array $errors = []): array
    {
        return [
            'success' => false,
            'status'  => $status,
            'error'   => [
                'message' => $message,
                'details' => $errors
            ]
        ];
    }

    public function requestSegement(): string
    {
        return 'api/*';
    }

    protected function registerExceptionHandlers(): void
    {
        $this->extend(AuthenticationException::class, fn() => [
            401,
            __('http-response.unauthenticated'),
            null
        ]);

        $this->extend(AuthorizationException::class, fn() => [
            403,
            __('http-response.forbidden'),
            null
        ]);

        $this->extend(ModelNotFoundException::class, fn() => [
            404,
            __('http-response.not_found'),
            null
        ]);

        $this->extend(QueryException::class, fn() => [
            409,
            __('http-response.duplicated'),
            null
        ]);

        $this->extend(ValidationException::class, fn(ValidationException $e) => [
            422,
            __('http-response.validation_failed'),
            $e->errors()
        ]);

        $this->extend(TooManyRedirectsException::class, fn() => [
            429,
            __('http-response.many_requests'),
            null
        ]);

        $this->extend(HttpExceptionInterface::class, fn(HttpExceptionInterface $e) => [
            $e->getStatusCode(),
            empty($e->getMessage())
                ? __('http-response.internal_server_error')
                : $this->e->getMessage(),
            null
        ]);
    }

    public function handle(Throwable $e, Request $request): array
    {
        foreach ($this->exceptionHandlers as $exceptionHandler => $resolver) {
            if ($e instanceof $exceptionHandler) {
                return $resolver($e, $request);
            }
        }
        return [
            500,
            app()->isProduction()
                ? __('http-response.internal_server_error')
                : $e->getMessage(),
            null,
        ];
    }
}
