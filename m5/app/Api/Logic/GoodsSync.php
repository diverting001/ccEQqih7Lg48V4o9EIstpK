<?php

namespace App\Api\Logic;

use App\Api\Model\Goods\Sync as SyncModel;

class GoodsSync
{
    /**
     * 保存商品同步消息
     *
     * @param   $productBn      string      商品编码
     * @param   $type           int         类型(1:新增/全部 2:基础信息 3:核心价格上下架)
     * @param   $status         int         状态(0:待处理 1:待审核 2:待同步 3:同步中 4:同步成功 5:同步失败)
     * @return  boolean
     */
    public function saveGoodsSyncMessage($productBn, $type, $status = 0)
    {
        if (empty($productBn))
        {
            return false;
        }

        $syncModel = new SyncModel();

        if ($status == 0 && $syncModel->getGoodsSyncMessageDetailByProduct($productBn, $type, array(0, 1, 2)))
        {
            return true;
        }

        $data = array(
            'product_bn'    => $productBn,
            'type'          => $type,
            'status'        => $status,
            'create_time'   => time(),
            'update_time'   => time(),
        );

        $messageId = $syncModel->addGoodsSyncMessage($data);

        return $messageId ? true : false;
    }

}
