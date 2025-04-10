<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\GoodsSync as GoodsSyncLogic;
use App\Api\V1\Service\Search\Elasticsearchupindexdata;
use Illuminate\Http\Request;

class GoodsSyncController extends BaseController
{
    /**
     * 保存消息
     *
     * @return  string
     */
    public function Message(Request $request)
    {
        $data = $this->getContentArray($request);

        // 货品BN
        $productBn = $data['product_bn'];

        // 类型(1:新增/全部 2:基础信息 3:核心价格上下架)
        $type = $data['type'] ? intval($data['type']) : 1;

        $goodsSyncLogic = new GoodsSyncLogic();

        // 保存商品同步消息
        $result = $goodsSyncLogic->saveGoodsSyncMessage($productBn, $type);

        if ( ! $result)
        {
            $this->setErrorMsg('保存消息失败');
            return $this->outputFormat([], 403);
        }

        return $this->outputFormat([]);
    }

    public function GoodsData(Request $request) {
        $goods_ids = $this->getContentArray($request)['goods_id'];
        if(empty($goods_ids)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 500);
        }
        $res = (new Elasticsearchupindexdata())->SaveElasticSearchsData($goods_ids);
        return $this->outputFormat($res);
    }
}
