<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-06
 * Time: 14:59
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Model\Point\Point as PointModel;
use App\Api\Model\PointScene\SceneMemberRel as SceneMemberRelModel;

class OrderPointShareRepair extends Command
{
    const COMMON_SCENE = 1;

    protected $signature = 'OrderPointShareRepair {oldid?}';

    protected $description = '订单积分均摊数据由于升级积分服务V3导致不可用数据修复';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $storeDb = app('api_db')->connection('neigou_store');

        $oldId = $this->argument('oldid') ? $this->argument('oldid') : 0;
        while (true) {
            $sql = 'SELECT * FROM `sdb_b2c_order_scene_point_info` where id > ' . $oldId . ' ORDER BY `id` asc LIMIT 100';

            $itemList = $storeDb->select($sql);
            if (count($itemList) <= 0) {
                $this->info('处理完成');
                break;
            }

            foreach ($itemList as $item) {
                $this->updateItem($item);
                $oldId = $item->id;
            }
        }
    }

    public function updateItem($item)
    {
        $orderInfo = OrderModel::GetOrderInfoById((string)$item->order_id);

        if (!$orderInfo) {
            $this->info('订单获取失败：不做处理');
            return;
        }

        $id           = $item->id;
        $companyId    = $orderInfo->company_id;
        $memberId     = $orderInfo->member_id;
        $sceneId      = $item->scene_id;
        $pointChannel = $orderInfo->point_channel;

        $pointChannelInfo = PointModel::GetChannelInfo($pointChannel);

        $storeDb = app('api_db')->connection('neigou_store');
        if ($pointChannelInfo->point_version == 1) {
            if ($sceneId != $companyId . '_' . $memberId . '_1') {
                $status = $storeDb->table('sdb_b2c_order_scene_point_info')
                    ->where('id', $id)
                    ->update([
                        'scene_id' => $companyId . '_' . $memberId . '_1'
                    ]);
                $this->info('内购积分：ID' . $id . ',' . $sceneId . '改为' . $companyId . '_' . $memberId . '_1状态' . intval($status));
            } else {
                $this->info('内购积分：' . $sceneId . '_不做处理');
            }
            return;
        }

        $memberAccountInfo = SceneMemberRelModel::FindByMemberAndScene($companyId, $memberId, $sceneId);
        if ($memberAccountInfo) {
            $status = $storeDb->table('sdb_b2c_order_scene_point_info')
                ->where('id', $id)
                ->update([
                    'scene_id' => $memberAccountInfo->account
                ]);
            $this->info('场景积分：ID' . $id . ',' . $sceneId . '改为' . $memberAccountInfo->account . ',状态' . intval($status));
        } else {
            $this->info('场景积分：companyid:' . $companyId . ',memberid:' . $memberId . ',sceneid:' . $sceneId . '账户获取失败_不做处理');
        }
    }
}
