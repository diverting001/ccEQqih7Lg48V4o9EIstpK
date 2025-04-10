<?php

namespace App\Api\V2\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Service;
use App\Api\V2\Service\Calculate\Calculate;
use Illuminate\Http\Request;

/**
 * Description of CalculateController
 *
 * @author zhaolong
 */
class CalculateController extends BaseController
{

    public function priceCalculate(Request $request)
    {
        $params = $this->getContentArray($request);

        \Neigou\Logger::General('calculate-v2-log', array('action' => 'param', 'sparam1' => json_encode($params)));

        $temp_order_id = $params['temp_order_id'];
        $memberId = $params['member_id'];
        $companyId = $params['company_id'];
        $goodsList = $params['goods_list'];
        $assetsList = $params['assets_list'];
        $delivery = $params['delivery'];
        $extendData = $params['extend_data'];
        $memberInfo = [
            "member_id" => $memberId,
            "company_id" => $companyId,
        ];

        if (!$memberId || !$companyId || !is_array($goodsList) || count($goodsList) <= 0) {
            \Neigou\Logger::General('calculate-v2-log',
                array('action' => 'param-error', 'reason' => 'invalid_params', 'sparam1' => json_encode($params)));
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 500);
        }


        $calculate = new Calculate();
        $res = $calculate->run($memberInfo, $goodsList, $assetsList, $delivery, $extendData);

        \Neigou\Logger::General('calculate-v2-log',
            array('action' => 'return', 'sparam1' => json_encode($params), 'sparam2' => json_encode($res)));

        if ($res['status']) {
            //处理成功，保存计算日志
            if (strlen($temp_order_id) > 0) {
                $log_data = array(
                    'version' => 'V2',
                    'member_info' => $memberInfo,
                    'goods_list' => $goodsList,
                    'assets_list' => $assetsList,
                    'delivery' => $delivery,
                    'extend_data' => $extendData,
                    'calculate_result_data' => $res['data'],
                );
                $calculate->saveCalculateLog($temp_order_id, $log_data);
            }
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($res['data']);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat([], 501);
        }
    }

    public function getLog(Request $request)
    {
        $params = $this->getContentArray($request);

        $order_id = $params['order_id'];
        $calculate = new Calculate();
        $calcualte_log = $calculate->getCalculateLogByMainOrderId($order_id);
        if (empty($calcualte_log)) {
            $this->setErrorMsg('获取结果失败');
            return $this->outputFormat([], 401);
        }
        // 新老数据兼容
        $calcualte_data = json_decode($calcualte_log->data  ,true);
        if($calcualte_data['version'] != 'V6') {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($calcualte_data);
        }
        $serviceObj = new Service() ;
        $result =  $serviceObj->ServiceCall("calculatev2_get" ,['order_id' => $order_id]) ;

        if($result['error_code'] != "SUCCESS" || empty($result['data'])) {
            $this->setErrorMsg('获取计算服务结果失败');
            return $this->outputFormat([], 402);
        }
        $new_calcualte = $result['data'] ;
        $goods_list = $new_calcualte['goods_list'] ;
        $dict = [
            'price.goods_amount'  => 'cost_item' ,
            'price.freight_amount' => 'cost_freight' ,
            'price.taxfees_amount' => 'cost_tax' ,
            'discount.promotion' => 'promotion' ,
            'discount.gift_promotion' => 'gift_promotion' ,
            'discount.order_promotion' => 'order_promotion' ,
            'discount.voucher' => 'voucher' ,
            'discount.freeshipping' => 'freeshipping' ,
            'discount.dutyfree' => 'dutyfree' ,
        ] ;

        foreach ($dict as $oldKey=>$newKey) {
            list($a1,$a2)  =  explode('.'  ,$oldKey) ;
            $calcualte_data['calculate_result_data'][$a1][$a2] = $new_calcualte[$newKey] ;
       }
        $product_list = [] ;
        $goods_dict = [
            'split_price.cash_amount' => 'cash_amount' ,
            'split_price.promotion_discount' => 'promotion_discount' ,
            'split_price.voucher_discount' => 'voucher_discount' ,
            'split_price.order_promotion_discount' => 'promotion_discount' ,
            'taxfees.cost_tax' => 'cost_tax' ,
            'taxfees.dutyfree_discount' => 'dutyfree_discount' ,
            'freight.cost_freight' => 'cost_freight' ,
            'freight.freight_discount' => 'freight_discount' ,
        ] ;
       foreach ($goods_list as $item) {
           $bn = $item['product_bn'] ;
           $product_list[$bn] = $item ;
           foreach ($goods_dict as $oldKey=>$newkey) {
               list($a1,$a2)  =  explode('.'  ,$oldKey) ;
               $product_list[$bn][$a1][$a2] = $item[$newkey] ;
           }
       }
      $calcualte_data['calculate_result_data']['product_list'] = $product_list ;
      $this->setErrorMsg('请求成功');
      return $this->outputFormat($calcualte_data);
    }

}
