<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Stock\Restrict as ProductRestrict;
use App\Api\Model\Stock\Product as Product;
use Illuminate\Http\Request;

class StockRestrictController extends BaseController
{

    //创建库存限制
    public function Create(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $name = isset($content_data['name']) ? trim($content_data['name']) : '';
        $product_bn = isset($content_data['product_bn']) ? trim($content_data['product_bn']) : '';
        $max_stock = isset($content_data['max_stock']) ? intval($content_data['max_stock']) : 0;
        $channel = isset($content_data['channel']) ? trim($content_data['channel']) : '';
        $start_time = isset($content_data['start_time']) ? intval($content_data['start_time']) : 0;
        $end_time = isset($content_data['end_time']) ? intval($content_data['end_time']) : 0;
        if (empty($product_bn) || empty($max_stock) || empty($channel) || empty($start_time) || empty($end_time)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if ($start_time > $end_time) {
            $this->setErrorMsg('开始时间不能大于结果时间');
            return $this->outputFormat([], 400);
        }
        //检查货品是否存在
        $product_info = Product::GetProductList([$product_bn]);
        if (empty($product_info)) {
            $this->setErrorMsg('货品不存在');
            return $this->outputFormat([], 400);
        }
        //同一货品同一时间只能有一个可用限制
        $is_set = ProductRestrict::CheckTime($product_bn, $channel, $start_time, $end_time);
        if ($is_set) {
            $this->setErrorMsg('时间段内已有其它限制');
            return $this->outputFormat([], 400);
        }
        $save_data = [
            'name' => $name,
            'product_bn' => $product_bn,
            'max_stock' => $max_stock,
            'channel' => $channel,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'create_time' => time(),
        ];
        $res = ProductRestrict::Save($save_data);
        if (!$res) {
            $this->setErrorMsg('保存失败');
            return $this->outputFormat([], 400);
        } else {
            $this->setErrorMsg('保存成功');
            return $this->outputFormat(['id' => $res]);
        }
    }

    /*
     * @todo 删除货品库存限制
     */
    public function Delete(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $id = intval($content_data['id']);
        if (empty($id)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $res = ProductRestrict::Delete($id);
        if ($res) {
            $this->setErrorMsg('删除成功');
            return $this->outputFormat([]);
        } else {
            $this->setErrorMsg('删除失败');
            return $this->outputFormat([], 400);
        }
    }

}
