<?php

namespace Ayvazyan10\Imagic\Tests;

use Ayvazyan10\Imagic\FieldServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Nova\NovaCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            NovaCoreServiceProvider::class,
            FieldServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('filesystems.disks.imagic-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/imagic-test'),
        ]);
        $app['config']->set('imagic.disk', 'imagic-test');
        $app['config']->set('imagic.transform.format', 'png');
        $app['config']->set('imagic.media_library.api_path', 'nova-vendor/imagic-test');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }
}
