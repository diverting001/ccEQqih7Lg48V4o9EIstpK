<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\V1\Service\Order\Concurrent as Concurrent;
use App\Api\V1\Service\PreOrder\PreOrder as PreOrder;
use App\Api\Logic\Salyut\Orders as SalyutOrders;
use Illuminate\Http\Request;

class PreOrderController extends BaseController
{

    /*
     * @todo 创建预下单
     */
    public function Create(Request $request)
    {
        $order_data = $this->getContentArray($request);
        //订单号发配
        $server_concurrent = new Concurrent();
        $server_preorder = new PreOrder();
        //临时订单号
        $temp_order_id = $order_data['temp_order_id'];
        if (!empty($temp_order_id)) {
            //检查临时订单号是否可用
            if (!$server_concurrent->CherckOrderId($temp_order_id)) {
                $this->setErrorMsg('临时订单号错误');
                return $this->outputFormat([], 400);
            }

            if ($server_preorder->CherckOrderId($temp_order_id)) {
                $this->setErrorMsg('预下单信息已存在');
                return $this->outputFormat([], 103);
            }
        } else {
            $temp_order_id = $server_concurrent->GetOrderId();
        }

        //组织数据
        $create_order_data = array(
            'temp_order_id' => $temp_order_id, //订单号
            'ship_name' => $order_data['ship_name'],   //收货人姓名
            'ship_addr' => $order_data['ship_addr'],   //收货人详情地址
            'ship_zip' => $order_data['ship_zip'], //收货人邮编
            'ship_tel' => $order_data['ship_tel'], //收货人电话
            'ship_mobile' => $order_data['ship_mobile'],   //收货人手机号
            'ship_province' => $order_data['ship_province'],   //收货人所在省
            'ship_city' => $order_data['ship_city'],   //收货人所在市
            'ship_county' => $order_data['ship_county'],   //收货人所在县
            'ship_town' => $order_data['ship_town'],   //收货人所在镇
            'idcardname' => $order_data['idcardname'], //收货人证件类型 (身份证)
            'idcardno' => $order_data['idcardno'], //收货人证件号 (身份证号)
        );

        $SalyutOrders = new SalyutOrders();
        $result_data = [];
        $order_items = $order_data['items'];
        $msg = 'OK';
        $err_code = '';
        $err_data = [];
        $r = $SalyutOrders->PreOrderCreate($create_order_data, $order_items, $result_data, $msg, $err_code, $err_data);
        //保存预下单数据
        $data = $this->formatSaveData($order_data, $result_data, $err_code, $msg, $err_data);
        $server_preorder->SavePreOrder($temp_order_id, $data);
        if (false === $r) {
            $err_code = !empty($err_code) ? $err_code : 101;
            $msg = !empty($msg) ? $msg : '创建预下单失败';
            $this->setErrorMsg($msg);
            return $this->outputFormat($err_data, $err_code);
        } else {
            $err_data = [];
            foreach ($order_items as $item) {
                $product_bn = $item['product_bn'];
                if (!isset($result_data[$product_bn])) {
                    $err_data[] = $item;
                }
            }
            if (!empty($err_data)) {
                $this->setErrorMsg('创建预下单返回数据异常');
                foreach ($err_data as $row) {
                    $this->setErrorMsg($row['product_bn']);
                }
                return $this->outputFormat([], 102);
            }


            $this->setErrorMsg('创建成功');
            return $this->outputFormat(['temp_order_id' => $temp_order_id, 'product_list' => $result_data]);
        }
    }

    private function formatSaveData($order_data, $result_data, $err_code, $msg, $err_data)
    {
        return [
            'order_data' => $order_data,
            'result_data' => $result_data,
            'err_code' => $err_code,
            'msg' => $msg,
            'err_data' => $err_data,
        ];
    }

    /*
     * @todo 查询预下单
     */
    public function GetPreOrderInfo(Request $request)
    {
        $request_data = $this->getContentArray($request);
        if (!isset($request_data['temp_order_id'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }
        $temp_order_id = $request_data['temp_order_id'];
        if (!empty($temp_order_id)) {
            //检查临时订单号是否可用
            $server_preorder = new PreOrder();
            $order_info = $server_preorder->GetOrder($temp_order_id);
            if (!empty($order_info)) {
                $this->setErrorMsg('获取成功');
                return $this->outputFormat($order_info);
            }
        }
        $this->setErrorMsg('订单不存在');
        return $this->outputFormat([], 401);
    }
}
