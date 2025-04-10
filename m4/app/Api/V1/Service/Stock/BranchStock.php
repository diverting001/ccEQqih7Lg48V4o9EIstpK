<?php

namespace App\Api\V1\Service\Stock;

use App\Api\V1\Service\Stock\Stock as StockService;
use App\Api\Model\Stock\Stock as ProductStock;
use App\Api\Model\Stock\Product as Product;
use App\Api\Model\Stock\Branch as Branch;
use App\Api\Model\Stock\LockLog as LockLog;
use App\Api\Logic\Salyut\Stock as SalyutStock;

class BranchStock extends StockService
{

    /*
     * @todo 货品库存锁定
     * @product_list    锁定货品列表
     * @channel 渠道
     * @area 地址
     * @fail_product 锁定失败货品列表
     * @ignore_product 忽略锁定货品列表（第三方平台对接 JD/YHD/YGSX）
     */
    public function Lock($lock_product_list, $parameter, &$response_products)
    {
        $lock_status = true;
        $channel = $parameter['channel'];
        $lock_obj = $parameter['lock_obj'];
        $lock_type = $parameter['lock_type'];
        $area = $parameter['area'];
        if (empty($lock_product_list) || empty($channel) || empty($lock_obj) || empty($lock_type)) {
            $this->_error_msg = '参数错误';
            return false;
        }
        //检查锁定记录
        $where = [
            ['channel', '=', $channel],
            ['lock_type', '=', $lock_type],
            ['lock_obj', '=', $lock_obj],
            ['status', '=', 1]
        ];
        $lock_list = LockLog::GetLockLogList($where);
        if (!empty($lock_list)) {
            $this->_error_msg = '订单已锁定，请忽重复锁定';
            return false;
        }

        //选择货品可使用虚拟仓库
        $brand_info = Branch::SelectBranch($channel);
        if (empty($brand_info)) {
            $brand_info = Branch::GetDefaultBranch();
        }
        //所有货品列表
        $products_bn = array_column($lock_product_list, 'product_bn');
        $product_list = Product::GetProductList($products_bn);
        foreach ((array)$product_list as $item) {
            $product_list[$item->product_bn] = $item;
        }
        //对货品库存进行锁定
        foreach ($lock_product_list as $product) {
            $stock_res = $lock_log_res = true;
            if (isset($product_list[$product['product_bn']])) {
                //第三方平台对接货品不做库存锁定
                if ($product_list[$product['product_bn']]->type == 3) {
                    $bn_array = explode('-', $product['product_bn']);
                    if ($bn_array[0] == 'JD' && in_array($area['city'],
                            array('克孜勒苏州', '和田地区', '喀什地区', '图木舒克市', '巴音郭楞州', '阿克苏地区', '阿拉尔市'))) {
                        $stock_res = false;
                    } else {
                        $response_products['ignore_product'][$product['product_bn']] = $product['product_bn'];
                    }
                } else {
                    //严选新疆地区无库存
                    $bn_array = explode('-', $product['product_bn']);
                    if ($area['province'] == '新疆' && ($bn_array[0] == 'YX' OR $bn_array[0] == 'YXKA' OR $bn_array[0] == 'YXSRBT')) {
                        $stock_res = false;
                    } else {
                        $stock_res = ProductStock::Lock($product['product_bn'], $brand_info->id, $product['count']);
                        //非默认仓库锁定失败后，尝试锁定默认仓库
                        if (!$stock_res && $brand_info->channel != 'DEFAULT') {
                            $brand_info = Branch::GetDefaultBranch();
                            $stock_res = ProductStock::Lock($product['product_bn'], $brand_info->id, $product['count']);
                        }
                        //保存锁定记录
                        if ($stock_res) {
                            $lokc_log_save_data = [
                                'product_bn' => $product['product_bn'],
                                'channel' => $channel,
                                'branch_id' => $brand_info->id,
                                'lock_type' => $lock_type,
                                'lock_obj' => $lock_obj,
                                'count' => $product['count'],
                                'last_modified' => time(),
                                'create_time' => time()
                            ];
                            $lock_log_res = LockLog::SaveLockLog($lokc_log_save_data);
                        }
                    }
                }
            } else {
                $stock_res = false;
            }
            $lock_status = $lock_status && $stock_res && $lock_log_res;
            //记录锁定失败的货品
            if (!$stock_res || !$lock_log_res) {
                $response_products['fail_product'][$product['product_bn']] = $product['product_bn'];
            }
        }
        if (!$lock_status) {
            $this->_error_msg = '库存不足';
        }
        return $lock_status;
    }


