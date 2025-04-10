<?php

namespace App\Cleansrs;

use Hhxsv5\LaravelS\Illuminate\Cleaners\CleanerInterface;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

class ApiDbCleaner implements CleanerInterface
{
    public function clean(Container $app, Container $snapshot)
    {
        if (!$app->offsetExists('db')) {
            return;
        }
        foreach (app('db')->getConnections() as $connection) {
            $level = $connection->transactionLevel();
            if ($level > 0) {
                $connection->rollBack(0);
            }
        }
    }
}
