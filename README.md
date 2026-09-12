# Laravel HTTP

A Laravel package that standardizes your API's JSON responses and automatically converts common exceptions into consistent, structured error responses — no more writing the same `try/catch` -> `response()->json(...)` boilerplate in every controller.

## Features

- **Consistent response shape** for both success and error responses.
- **Automatic exception mapping** — common Laravel/Symfony exceptions are converted to the right HTTP status and message without touching your `app/Exceptions/Handler.php`.
- **Translatable messages** via a publishable language file.
- **Extensible** — override `HttpExceptionHandler` to add your own exception mappings or change the response payload shape.
- Only kicks in for JSON/API requests, so your web routes are untouched.

## Installation

```bash
composer require night-commit/laravel-http
```

> Replace the package name above with your actual Packagist/vendor name if different.

Laravel's package auto-discovery will register `HttpResponseServiceProvider` automatically. If you have auto-discovery disabled, add it manually to `config/app.php`:

```php
'providers' => [
    // ...
    NightCommit\PHP\Frameworks\Laravel\Http\HttpResponseServiceProvider::class,
],
```

### Publishing the language file

```bash
php artisan vendor:publish --tag=lang
```

This publishes `lang/en/http-response.php` into your app's `lang/en/` directory, where you can translate or customize every message the package uses.

## How it works

On boot, the service provider registers a `renderable` callback on Laravel's exception handler. For every uncaught exception, it checks whether the request is JSON (`$request->expectsJson()`) or matches the configured request segment (`api/*` by default). If neither is true, it returns `null` and lets Laravel handle the exception normally (e.g. your web error pages).

Otherwise, the exception is passed to `HttpExceptionHandler::handle()`, which looks up a matching resolver in the exception registry, maps it to an HTTP status code, a message, and optional error details, and returns them as a JSON response.

### Exception mapping

Exceptions are matched against a registry of `exception class => resolver` pairs, checked in registration order (first `instanceof` match wins). Out of the box:

| Exception                                                             | Status | Message key                                                                                          |
|-----------------------------------------------------------------------|--------|------------------------------------------------------------------------------------------------------|
| `Illuminate\Auth\AuthenticationException`                               | 401    | `http-response.unauthenticated`                                                                        |
| `Illuminate\Auth\Access\AuthorizationException`                         | 403    | `http-response.forbidden`                                                                              |
| `Illuminate\Database\Eloquent\ModelNotFoundException`                   | 404    | `http-response.not_found`                                                                              |
| `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`          | 404    | `http-response.not_found`                                                                              |
| `Illuminate\Database\QueryException`                                    | 409    | `http-response.duplicated`                                                                             |
| `Illuminate\Validation\ValidationException`                             | 422    | `http-response.validation_failed` (+ field errors)                                                     |
| `GuzzleHttp\Exception\TooManyRedirectsException`                        | 429    | `http-response.many_requests`                                                                          |
| `Symfony\Component\HttpKernel\Exception\HttpExceptionInterface`         | exception's own status code | exception's own message, or `http-response.internal_server_error` if empty        |
| Anything else (no match found)                                        | 500    | exception's own message in non-production, or `http-response.internal_server_error` in production      |

Anything not in the registry falls through to the 500 default in `handle()`.

### Error response shape

```json
{
    "success": false,
    "status": 422,
    "error": {
        "message": "Validation failed",
        "details": {
            "email": [
                "The email field is required."
            ]
        }
    }
}
```

`details` is `null` for exceptions that don't carry field-level errors (everything except `ValidationException`).

### Customizing which requests are intercepted

By default, only requests that expect JSON or match `api/*` are handled. Override `requestSegement()` on a custom handler (see "Extending" below) to change the matched path pattern.

## The `Response` helper

For success responses, use the static `Response` helper instead of building `response()->json(...)` by hand:

```php
use NightCommit\PHP\Frameworks\Laravel\Http\Handlers\Response;

// 200 OK
return Response::ok($user);

// 201 Created
return Response::created($user, 'User');

// 200 OK, "updated" message
return Response::updated($user, 'User');

// Deleted (204/410 depending on your needs — see note below)
return Response::deleted(null, 'User');

// Fully custom
return Response::response('Custom message', $data, 200);
```

### Success response shape

```json
{
    "success": true,
    "message": "User created successfully.",
    "status": 201,
    "data": {
        "id": 1,
        "name": "Jane Doe"
    }
}
```

`message` and `data` keys are omitted entirely when `null`.

