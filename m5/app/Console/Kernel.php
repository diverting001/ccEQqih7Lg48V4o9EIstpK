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
        \App\Console\Commands\ExceptionMsg::class,
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
        \App\Console\Commands\OrderTask::class,
        \App\Console\Commands\PointRetrieve::class,
        \App\Console\Commands\OrderPointShareRepair::class,
        \App\Console\Commands\PointRecovery::class,
        \App\Console\Commands\OverduePointConsume::class,
        \App\Console\Commands\OrderReview::class,
        \App\Console\Commands\GoodsModifyUpdateToES::class,
        \App\Console\Commands\Pricingset::class ,
        \App\Console\Commands\MessageCenter::class,
        \App\Console\Commands\CatCache::class,
        \App\Console\Commands\PriceDiff::class,
        \App\Console\Commands\MessageResult::class,
        \App\Console\Commands\Promotion::class,
        \App\Console\Commands\OrderAddress::class,
        \App\Console\Commands\EsGoodsBrandsUpdate::class,
        \App\Console\Commands\OutletChangeUpdateToEs::class,
        \App\Console\Commands\GoodsOutletChangeUpdateToEs::class,
        \App\Console\Commands\DaemonTaskTableData2MQ::class,
        \App\Console\Commands\DaemonTaskConsumeMessage::class,
        \App\Console\Commands\ExpressSync::class,
        \App\Console\Commands\ExpressPickup::class,
        \App\Console\Commands\EsOrderSync::class,
        \App\Console\Commands\BatchOldOrder2ES::class,
        \App\Console\Commands\EsOrderAddIndex::class,
        \App\Console\Commands\BatchOldOrderExendInfoSync::class,
        \App\Console\Commands\PricingSetMessage::class,
        \App\Console\Commands\OpenPlatformAutoRefreshToken::class,
        \App\Console\Commands\FourRegionGpsSupplement::class,
        \App\Console\Commands\PromotionVoucherMemberReCompany::class,
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
