<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Api\Dispatcher;

class ServiceCallProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * 注册服务
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Dispatcher::class);
    }

    public function provides()
    {
        return [Dispatcher::class];
    }
}
