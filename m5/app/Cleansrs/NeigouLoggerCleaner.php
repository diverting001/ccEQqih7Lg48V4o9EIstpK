<?php

namespace App\Cleansrs;

use Hhxsv5\LaravelS\Illuminate\Cleaners\CleanerInterface;
use Illuminate\Container\Container;
use Neigou\Logger;

class NeigouLoggerCleaner implements CleanerInterface
{
    public function clean(Container $app, Container $snapshot)
    {
        if (!class_exists(Logger::class) || !method_exists(Logger::class, 'clean')) {
            return;
        }
        Logger::clean();
    }
}
