<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;

// Load test helpers
require_once __DIR__.'/helpers.php';

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup testing environment
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Add application key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Use array cache for testing to avoid database cache issues
        $app['config']->set('cache.default', 'array');

        // Configure OneClickLogin to use TestUser
        $app['config']->set('oneclicklogin.user_model', 'TestUser');
        $app['config']->set('oneclicklogin.email_field', 'email');

        // Setup auth configuration
        $app['config']->set('auth.providers.users.model', 'TestUser');

        // Create users table for testing
        $app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        // Add test routes that the package expects
        $router->get('/login', function () {
            return response('Login page');
        })->name('login');

        $router->get('/register', function () {
            return response('Register page');
        })->name('register');

        $router->get('/dashboard', function () {
            return response('Dashboard');
        });

        // Load the package routes
        $router->group(['middleware' => ['web']], function ($router) {
            require __DIR__.'/../routes/web.php';
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            \Grazulex\OneClickLogin\OneClickLoginServiceProvider::class,
        ];
    }
}