| Method      | Default status                | Message                     |
|-------------|--------------------------------|------------------------------|
| `ok()`      | 200                             | none                          |
| `created()` | 201 (`HTTP_CREATED`)            | `:Resource created successfully.` |
| `updated()` | 200 (`HTTP_OK`)                  | `:Resource updated successfully.` |
| `deleted()` | 410 (`HTTP_GONE`)                | `:Resource deleted successfully.` |

> **Note:** `deleted()` currently returns HTTP 410 (Gone) rather than the more conventional 200 or 204 for a successful delete. If that's not intentional for your API, override it in a custom handler or pass `Response::response()` directly with the status you want.

`:Resource` in the language file is replaced with the `$resourceName` argument you pass in (e.g. `'User'`).

## Customizing messages

Edit the published `lang/en/http-response.php` file (or add translations for other locales under `lang/{locale}/http-response.php`):

```php
return [
    'created'               => ':Resource created successfully.',
    'updated'                => ':Resource updated successfully.',
    'deleted'                => ':Resource deleted successfully.',
    'validation_failed'      => 'Validation failed',
    'not_found'              => ':Resource not found',
    'unauthenticated'        => 'unauthenticated',
    'forbidden'              => "You don't have the required permissions to perform this action",
    'http_error'             => 'HTTP error',
    'duplicated'             => 'Duplicate or invalid data',
    'many_requests'          => 'Too many requests',
    'internal_server_error'  => 'Internal server error',
];
```

## Extending

`HttpExceptionHandler` resolves exceptions through an internal registry (`exception class => resolver closure`) rather than a fixed `match` expression. You extend it by calling `extend()`, and the container lets you plug your own subclass in without editing the package.

### Wiring your subclass in

Bind your subclass over the base class in a service provider:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Support\HttpExceptionHandler;
use NightCommit\PHP\Frameworks\Laravel\Http\Handlers\HttpExceptionHandler as BaseHttpExceptionHandler;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BaseHttpExceptionHandler::class, HttpExceptionHandler::class);
    }
}
```

### Adding new exception mappings

```php
namespace App\Support;

use NightCommit\PHP\Frameworks\Laravel\Http\Handlers\HttpExceptionHandler as BaseHttpExceptionHandler;
use App\Exceptions\OutOfStockException;

class HttpExceptionHandler extends BaseHttpExceptionHandler
{
    public function __construct()
    {
        parent::__construct();

        $this->extend(OutOfStockException::class, fn () => [
            409, 'Item is out of stock', null,
        ]);
    }
}
```

### Overriding a built-in mapping

Call `extend()` again with the **same exception class** — the later registration replaces the earlier one, since `extend()` just overwrites that key in the registry:

```php
public function __construct()
{
    parent::__construct();

    $this->extend(\Illuminate\Auth\AuthenticationException::class, fn () => [
        401, 'Please log in to continue', null,
    ]);
}
```

Because matching is `instanceof`-based and checked in registration order, register more specific exception classes before more generic ones when one extends the other (e.g. a custom exception that extends `HttpException`).

### Overriding the error response shape

Override `response()` to change the JSON shape used for *every* error response, regardless of which exception produced it:

```php
namespace App\Support;

use NightCommit\PHP\Frameworks\Laravel\Http\Handlers\HttpExceptionHandler as BaseHttpExceptionHandler;
use Override;

class HttpExceptionHandler extends BaseHttpExceptionHandler
{
    #[Override]
    public function response(string $message, int $status, ?array $errors = []): array
    {
        return [
            'ok'      => false,
            'code'    => $status,
            'message' => $message,
            'errors'  => $errors,
        ];
    }
}
```

Bind it the same way as above, and every mapped exception (built-in or your own) will use this shape.

### Overriding the success response shape

`Response`'s methods are `public static`, so it isn't resolved through the container the way `HttpExceptionHandler` is. To change the success response shape, extend `Response` and use *your* class instead of the package's wherever you build responses:

```php
namespace App\Support;

use NightCommit\PHP\Frameworks\Laravel\Http\Handlers\Response as BaseResponse;
use Illuminate\Http\JsonResponse;
use Override;

class Response extends BaseResponse
{
    #[Override]
    public static function response(
        ?string $message = null,
        mixed   $data    = null,
        int     $status  = 200,
        array   $headers = [],
    ): JsonResponse {
        return new JsonResponse([
            'ok'      => true,
            'code'    => $status,
            'message' => $message,
            'result'  => $data,
        ], $status, $headers);
    }
}
```

```php
use App\Support\Response;

return Response::created($user, 'User');
```

`created()`, `updated()` and `deleted()` all funnel through `response()`, so overriding just that one method changes the shape for all of them. There's no binding step needed here — you opt in by importing your subclass in place of the package's.

## Requirements

- PHP ^8.x
- Laravel (Illuminate Contracts/Http/Support components)

## License
MIT
