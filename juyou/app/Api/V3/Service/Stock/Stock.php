<?php

namespace App\Api\V3\Service\Stock;

abstract class Stock
{
    protected $_error_msg = '';


    /*
     * @tood 库存锁定
     * $parameter   锁定货品参数
     * $response_products   锁定结果
     */
    abstract public function Lock($lock_product_list, $parameter, &$response_products);

    /*
     * @todo 库存锁定取消
     * $channel 渠道
     * $lock_type 冻结类型
     * $lock_obj 冻结对象
     */
    abstract public function CancelLock($channel, $lock_type, $lock_obj);

    /*
     * @todo 获取货品库存
     * @$product_list 货品bn列表
     * @$filter 过滤数据
     * @return [
     *  bn  货品bn
     *  stock 货品库存  999999 为限制
     *  main_stock 货品总库存 999999 为限制
     * ]
     */
    abstract public function GetStock($product_list, $filter);

    public function GetErrorMsg()
    {
        return $this->_error_msg;
    }

}
