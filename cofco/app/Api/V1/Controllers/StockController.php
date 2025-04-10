<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Stock\BranchStock as BranchStock;
use App\Api\V1\Service\Stock\StockRestrict as StockRestrict;
use Illuminate\Http\Request;

class StockController extends BaseController
{
    protected $_server_stock_obj = [];
    protected $_service_branch_stock = null;
    protected $_service_stock_restricet = null;

    public function __construct()
    {
        $this->_service_branch_stock = new BranchStock();
        $this->_service_stock_restricet = new StockRestrict();
    }

    //获取货品库存列表
    function GetProudctStock(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $product_stock_list = [];   //货品库存列表
        $product_bn_bn = $content_data['product_list'];
        $product_num_list = isset($content_data['product_num_list']) ? $content_data['product_num_list'] : [];
        $channel = trim($content_data['channel']);
        $province = !isset($content_data['province']) ? '' : trim($content_data['province']);
        $city = !isset($content_data['city']) ? '' : trim($content_data['city']);
        $county = !isset($content_data['county']) ? '' : trim($content_data['county']);
        $town = !isset($content_data['town']) ? '' : trim($content_data['town']);
        $num = !isset($content_data['num']) ? 1 : trim($content_data['num']);   //检查购买数量
        $addr = !isset($content_data['addr']) ? '' : trim($content_data['addr']);
        $extend_data = !isset($content_data['extend_data']) ? '' : trim($content_data['extend_data']);
        if (empty($product_bn_bn) || empty($channel)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        if (empty($this->_server_stock_obj)) {
            $this->setErrorMsg('未找到可处理对象');
            return $this->outputFormat([], 400);
        }

        foreach ($this->_server_stock_obj as $server_obj) {
            //获取服务库存
            $product_service_stock_list = $server_obj->GetStock($product_bn_bn, [
                'channel' => $channel,
                'province' => $province,
                'city' => $city,
                'county' => $county,
                'town' => $town,
                'addr' => $addr,
                'extend_data' => $extend_data
            ], $product_num_list);
            //组织数据
            foreach ($product_bn_bn as $bn) {
                $main_stock = 0;
                $stock = 0;
                if (isset($product_service_stock_list[$bn])) {
                    if (isset($product_stock_list[$bn])) {
                        $main_stock = min($product_stock_list[$bn]['main_stock'],
                            $product_service_stock_list[$bn]['main_stock']);
                        $stock = min($product_stock_list[$bn]['stock'], $product_service_stock_list[$bn]['stock']);
                    } else {
                        $main_stock = $product_service_stock_list[$bn]['main_stock'];
                        $stock = $product_service_stock_list[$bn]['stock'];
                    }
                }
                $stock_state = $stock >= $num ? 1 : 2;
                $product_stock_list[$bn] = [
                    'bn' => $bn,
                    'main_stock' => $main_stock,
                    'stock' => $stock,
                    'stock_state' => isset($product_service_stock_list[$bn]['stock_state']) && $product_service_stock_list[$bn]['stock_state'] ? $product_service_stock_list[$bn]['stock_state'] : $stock_state,
                ];
            }
        }
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($product_stock_list);
    }

    //货品库存锁定
    public function Lock(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $response_products = array();
        $products_bn = $content_data['product_list'];
        $area = $content_data['area'];  //收货地址
        $channel = trim($content_data['channel']);
        $order_id = trim($content_data['order_id']);
        if (empty($products_bn) || empty($channel) || empty($order_id)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        //开启事务
        app('db')->beginTransaction();
        //冻结活动限制库存
        $restricet_res = $this->_service_stock_restricet->Lock($products_bn, ['channel' => $channel],
            $response_products);
        //冻结虚拟创建货品库存
        $branch_stock_res = $this->_service_branch_stock->Lock($products_bn,
            ['channel' => $channel, 'lock_type' => 'order', 'lock_obj' => $order_id, 'area' => $area],
            $response_products);
        //操作确认
        if ($restricet_res && $branch_stock_res) {
            app('db')->commit();
            $this->setErrorMsg('操作成功');
            return $this->outputFormat(['ignore_product' => (array)$response_products['ignore_product']]);
        } else {
            app('db')->rollBack();
            $this->setErrorMsg($this->_service_branch_stock->GetErrorMsg());
            $log_data = array(
                'bn' => json_encode($products_bn),
                'sparam2' => $order_id,
                'sparam3' => json_encode($content_data),
                'sparam1' => json_encode($response_products['fail_product']),
                'remark' => $this->_service_branch_stock->GetErrorMsg()
            );
            \Neigou\Logger::General('stock_lock_fail', $log_data);
            if (empty($response_products['fail_product'])) {
                return $this->outputFormat((array)$response_products['fail_product'], 400);
            } else {
                return $this->outputFormat((array)$response_products['fail_product'], 401);
            }
        }
    }


    //取消订单库存锁定
    public function CancelLock(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $channel = trim($content_data['channel']);
        $order_id = trim($content_data['order_id']);
        if (empty($channel) || empty($order_id)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        app('db')->beginTransaction();
        //取消虚拟仓库锁定
        $branch_stock_res = $this->_service_branch_stock->CancelLock($channel, 'order', $order_id);
        //取消库存限制锁定
        $restricet_stock_res = $this->_service_stock_restricet->CancelLock($channel, 'order', $order_id);
        if ($branch_stock_res && $restricet_stock_res) {
            app('db')->commit();
            $msg = $this->_service_branch_stock->GetErrorMsg() ? $this->_service_branch_stock->GetErrorMsg() : '操作成功';
            $this->setErrorMsg($msg);
            return $this->outputFormat([]);
        } else {
            $log_data = array(
                'bn' => $order_id,
                'remark' => $this->_service_branch_stock->GetErrorMsg(),
            );
            \Neigou\Logger::General('stock_cancel_fail', $log_data);
            app('db')->rollBack();
            $this->setErrorMsg($this->_service_branch_stock->GetErrorMsg());
            return $this->outputFormat([], 400);
        }
    }


    /*=========================库存零时锁定=============================*/
    //货品库存锁定
    public function TempLock(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $response_products = array();
        $products_bn = $content_data['product_list'];
        $area = $content_data['area'];  //收货地址
        $channel = trim($content_data['channel']);
        $lock_obj = trim($content_data['lock_obj']);
        $lock_type = trim($content_data['lock_type']);
        if (empty($products_bn) || empty($channel) || empty($lock_obj) || empty($lock_type)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        //开启事务
        app('db')->beginTransaction();
        //冻结活动限制库存
        $restricet_res = $this->_service_stock_restricet->Lock($products_bn, ['channel' => $channel],
            $response_products);
        //冻结虚拟创建货品库存
        $branch_stock_res = $this->_service_branch_stock->Lock($products_bn,
            ['channel' => $channel, 'lock_type' => $lock_type, 'lock_obj' => $lock_obj, 'area' => $area],
            $response_products);
        //操作确认
        if ($restricet_res && $branch_stock_res) {
            app('db')->commit();
            $this->setErrorMsg('操作成功');
            return $this->outputFormat(['ignore_product' => (array)$response_products['ignore_product']]);
        } else {
            app('db')->rollBack();
            $this->setErrorMsg($this->_service_branch_stock->GetErrorMsg());
            $log_data = array(
                'bn' => json_encode($products_bn),
                'sparam2' => $lock_obj,
                'sparam3' => $lock_type,
                'sparam4' => json_encode($content_data),
                'sparam1' => json_encode($response_products['fail_product']),
                'remark' => $this->_service_branch_stock->GetErrorMsg()
            );
            \Neigou\Logger::General('stock_templock_fail', $log_data);
            if (empty($response_products['fail_product'])) {
                return $this->outputFormat((array)$response_products['fail_product'], 400);
            } else {
                return $this->outputFormat((array)$response_products['fail_product'], 401);
            }
        }
    }


    //取消订单库存锁定
    public function CancelTempLock(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $channel = trim($content_data['channel']);
        $lock_type = trim($content_data['lock_type']);
        $lock_obj = trim($content_data['lock_obj']);
        if (empty($channel) || empty($lock_obj) || empty($lock_type)) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        app('db')->beginTransaction();
        //取消虚拟仓库锁定
        $branch_stock_res = $this->_service_branch_stock->CancelLock($channel, $lock_type, $lock_obj);
        //取消库存限制锁定
        $restricet_stock_res = $this->_service_stock_restricet->CancelLock($channel, $lock_type, $lock_obj);
        if ($branch_stock_res && $restricet_stock_res) {
            app('db')->commit();
            $msg = $this->_service_branch_stock->GetErrorMsg() ? $this->_service_branch_stock->GetErrorMsg() : '操作成功';
            $this->setErrorMsg($msg);
            return $this->outputFormat([]);
        } else {
            $log_data = array(
                'bn' => $lock_obj,
                'sparam1' => $lock_type,
                'remark' => $this->_service_branch_stock->GetErrorMsg(),
            );
            \Neigou\Logger::General('stock_cancel_fail', $log_data);
            app('db')->rollBack();
            $this->setErrorMsg($this->_service_branch_stock->GetErrorMsg());
            return $this->outputFormat([], 400);
        }
    }

    /*
     * @todo 暂时订单转换
     * change_data = array(
     *  lock_type => 'xxxxx',
     *  lock_obj => 'xxxx',
     *  to_lock_obj => 'xxxxx',
     *  product_list => array(
     *      array(
     *          'bn'=>'XXXXX',
     *          'count'=>10
     *      )
     *  )
     * )
     */
    public function TempLockChange(Request $request)
    {
        $content_data = $this->getContentArray($request);
        $change_data = $content_data['change_data'];
        $channel = $content_data['channel'];
        if (empty($channel) || empty($change_data)) {
            $this->outputFormat('参数错误');
            return $this->outputFormat([], 400);
        }
        if ($change_data['lock_type'] == 'order') {
            $this->outputFormat('锁定无须转换');
            return $this->outputFormat([], 400);
        }
        app('db')->beginTransaction();
        $change_data['to_lock_type'] = empty($change_data['to_lock_type']) ? 'order' : $change_data['to_lock_type'];
        $branch_stock_res = $this->_service_branch_stock->LockChange($channel, $change_data,
            $change_data['to_lock_obj'], $change_data['to_lock_type']);
        if (!$branch_stock_res) {
            app('db')->rollBack();
            $this->setErrorMsg($this->_service_branch_stock->GetErrorMsg());
            return $this->outputFormat([], 400);
        } else {
            app('db')->commit();
            $this->setErrorMsg('操作成功');
            return $this->outputFormat([]);
        }
    }

    /*
     * @todo 库存处理对象
     */
    public function SetStockObj(\App\Api\V1\Service\Stock\Stock $obj)
    {
        if (!isset($this->_server_stock_obj[get_class($obj)])) {
            $this->_server_stock_obj[get_class($obj)] = $obj;
        }
    }
}
