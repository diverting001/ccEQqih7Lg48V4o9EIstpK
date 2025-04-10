<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/4/2
 * Time: 14:35
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Voucher\DutyFreeCouponModel;
use Illuminate\Http\Request;
use Neigou\Logger;

class DutyFreeController extends BaseController
{

    public function createMemberCoupon(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['coupon_name']) ||
            !isset($createParams['member_id']) ||
            !isset($createParams['company_id']) ||
            !isset($createParams['valid_time'])) {
            \Neigou\Logger::General(
                'DutyFree.MemberCoupon.Create',
                array(
                    'action' => 'DutyFree/createMemberCoupon',
                    'reason' => 'invalid_params',
                    'data' => $createParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new DutyFreeCouponModel();
        $createResult = $FreeShippingMdl->createMemberCoupon(
            $createParams['member_id'],
            $createParams['company_id'],
            $createParams['coupon_name'],
            $createParams['valid_time'],
            $createParams['op_name'],
            $createParams['rule_id'],
            $createParams['rule_name'],
            $createParams['money'],
            $createParams['start_time'],
            $err_code,
            $err_msg
        );
        \Neigou\Logger::General(
            'DutyFree.MemberCoupon.Create',
            array(
                'action' => 'DutyFree/createMemberCoupon',
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

    //查询使用金额
    public function queryCouponWithList(Request $request)
    {
        $data = $this->getContentArray($request);
        $member_id = $data['member_id'];
        $company_id = $data['company_id'];
        $goods_list = $data['goods_list'];
        $coupon_ids = $data['coupon_ids'];
        $FreeShippingMdl = new DutyFreeCouponModel();
        $queryMemberCouponResult = $FreeShippingMdl->queryWithList($member_id, $company_id, $coupon_ids, $goods_list);
        Logger::General(
            'DutyFree.MemberCoupon.GetWithList',
            array(
                'action' => 'DutyFree/queryCouponWithList',
                'data' => $data,
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

    //TODO 获取可用券列表
    public function queryCouponWithRule(Request $request)
    {
        $data = $this->getContentArray($request);
        $member_id = $data['member_id'];
        $company_id = $data['company_id'];
        $goods_list = $data['goods_list'];
        $FreeShippingMdl = new DutyFreeCouponModel();
        $queryMemberCouponResult = $FreeShippingMdl->queryWithRule($member_id, $company_id, $goods_list);
        Logger::General(
            'DutyFree.MemberCoupon.GetWithRule',
            array(
                'action' => 'DutyFree/queryCouponWithRule',
                'data' => $data,
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

    //获取免邮券列表-个人所有券
    public function queryMemberCoupon(Request $request)
    {
        $queryParams = $this->getContentArray($request);
        if (!isset($queryParams['member_id'])) {
            Logger::Debug(
                'DutyFree.MemberCoupon.MemberCoupon.Get',
                array(
                    'action' => 'DutyFree/queryMemberCoupon',
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
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new DutyFreeCouponModel();
        $queryMemberCouponResult = $FreeShippingMdl->queryMemberCoupon($queryParams['member_id'], $where, $limit);
        Logger::Debug(
            'DutyFree.MemberCoupon.MemberCoupon.Get',
            array(
                'action' => 'DutyFree/queryMemberCoupon',
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

    /**
     * 免邮券规则详情获取
     * @return array
     */
    public function getRuleList(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new DutyFreeCouponModel();
        $rzt = $obj->getRuleList($params);
        Logger::General(
            'DutyFree.Rule.GetList',
            array(
                'action' => 'DutyFree/getRuleList',
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

    //为订单创建券
    public function createOrderForCoupon(Request $request)
    {
        $createParams = $this->getContentArray($request);
        if (!isset($createParams['coupon_id']) ||
            !isset($createParams['member_id']) ||
            !isset($createParams['order_id']) ||
            !isset($createParams['filter_data'])) {
            \Neigou\Logger::General(
                'DutyFree.createOrderForCoupon',
                array(
                    'action' => 'DutyFree/createOrderForCoupon',
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
        $FreeShippingMdl = new DutyFreeCouponModel();
        $createOrderResult = $FreeShippingMdl->createOrderForCoupon(
            $createParams['coupon_id'],
            $createParams['order_id'],
            $createParams['member_id'],
            $companyId,
            $createParams['filter_data'],
            $err_code,
            $err_msg
        );
        Logger::General(
            'DutyFree.createOrderForCoupon',
            array(
                'action' => 'DutyFree/createOrderForCoupon',
                'data' => $createParams,
                'result' => $createOrderResult
            )
        );
        if (intval($createOrderResult['coupon_id']) > 0) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($createOrderResult);
        } else {
            $this->setErrorMsg($createOrderResult);
            return $this->outputFormat(null, 404);
        }
    }

    //取消订单回收券
    public function cancelOrderForCoupon(Request $request)
    {
        $cancelParams = $this->getContentArray($request);
        if (!isset($cancelParams['order_id'])) {
            Logger::General(
                'DutyFree.cancelOrderForCoupon',
                array(
                    'action' => 'DutyFree/cancelOrderForCoupon',
                    'reason' => 'invalid_params',
                    'data' => $cancelParams
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        $err_code = 0;
        $err_msg = "";
        $FreeShippingMdl = new DutyFreeCouponModel();
        $cancelOrderResult = $FreeShippingMdl->cancelOrderForCoupon($cancelParams['order_id'], $err_code, $err_msg);
        Logger::General(
            'DutyFree.cancelOrderForCoupon',
            array(
                'action' => 'DutyFree/cancelOrderForCoupon',
                'data' => $cancelParams,
                'result' => $cancelOrderResult
            )
        );
        if (!empty($cancelOrderResult)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($cancelOrderResult);
        } else {
            $this->setErrorMsg($err_msg);
            return $this->outputFormat(null, 404);
        }
    }

    //订单完成
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
        $FreeShippingMdl = new DutyFreeCouponModel();
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

    //订单券查询
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
        $FreeShippingMdl = new DutyFreeCouponModel();
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

    /**
     * 免邮券规则添加
     * @return array
     */
    public function saveRule(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $rule_manager = new DutyFreeCouponModel();
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


    //////////////// 购物车改造 ///////////////////////
    //批量使用免税券
    public function createOrderForCouponV2(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new DutyFreeCouponModel();
        $rzt = $mdl->createOrderForCouponV2($data['couponInfo']);
        if (is_array($rzt)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg($rzt);
            return $this->outputFormat(null, 404);
        }
    }


}