    public function CancelLock($channel, $lock_type, $lock_obj)
    {
        if (empty($channel) || empty($lock_obj)) {
            $this->_error_msg = '参数错误';
            return false;
        }
        $where = [
            ['channel', '=', $channel],
            ['lock_type', '=', $lock_type],
            ['lock_obj', '=', $lock_obj],
//            ['status'   ,'=', 1]
        ];
        $lock_list = LockLog::GetLockLogList($where);
        if (empty($lock_list)) {
            $this->_error_msg = '未找到订单锁定记录';
            return true;
        }
        foreach ($lock_list as $item) {
            if ($item->status != 1) {
                $this->_error_msg = '订单不可以解除锁定';
                return false;
            }
            //更新记录状态
            $up_res = LockLog::UpdateStatus($item->id, 2, $item->status);
            if (!$up_res) {
                $this->_error_msg = $item->product_bn . '释放失败';
                return false;
            }
            //锁定库存返还
            if (!$this->Restore($item)) {
                return false;
            }
        }
        return true;
    }

    /*
     * @todo 获取货品库存
     */
    public function GetStock($product_list, $filter, $product_num_list = array())
    {
        //货品库存列表
        $product_stock_list = [];
        if (empty($product_list)) {
            return $product_stock_list;
        }
        //获取货品主库存
        $main_product_stock_list = ProductStock::GetProductStockByChannel($product_list, $filter['channel']);
        foreach ($product_list as $bn) {
            $main_stock = isset($main_product_stock_list[$bn]) ? intval($main_product_stock_list[$bn]['stock']) : 0;
            //严选新疆地区无库存
            $bn_array = explode('-', $bn);
            if ($filter['province'] == '新疆' && ($bn_array[0] == 'YX' OR $bn_array[0] == 'YXKA')) {
                $main_stock = 0;
            } else {
                if ($bn_array[0] == 'JD' && in_array($filter['city'],
                        array('克孜勒苏州', '和田地区', '喀什地区', '图木舒克市', '巴音郭楞州', '阿克苏地区', '阿拉尔市'))) {
                    $main_stock = 0;
                }
            }
            //如果有省份限制查询分省库存
            $product_stock_list[$bn] = [
                'bn' => $bn,
                'main_stock' => $main_stock,
                'stock' => $main_stock,
                'stock_state' => 2,
            ];
        }
        //使用salyut数据替换分省库存
        if (!empty($filter['province'])) {
            //拆分出salyut货品bn
            $salyut_product_bn = $this->GetSalyutProductBn($product_list);
            if (!empty($salyut_product_bn)) {
                $salyut_proudct_stock_list = SalyutStock::ProductStock($salyut_product_bn, $filter, $product_num_list);
                foreach ($salyut_product_bn as $bn) {
                    $stock = isset($salyut_proudct_stock_list[$bn]) ? intval($salyut_proudct_stock_list[$bn]['stock']) : 0;
                    $product_stock_list[$bn]['stock'] = $stock;
                    //检查是否可购买状态
                    if (isset($salyut_proudct_stock_list[$bn]) && !empty($salyut_proudct_stock_list[$bn]['stock_state'])) {
                        $product_stock_list[$bn]['stock_state'] = $salyut_proudct_stock_list[$bn]['stock_state'];
                    }
                }
            }
        }
        return $product_stock_list;
    }

