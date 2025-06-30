<?php

namespace Exxtensio\SqsExtension;

use Aws\Sqs\SqsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SqsClient::class, fn() => new SqsClient([
            'region' => config('queue.connections.sqs.region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('queue.connections.sqs.key'),
                'secret' => config('queue.connections.sqs.secret'),
            ],
        ]));

        $this->app->singleton(SqsService::class, fn () => new SqsService(
            $this->app->make(SqsClient::class)
        ));
    }

    public function boot(): void
    {

    }
}
