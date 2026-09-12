<?php

namespace NightCommit\PHP\Frameworks\Laravel\Http\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use NightCommit\PHP\Frameworks\Laravel\Http\HttpResponseServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            HttpResponseServiceProvider::class
        ];
    }

    protected function defineRoutes($router)
    {
        // Register test-only routes here instead of routes/web.php,
        // since the package has no app routes file
        $router->post('/test/not-found', fn() => abort(404));
        $router->post('/test/unauthenticated', fn() => abort(401));
        $router->post('/test/forbidden', fn() => abort(403));
        $router->post('/test/model-not-found', fn() => throw new ModelNotFoundException);
        $router->post('/test/validation', function () {
            request()->validate(['email' => 'required|email']);
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        // If your package needs config values, set them here
        $app['config']->set('app.debug', false);
    }
}
