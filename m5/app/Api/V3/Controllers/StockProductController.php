<?php

namespace App\Api\V3\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Stock\Product as Product;
use Illuminate\Http\Request;

class StockProductController extends BaseController
{

    //更新货品库存消息通知
    public function UpdateProductsMessage(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $product_list = $content_data['product_list'];
        $source = $content_data['source'];
        $force = isset($content_data['force']) ? intval($content_data['force']) : 0; //是否强制使用当前来源
        if (empty($product_list) || empty($source)) {
            $this->setErrorMsg('请选择更新货品bn');
            return $this->outputFormat([], 400);
        }
        app('db')->beginTransaction();
        try {
            foreach ($product_list as $product) {
                $product_info = Product::GetProductList([$product['bn']]);
                if (empty($product_info)) {
                    //创建记录
                    $save_data = [
                        'product_bn' => $product['bn'],
                        'type' => $product['type'],
                        'source' => $source,
                        'update_level' => 0,
                        'create_time' => time(),
                        'last_modified' => 0,
                    ];
                    $res = Product::AddProduct($save_data);
                    if (!$res) {
                        throw new Exception($product['bn'] . '货品保存失败');
                    }
                } else {
                    //更新记录
                    $save_data = [
                        'last_modified' => 0,
                    ];
                    //强制货品使用当前来源
                    if ($force) {
                        $save_data['source'] = $source;
                        $save_data['type'] = $product['type'];
                    }
                    $res = Product::UpdateProductbyIds([$product_info[0]->id], $save_data);
                    if (!$res) {
                        throw new Exception($product['bn'] . '货品更新失败');
                    }
                }
                $product_info = [];
                $save_data = [];
            }
            //事务提交
            app('db')->commit();
        } catch (Exception $e) {
            app('db')->rollBack();
            $this->setErrorMsg($e->getMessage());
            return $this->outputFormat([], 400);
        }
        $this->setErrorMsg('操作成功');
        return $this->outputFormat([]);
    }

}
