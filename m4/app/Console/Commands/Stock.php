<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Model\Stock\Product as Product;
use App\Api\Model\Stock\LockLog as LockLog;
use App\Api\Model\Stock\Stock as ProductStock;
use App\Api\Model\Stock\SyncLog as SyncLog;
use App\Api\Model\Stock\Branch as Branch;
use Exception;

class Stock extends Command
{
    protected $force = '';
    protected $signature = 'getstock {source} {type}';
    protected $description = '同步货品库存';


    public function handle()
    {
        set_time_limit(0);
//        while (true){
        $post_data = array();
        //获取需要更新的货品数据
        $product_list = $this->GetSyncProductData($this->argument('source'), $this->argument('type'), 500);
        if (empty($product_list)) {
            return;
        }
        //从接口获取货品库存
        $class_name = 'App\\Api\\Logic\\StockSource\\' . ucfirst(strtolower($this->argument('source')));
        if (!class_exists($class_name)) {
            throw new Exception('处理类不存在');
        }
        $class_obj = new $class_name;
        $product_stock_list = $class_obj->GetStock($product_list);
        //保存更新货品库存
        if (!empty($product_stock_list)) {
            $this->UpdateProductStock($product_stock_list);
        }
//        }
    }

    /*
     * @todo 获取需要更新的货品信息
     */
    public function GetSyncProductData($source, $type, $limit = 50)
    {
        $product_data = [];
        $product_list = Product::GetUpdateProductList($source, $type, $limit);
        //获取货品需要确认锁定
        if (!empty($product_list)) {
            foreach ($product_list as $item) {
                if($item->product_bn == 'SHOP-5D2F07B64C7E8-0') continue;
                if($item->product_bn == 'SHOP-5A3B970BE6E47-0') continue;
                if($item->product_bn == 'SHOP-5D22E94AB265E-0') continue;
                if($item->product_bn == 'SHOP-5D2F083DD2FC5-0') continue;
                if($item->product_bn == 'SHOP-5D4A708A8B038-0') continue;
                if($item->product_bn == 'SHOP-5D510ECB6F8E2-0') continue;
                if($item->product_bn == 'SHOP-5D4D26A57AF45-0') continue;
                if($item->product_bn == 'SHOP-5D4A710D29450-0') continue;
                if($item->product_bn == 'SHOP-5DCAAE1D2BF63-0') continue;
                $lock_orders = array();
                $product_data[$item->id]['product_bn'] = $item->product_bn;
                $product_data[$item->id]['id'] = $item->id;
                //获取货品未确认锁定订单
                $lock_product_orders = LockLog::GetOrderByProductBn($item->product_bn);
                if (!empty($lock_product_orders) && count($lock_product_orders) < 15000) {
                    foreach ($lock_product_orders as $product_order) {
                        $lock_orders[] = $product_order->lock_obj;
                    }
                }
                $product_data[$item->id]['order_list'] = $lock_orders;
            }
        };
        return $product_data;
    }

    //更新货品库存
    public function UpdateProductStock($product_stock_list)
    {
        if (empty($product_stock_list)) {
            return false;
        }
        $branch_info = Branch::GetDefaultBranch();
        foreach ($product_stock_list as $product) {
            //临时过滤掉腾讯商品同步
            if ($product['product_info']['bn'] == 'AEX16JMT90') {
                continue;
            }
            app('api_db')->transaction(function () use ($product, $branch_info) {
                $order_lock_count = 0;   //订单锁定库存数据
                echo $product['product_info']['bn'] . '＝＝＝＝' . "\n";
                $product_stock_info = ProductStock::GetProductInfo($product['product_info']['bn'], $branch_info->id);
                //更新货品锁定库存
                if (!empty($product['order_list'])) {
                    foreach ($product['order_list'] as $order) {
                        //获取锁定记录
                        $lock_info = LockLog::GetPudoctLockInfoByOrder($product['product_info']['bn'], 'order',
                            $order['order_bn']);
                        if (!$lock_info) {
                            throw new Exception('未找到对应库存锁定记录');
                        }
                        //订单锁定库存已使用处理
                        if (isset($order['use_count']) && !empty($order['use_count'])) {
                            //更新锁定记录
                            if (($order['use_count'] + $order['lock_count']) != $lock_info->count) {
                                $order['use_count'] = $lock_info->count;
                                $status = 5;
                            } else {
                                $status = $order['use_count'] < $lock_info->count ? 3 : 4;
                            }
                            $lock_save_data = array(
                                'amount_used' => $order['use_count'],
                                'status' => $status
                            );
                            $res = LockLog::UpdataAmountUsed($lock_info->id, $lock_info->amount_used, $lock_save_data);
                            if (!$res) {
                                throw new Exception('更新库存锁定失败');
                            }
                            //更新货品
                            $product_stock = ProductStock::GetProductInfo($lock_info->product_bn,
                                $lock_info->branch_id);
                            if (!$product_stock) {
                                throw new Exception('未找到需要更新的货品');
                            }
                            $freez = $product_stock->freez - ($order['use_count'] - $lock_info->amount_used);
                            $res = ProductStock::UpdataFreez($lock_info->product_bn, $lock_info->branch_id, $freez,
                                $product_stock->freez);
                            if (!$res) {
                                throw new Exception('更新货品冻结库存数失败');
                            }
                            //订单确认后扣除冻结库存日志
                            $log_svae_data = [
                                'product_bn' => $lock_info->product_bn,
                                'branch_id' => $lock_info->branch_id,
                                'type' => '确认之后扣除冻结',
                                'text' => json_encode(
                                    [
                                        'status' => $status,
                                        'use_count' => $order['use_count'],
                                        'old_freez' => $product_stock->freez,
                                        'new_freez' => $freez,
                                        'lock_id' => $lock_info->id,
                                        'order_id' => $lock_info->lock_obj,
                                    ]
                                )
                            ];
                            SyncLog::Save($log_svae_data);
                        }
                        //订单锁定库存依然冰结处理
                        if (isset($order['lock_count']) && !empty($order['lock_count'])) {
                            $order_lock_count += $order['lock_count'];
                        }
                    }
                }
                //货品库存数＝ocs可用库存数+ocs订单冻结库存数
                $stock = $product['stock'] + $order_lock_count;
                //更新货品库存
                $up_res = ProductStock::UpdateStock($product['product_info']['bn'], $branch_info->id, $stock);
                if ($up_res === false) {
                    throw new Exception('货品库存更新失败');
                }
                if (empty($product_stock_info) || $stock != $product_stock_info->stock) {
                    //库存同步日志
                    $log_svae_data = [
                        'product_bn' => $product['product_info']['bn'],
                        'branch_id' => $branch_info->id,
                        'type' => '同步更新货品库存',
                        'text' => json_encode(
                            [
                                'old_stock' => empty($product_stock_info) ? 0 : $product_stock_info->stock,
                                'new_stock' => $stock,
                                'order_lock_count' => $order_lock_count,
                                'ocs_stock' => $product['stock']
                            ]
                        )
                    ];
                    SyncLog::Save($log_svae_data);
                }
                //更新货品更新时间
                Product::UpdateProduct(['product_bn' => $product['product_info']['bn']], ['last_modified' => time()]);
            });
        }
    }
}
