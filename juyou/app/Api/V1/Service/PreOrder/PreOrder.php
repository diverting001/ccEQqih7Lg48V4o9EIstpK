<?php

namespace App\Api\V1\Service\PreOrder;

use App\Api\Model\PreOrder\PreOrder as MdlPreOrder;

/*
 * @todo 预下单
 */

class PreOrder
{


    /*
     * @todo 获取预下单信息
     */
    public function GetOrder($order_id)
    {
        return MdlPreOrder::GetPreOrder($order_id);
    }

    /*
     * @todo 检查订单是否存在
     */
    public function CherckOrderId($order_id)
    {
        $order_info = MdlPreOrder::GetPreOrder($order_id);
        return $order_info ? true : false;
    }

    /*
     * @todo 检查保存预下单信息
     */
    public function SavePreOrder($order_id, $data)
    {
        return MdlPreOrder::SavePreOrder($order_id, $data);
    }


}