    /*
     * @todo 锁定记录转换
     */
    public function LockChange($channel, $change_data, $new_lock_obj, $new_lock_type)
    {
        if (empty($channel) || empty($change_data) || empty($new_lock_obj) || empty($new_lock_type)) {
            $this->_error_msg = '参数错误';
            return false;
        }
        //第三方无法锁库存的商品
        $ignore_product = [];
        $product_list = Product::GetProductList(array_column($change_data['product_list'], 'product_bn'));
        foreach ((array)$product_list as $item) {
            if ($item->type == 3) {
                $ignore_product[$item->product_bn] = $item->product_bn;
            }
        }
        if (count($change_data['product_list']) == count($ignore_product)) {
            $this->_error_msg = '商品为第三方无需转换';
            return true;
        }
        //检查订单是否锁定记录
        $where = [
            ['channel', '=', $channel],
            ['lock_type', '=', $change_data['lock_type']],
            ['lock_obj', '=', $change_data['lock_obj']],
            ['status', '=', 1]
        ];
        $lock_list = LockLog::GetLockLogList($where);
        if (empty($lock_list)) {
            $this->_error_msg = '锁定记录不存在';
            return false;
        }
        $lock_list_new = [];
        foreach ($lock_list as $v) {
            $lock_list_new[$v->product_bn] = $v;
        }
        //进行库存转换
        foreach ($change_data['product_list'] as $product) {
            if ($ignore_product[$product['product_bn']]) {
                continue;
            }
            if (!isset($lock_list_new[$product['product_bn']])) {
                $this->_error_msg = '转换商品不存在';
                return false;
            }
            if ($lock_list_new[$product['product_bn']]->count < $product['count']) {
                $this->_error_msg = '转换商品数据不足';
                return false;
            }
            //保存新的锁定记录
            $lokc_log_save_data = [
                'product_bn' => $product['product_bn'],
                'channel' => $channel,
                'branch_id' => $lock_list_new[$product['product_bn']]->branch_id,
                'lock_type' => $new_lock_type,
                'lock_obj' => $new_lock_obj,
                'create_source' => $lock_list_new[$product['product_bn']]->id,
                'count' => $product['count'],
                'last_modified' => time(),
                'create_time' => time()
            ];
            $lock_log_res = LockLog::SaveLockLog($lokc_log_save_data);
            if (!$lock_log_res) {
                $this->_error_msg = '商品转换失败';
                return false;
            }
            //更新原有记录
            $save_data = [
                'count' => $lock_list_new[$product['product_bn']]->count - $product['count'],
                'last_modified' => time(),
            ];
            $save_data_where = [
                'id' => $lock_list_new[$product['product_bn']]->id,
                'count' => $lock_list_new[$product['product_bn']]->count,
                'status' => $lock_list_new[$product['product_bn']]->status,
            ];
            $lock_log_res = LockLog::Updata($save_data_where, $save_data);
            if (!$lock_log_res) {
                $this->_error_msg = '原商品转换失败';
                return false;
            }
        }
        return true;
    }


    //拆分货品查询库存地址
    private function GetSalyutProductBn(array $product_bn)
    {
        $salyut_product_bn = [];
        if (empty($product_bn)) {
            return $salyut_product_bn;
        }
        $product_list = Product::GetProductList($product_bn);
        if (empty($product_list)) {
            return $salyut_product_bn;
        }
        foreach ($product_list as $product) {
            if ($product->type == 3) {
                $salyut_product_bn[] = $product->product_bn;
            }
        }
        return $salyut_product_bn;
    }


    /*
     * @todo 锁定库存返还
     */
    private function Restore($lock_log_info)
    {
        if (empty($lock_log_info)) {
            return false;
        }
        if (!empty($lock_log_info->create_source)) {
            $res = $this->TempLockRestore($lock_log_info);
        } else {
            $res = $this->ProductRestore($lock_log_info);
        }
        return $res;
    }

    /*
     * @todo 返还商品库存
     */
    private function ProductRestore($lock_log_info)
    {
        if (empty($lock_log_info)) {
            return false;
        }
        //更新货品冻结数
        $product_stock_info = ProductStock::GetProductInfo($lock_log_info->product_bn, $lock_log_info->branch_id);
        if (empty($product_stock_info)) {
            $this->_error_msg = $lock_log_info->product_bn . '货品不存在';
            return false;
        }
        if ($product_stock_info->freez < $lock_log_info->count) {
            $this->_error_msg = $lock_log_info->product_bn . '取消库存数大于冻结库存数';
            return false;
        }
        $freez = $product_stock_info->freez - $lock_log_info->count;
        $res = ProductStock::UpdataFreez($lock_log_info->product_bn, $lock_log_info->branch_id, $freez,
            $product_stock_info->freez);
        if (!$res) {
            $this->_error_msg = $lock_log_info->product_bn . '货品冻结释放错误';
            return false;
        }
        return true;
    }

    /*
     * @todo 返回临时锁定
     */
    private function TempLockRestore($lock_log_info)
    {
        if (empty($lock_log_info)) {
            return false;
        }
        $temp_lock_info = LockLog::GetLockInfoById($lock_log_info->create_source);
        if (empty($temp_lock_info)) {
            $this->_error_msg = $lock_log_info->product_bn . '锁定业务不存在';
            return false;
        }
        if ($temp_lock_info->status != 1) {
            $this->_error_msg = $lock_log_info->product_bn . '锁定业务已失效';
            return false;
        }
        //返回给临时锁定
        $where = [
            'id' => $temp_lock_info->id,
            'status' => $temp_lock_info->status,
            'count' => $temp_lock_info->count,
        ];
        $count = $temp_lock_info->count + $lock_log_info->count;
        $save_data = array(
            'count' => $count,
            'last_modified' => time()
        );
        $up_res = LockLog::Updata($where, $save_data);
        return $up_res;
    }
}
