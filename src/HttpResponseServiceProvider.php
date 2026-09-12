<?php

namespace NightCommit\PHP\Frameworks\Laravel\Http;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use NightCommit\PHP\Frameworks\Laravel\Http\Handlers\HttpExceptionHandler;
use Throwable;

class HttpResponseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../lang/en/http-response.php' => $this->app->langPath('en/http-response.php'),
        ], 'lang');

        $this->registerExceptionHandling();
    }

    protected function registerExceptionHandling(): void
    {
        $responseExceptionHandler = $this
            ->app
            ->make(HttpExceptionHandler::class);

        $this
            ->app
            ->make(ExceptionHandler::class)
            ->renderable(function (Throwable $e, Request $request) use ($responseExceptionHandler) {

                if (!$request->expectsJson() && !$request
                    ->is($responseExceptionHandler->requestSegement())) {

                    return null;
                }

                [$status, $message, $errors]
                    = $responseExceptionHandler->handle($e, $request);

                return response()->json(
                    $responseExceptionHandler->response($message, $status, $errors),
                    $status
                );
            });
    }
}
