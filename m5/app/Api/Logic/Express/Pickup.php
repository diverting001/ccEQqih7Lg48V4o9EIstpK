<?php

namespace App\Api\Logic\Express;

use App\Api\Logic\Openapi;

class Pickup
{
    private $_channel;

    public function __construct($channel) {
        $this->_channel = $channel;
    }

    /**
     * 下单
     */
    public function createOrder($order) {

        //通用请求参数
        $request = array(
            'channel' => $this->_channel,
            'order_no' => $order['order_no'],
            'sender_content' => [
                'name' => $order['sender_name'],
                'mobile' => $order['sender_mobile'],
                'address' => $order['sender_address'],
            ],
            'receiver_content' => [
                'name' => $order['receiver_name'],
                'mobile' => $order['receiver_mobile'],
                'address' => $order['receiver_address'],
            ]
        );

        $cargoes = [];
        foreach ($order['order_item'] as $orderItem) {
            $cargoes[] = [
                'name' => $orderItem['name'],
                'number' => $orderItem['number'],
                'weight' => $orderItem['weight'],
                'volume' => $orderItem['volume']
            ];
        }
        $request['cargoes'] = $cargoes;
        //调用预下单接口
        return $this->_request('/ChannelInterop/V1/Express/Pickup/createOrder', $request);
    }

    /**
     * 查询订单详情
     * @param $thirdOrderNo
     * @param $orderNo
     * @return false|mixed
     */
    public function queryOrderDetail($thirdOrderNo, $orderNo) {
        //通用请求参数
        $request = array(
            'channel' => $this->_channel,
            'third_order_no' => $thirdOrderNo,
            'order_no' => $orderNo,
        );
        //调用预下单接口
        return $this->_request('/ChannelInterop/V1/Express/Pickup/queryOrderDetail', $request);
    }

    /**
     * 查询订单费用
     * @param $thirdOrderNo
     * @param $orderNo
     * @return false|mixed
     */
    public function queryOrderFee($thirdOrderNo, $orderNo) {
        //通用请求参数
        $request = array(
            'channel' => $this->_channel,
            'third_order_no' => $thirdOrderNo,
            'order_no' => $orderNo,
        );
        //调用预下单接口
        return $this->_request('/ChannelInterop/V1/Express/Pickup/queryOrderFee', $request);
    }

    /**
     * 查询订单轨迹
     * @param $thirdOrderNo
     * @param $orderNo
     * @return false|mixed
     */
    public function queryOrderTrace($thirdOrderNo, $orderNo) {
        //通用请求参数
        $request = array(
            'channel' => $this->_channel,
            'third_order_no' => $thirdOrderNo,
            'order_no' => $orderNo,
        );
        //调用预下单接口
        return $this->_request('/ChannelInterop/V1/Express/Pickup/queryOrderTrace', $request);
    }

    /**
     * 取消订单
     * @param $thirdOrderNo
     * @param $orderNo
     * @param $reason
     * @return false|mixed
     */
    public function cancelOrder($thirdOrderNo, $orderNo, $reason = '') {
        //通用请求参数
        $request = array(
            'channel' => $this->_channel,
            'third_order_no' => $thirdOrderNo,
            'order_no' => $orderNo,
            'reason' => $reason
        );
        //调用预下单接口
        return $this->_request('/ChannelInterop/V1/Express/Pickup/cancelOrder', $request);
    }


    private function _request($path, $requestData)
    {
        if (empty($path) or empty($requestData)) {
            return false;
        }

        $openapi_logic = new Openapi();
        return  $openapi_logic->QueryV2($path, $requestData);
    }
}
