<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SendOrderExceptionMsg::class,
        \App\Console\Commands\WmsRetry::class,
        \App\Console\Commands\Express::class,
        \App\Console\Commands\Stock::class,
        \App\Console\Commands\OrderMessage::class,
        \App\Console\Commands\Voucher::class,
        \App\Console\Commands\VoucherTask::class,
        \App\Console\Commands\SearchBusinessUp::class,
        \App\Console\Commands\EsProcess::class,
        \App\Console\Commands\EsRedis::class,
        \App\Console\Commands\EsMRYXRedis::class,
        \App\Console\Commands\RedisVoucher::class,
        \App\Console\Commands\EInvoice::class,
        \App\Console\Commands\PointUpgrade::class,
        \App\Console\Commands\InvoiceV2::class,
        \App\Console\Commands\MessageHandler::class,
        \App\Console\Commands\CreditRemind::class,
        \App\Console\Commands\LogV3::class,
        \App\Console\Commands\OrderInvoiceTask::class,
        \App\Console\Commands\PointRetrieve::class,
        \App\Console\Commands\OrderPointShareRepair::class,
        \App\Console\Commands\PointRecovery::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    }
}
