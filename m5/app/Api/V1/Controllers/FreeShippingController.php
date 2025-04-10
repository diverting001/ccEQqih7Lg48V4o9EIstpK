<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 17:33
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Voucher\FreeShippingCouponModel;
use Illuminate\Http\Request;

class FreeShippingController extends BaseController
{

    public function createMemberCoupon(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['coupon_name']) ||
            !isset($createParams['member_id']) ||
            !isset($createParams['company_id']) ||
            !isset($createParams['rule_id']) ||
            !isset($createParams['valid_time'])) {
            \Neigou\Logger::General(
                'Voucher.FreeShipping.MemberCoupon.Create',
                array(
                    'action' => 'FreeShippingController/createMemberCoupon',
                    'reason' => 'invalid_params',
                    'data' => $createParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $createResult = $FreeShippingMdl->createMemberCoupon($createParams['member_id'], $createParams['company_id'],
            $createParams['coupon_name'], $createParams['valid_time'], $createParams['op_name'],
            $createParams['rule_id'], $createParams['rule_name'],$createParams['money'],$createParams['start_time'], $err_code, $err_msg,$createParams['message_channel']);
        \Neigou\Logger::General(
            'Voucher.FreeShipping.MemberCoupon.Create',
            array(
                'action' => 'FreeShippingController/createMemberCoupon',
                'data' => $createParams,
                'result' => $createResult
            )
        );
        if (!empty($createResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createResult);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    public function createOrderForCoupon(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['coupon_id']) ||
            !isset($createParams['member_id']) ||
            !isset($createParams['order_id']) ||
            !isset($createParams['filter_data'])) {
            \Neigou\Logger::General(
                'Voucher.FreeShipping.createOrderForCoupon',
                array(
                    'action' => 'FreeShippingController/createOrderForCoupon',
                    'reason' => 'invalid_params',
                    'data' => $createParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $err_code = 0;
        $err_msg = "";
        // 公司ID
        $companyId = isset($createParams['company_id']) ? $createParams['company_id'] : null;
        $FreeShippingMdl = new FreeShippingCouponModel();
        $createOrderResult = $FreeShippingMdl->createOrderForCoupon($createParams['coupon_id'],
            $createParams['order_id'],
            $createParams['member_id'], $companyId, $createParams['filter_data'], $err_code, $err_msg);
        \Neigou\Logger::General(
            'Voucher.FreeShipping.createOrderForCoupon',
            array(
                'action' => 'FreeShippingController/createOrderForCoupon',
                'data' => $createParams,
                'result' => $createOrderResult
            )
        );
        if (intval($createOrderResult) > 0) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createOrderResult);
        } else {
            $this->setErrorMsg($createOrderResult);
            return $this->outputFormat(null, 404);
        }
    }

    public function cancelOrderForCoupon(Request $request)
    {
        $cancelParams = $this->getContentArray($request);
        if (!isset($cancelParams['order_id'])) {
            \Neigou\Logger::General(
                'Voucher.FreeShipping.cancelOrderForCoupon',
                array(
                    'action' => 'FreeShippingController/cancelOrderForCoupon',
                    'reason' => 'invalid_params',
                    'data' => $cancelParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 10002);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $cancelOrderResult = $FreeShippingMdl->cancelOrderForCoupon($cancelParams['order_id'], $err_code, $err_msg);
        \Neigou\Logger::General(
            'Voucher.FreeShipping.cancelOrderForCoupon',
            array(
                'action' => 'FreeShippingController/cancelOrderForCoupon',
                'data' => $cancelParams,
                'result' => $cancelOrderResult
            )
        );
        if (!empty($cancelOrderResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($cancelOrderResult);
//            return $this->success($cancelOrderResult);
        } else {
            $this->setErrorMsg($err_msg);
            return $this->outputFormat(null, 404);
//            return $this->error($err_code, $err_msg);
        }
    }

    public function finishOrderForCoupon(Request $request)
    {
        $finishParams = $this->getContentArray($request);
        if (!isset($finishParams['order_id'])) {
            \Neigou\Logger::General(
                'Voucher.FreeShipping.finishOrderForCoupon',
                array(
                    'action' => 'FreeShippingController/finishOrderForCoupon',
                    'reason' => 'invalid_params',
                    'data' => $finishParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $finishOrderResult = $FreeShippingMdl->finishOrderForCoupon($finishParams['order_id'], $err_code, $err_msg);
        \Neigou\Logger::General(
            'Voucher.FreeShipping.finishOrderForCoupon',
            array(
                'action' => 'FreeShippingController/finishOrderForCoupon',
                'data' => $finishParams,
                'result' => $finishOrderResult
            )
        );
        if (!empty($finishOrderResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($finishOrderResult);
        } else {
            $this->setErrorMsg($err_msg);
            return $this->outputFormat(null, 404);
        }
    }

    public function queryOrderCoupon(Request $request)
    {
        $queryParams = $this->getContentArray($request);
        if (!isset($queryParams['order_id'])) {
            \Neigou\Logger::General(
                'Voucher.FreeShipping.GetOrderForCoupon',
                array(
                    'action' => 'FreeShippingController/queryOrderCoupon',
                    'reason' => 'invalid_params',
                    'data' => $queryParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $queryOrderResult = $FreeShippingMdl->queryOrderCoupon($queryParams['order_id'], $err_code, $err_msg);
        \Neigou\Logger::General(
            'Voucher.FreeShipping.GetOrderForCoupon',
            array(
                'action' => 'FreeShippingController/queryOrderCoupon',
                'data' => $queryParams,
                'result' => $queryOrderResult
            )
        );
        if (!empty($queryOrderResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($queryOrderResult);
        } else {
            $this->setErrorMsg($err_msg);
            return $this->outputFormat(null, 404);
        }
    }

    public function queryMemberCoupon(Request $request)
    {
        $queryParams = $this->getContentArray($request);
        if (!isset($queryParams['member_id'])) {
            \Neigou\Logger::Debug(
                'Voucher.Freeshipping.MemberCoupon.Get',
                array(
                    'action' => 'FreeShippingController/queryMemberCoupon',
                    'data' => $queryParams,
                    'reason' => 'invalid_params'
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $currentTime = time();
        $where = $limit = '';
        if (isset($queryParams['coupon_type'])) {
            switch ($queryParams['coupon_type']) {
                case "valid":
                    $where = "valid_time >=$currentTime and status=0";
                    break;
                case "used":
                    $where = "(status=1 or status=2)";
                    break;
                case "expired":
                    $where = "valid_time < $currentTime and status=0";
                    break;
                default:
                    break;
            }
        }
        if ((isset($queryParams['offset']) && is_numeric($queryParams['offset'])) &&
            (isset($queryParams['length']) && is_numeric($queryParams['length']))) {
            $limit = "{$queryParams['offset']},{$queryParams['length']}";
        }

        // 增加公司查询
        if ($queryParams['company_id']){
            if ($where){
                $where.= ' and company_id='.$queryParams['company_id'];
            }else{
                $where= 'company_id='.$queryParams['company_id'];
            }
        }

        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $queryMemberCouponResult = $FreeShippingMdl->queryMemberCoupon($queryParams['member_id'], $where, $limit,
            $err_code, $err_msg);
        \Neigou\Logger::Debug(
            'Voucher.Freeshipping.MemberCoupon.Get',
            array(
                'action' => 'FreeShippingController/queryMemberCoupon',
                'data' => $queryParams,
                'result' => $queryMemberCouponResult
            )
        );
        if (!empty($queryMemberCouponResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($queryMemberCouponResult);
        } else {
            $this->setErrorMsg($err_msg);
            return $this->outputFormat(null, $err_code);
        }
    }

    public function queryMemberCouponWithRule(Request $request)
    {
        $queryParams = $this->getContentArray($request);
        if (!isset($queryParams['member_id']) ||
            !isset($queryParams['filter_data'])) {
            \Neigou\Logger::General(
                'Voucher.Freeshipping.MemberCoupon.GetWithRule',
                array(
                    'action' => 'FreeShippingController/queryMemberCouponWithRule',
                    'data' => $queryParams,
                    'reason' => 'invalid_params'
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $queryMemberCouponResult = $FreeShippingMdl->queryMemberCouponWithRule(
            $queryParams['member_id'],
            $queryParams['company_id'],
            $queryParams['filter_data'],
            $err_code,
            $err_msg
        );
        \Neigou\Logger::General(
            'Voucher.Freeshipping.MemberCoupon.GetWithRule',
            array(
                'action' => 'FreeShippingController/queryMemberCouponWithRule',
                'data' => $queryParams,
                'result' => $queryMemberCouponResult
            )
        );
        if ($queryMemberCouponResult != false) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($queryMemberCouponResult);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    public function queryCouponWithRule(Request $request)
    {
        $queryParams = $this->getContentArray($request);
        if (!isset($queryParams['member_id']) ||
            !isset($queryParams['coupon_id']) ||
            !isset($queryParams['company_id']) ||
            !isset($queryParams['filter_data'])) {
            \Neigou\Logger::General(
                'Voucher.Freeshipping.GetCouponWithRule',
                array(
                    'action' => 'FreeShippingController/queryCouponWithRule',
                    'data' => $queryParams,
                    'reason' => 'invalid_params'
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new FreeShippingCouponModel();
        $queryCouponResult = $FreeShippingMdl->queryCouponWithRule($queryParams['coupon_id'], $queryParams['member_id'],
            $queryParams['company_id'], $queryParams['filter_data'], $err_code, $err_msg);
        \Neigou\Logger::General(
            'Voucher.Freeshipping.GetCouponWithRule',
            array(
                'action' => 'FreeShippingController/queryCouponWithRule',
                'data' => $queryParams,
                'result' => json_encode($queryCouponResult)
            )
        );
        if (intval($queryCouponResult) > 0) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($queryCouponResult);
        } else {
            $this->setErrorMsg($err_msg);
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 免邮券规则详情获取
     * @return array
     */
    public function getRuleList(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new FreeShippingCouponModel();
        $rzt = $obj->getRuleList($params);
        \Neigou\Logger::General(
            'Voucher.Freeshipping.Rule.GetList',
            array(
                'action' => 'FreeShippingController/getRuleList',
                'data' => $params,
                'result' => $rzt
            )
        );
        if (is_array($rzt)) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg($rzt);
            return $this->outputFormat(null, 10001);
        }
    }

    /**
     * 获取免邮券规则信息
     * @return array
     */
    public function getRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new FreeShippingCouponModel();
        $rzt = $obj->getRule($params);
        \Neigou\Logger::General(
            'Voucher.Freeshipping.Rule.Get',
            array(
                'action' => 'FreeShippingController/getRule',
                'data' => $params,
                'result' => $rzt->rule_id
            )
        );
        if (is_object($rzt)) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg($rzt);
            return $this->outputFormat(null, 10001);
        }
    }

    /**
     * 免邮券规则添加
     * @return array
     */
    public function saveRule(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $rule_manager = new FreeShippingCouponModel();
        $rule_id = $rule_manager->saveRule($json_data);
        \Neigou\Logger::General(
            'Voucher.Freeshipping.Rule.Save',
            array(
                'action' => 'Voucher/saveRule',
                'data' => $json_data,
                'result' => $rule_id
            )
        );
        if (intval($rule_id) > 0) {
            $this->setErrorMsg('请求成功');
            $out['rule_id'] = $rule_id;
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg($rule_id);
            return $this->outputFormat(null, 404);
        }
    }


    ///////////////////////// 购物车改造 ///////////////////////

    /**
     * 批量使用免邮券
     * @return array
     */
    public function createOrderForCouponV2(Request $request)
    {
        $data = $this->getContentArray($request);
        $FreeShippingMdl = new FreeShippingCouponModel();
        $createOrderResult = $FreeShippingMdl->createOrderForCouponV2($data['couponInfo']);
        \Neigou\Logger::General(
            'Voucher.FreeShipping.createOrderForCoupon',
            array(
                'action' => 'FreeShippingController/createOrderForCoupon',
                'data' => $data,
                'result' => $createOrderResult
            )
        );
        if (is_array($createOrderResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createOrderResult);
        } else {
            $this->setErrorMsg($createOrderResult);
            return $this->outputFormat(null, 404);
        }
    }

}
