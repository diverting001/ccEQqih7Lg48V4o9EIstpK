<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/18
 * Time: 19:53
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Order\OrderCalcLog;
use App\Api\Model\Voucher\RuleManager;
use App\Api\Model\Voucher\Voucher;
use App\Api\Model\Voucher\VoucherMember;
use App\Api\Model\Voucher\VoucherPackage;
use App\Api\V1\Service\Voucher\Refund as VoucherRefund;
use App\Api\V1\Service\Voucher\Voucher as VoucherService;
use Illuminate\Http\Request;

class VoucherController extends BaseController
{
    //查券
    public function queryVoucher(Request $request)
    {
        $voucherNumber = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->queryVoucher($voucherNumber);
        \Neigou\Logger::Debug(
            'Voucher.Get',
            array(
                'action' => 'Voucher/queryVoucher',
                'data' => $voucherNumber,
                'result' => json_encode($rzt)
            )
        );
        if (is_object($rzt)) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }

    }

    /**
     * 查询内购券数量
     * @return  string
     */
    public function queryVoucherCount(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $voucher = new Voucher();
        $rzt = $voucher->queryVoucherCount($json_data);
        $this->setErrorMsg('请求成功');
        \Neigou\Logger::Debug(
            'Voucher.Count',
            array(
                'action' => 'Voucher/queryVoucherCount',
                'data' => $json_data,
                'result' => $rzt
            )
        );
        return $this->outputFormat($rzt);
    }

    public function queryVoucherUsedCount(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $voucher = new Voucher();
        $rzt = $voucher->queryVoucherUsedCount($json_data);
        $this->setErrorMsg('请求成功');
        \Neigou\Logger::Debug(
            'Voucher.UsedCount',
            array(
                'action' => 'Voucher/queryVoucherUsedCount',
                'data' => $json_data,
                'result' => $rzt
            )
        );
        return $this->outputFormat($rzt);
    }

    //领券
    public function addVoucher(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->addVoucher(json_encode($data));
        \Neigou\Logger::General(
            'Voucher.Create',
            array(
                'action' => 'Voucher/addVoucher',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }
    }

    //用券
    public function useVoucher(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->useVoucher(
            json_encode($data['voucher_number']),
            $data['member_id'],
            $data['order_id'],
            $data['use_money'],
            $data['memo']
        );
        \Neigou\Logger::General(
            'Voucher.Use',
            array(
                'action' => 'Voucher/useVoucher',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt === true) {
            $this->setErrorMsg('success');
            $code = 200;
        } else {
            $this->setErrorMsg('fail');
            $code = 404;
        }
        return $this->outputFormat($rzt, $code);
    }

    public function disableVoucher(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->disableVoucher($data['voucher_number'], $data['memo']);
        \Neigou\Logger::General(
            'Voucher.Disable',
            array(
                'action' => 'Voucher/disableVoucher',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat(['status' => true]);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(['status' => false], 404);
        }

    }

    /*Voucher end*/


    public function addBlackList(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $voucher = new Voucher();
        $id = $voucher->addBlackList($json_data);
        \Neigou\Logger::General(
            'Voucher.BlackList.Create',
            array(
                'action' => 'Voucher/addBlackList',
                'data' => $json_data,
                'result' => $id
            )
        );
        if (intval($id) > 0) {
            $this->setErrorMsg('请求成功');
            $out['create_id'] = $id;
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg($id);
            return $this->outputFormat(null, 404);
        }
    }

    public function saveBlackList(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $voucher = new Voucher();
        $id = $voucher->saveBlackList($json_data);
        \Neigou\Logger::General(
            'Voucher.BlackList.Save',
            array(
                'action' => 'Voucher/saveBlackList',
                'data' => $json_data,
                'result' => $id
            )
        );
        if (intval($id) > 0) {
            $this->setErrorMsg('请求成功');
            $out['rule_id'] = $id;
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg($id);
            return $this->outputFormat(null, 404);
        }
    }

    public function deleteBlackList(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $voucher = new Voucher();
        $id = $voucher->deleteBlackList($json_data);
        \Neigou\Logger::General(
            'Voucher.BlackList.Delete',
            array(
                'action' => 'Voucher/deleteBlackList',
                'data' => $json_data,
                'result' => $id
            )
        );
        if (intval($id) > 0) {
            $this->setErrorMsg('请求成功');
            $out['rule_id'] = $id;
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg($id);
            return $this->outputFormat(null, 404);
        }
    }

    public function createRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new RuleManager();
        $type = $params['type'];
        $json_data = $params['json_data'];
        $rzt = $obj->createRule($type, $json_data);
        \Neigou\Logger::General(
            'Voucher.Rule.Create',
            array(
                'action' => 'Voucher/createRule',
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

    public function saveRule(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $rule_manager = new RuleManager();
        $rule_id = $rule_manager->saveRule($json_data);
        \Neigou\Logger::General(
            'Voucher.Rule.Save',
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

    public function getRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->getRule($params);
        \Neigou\Logger::Debug(
            'Voucher.Rule.Get',
            array(
                'action' => 'Voucher/getRule',
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

    public function getRuleList(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new RuleManager();
        $rzt = $obj->getRuleList($params);
        \Neigou\Logger::Debug(
            'Voucher.Rule.GetList',
            array(
                'action' => 'Voucher/getRuleList',
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

    /*Rule end*/

    public function createPackageRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new VoucherPackage();
        $rzt = $obj->createPackageRule($params);
        \Neigou\Logger::General(
            'Voucher.PackageRule.Create',
            array(
                'action' => 'Voucher/createPackageRule',
                'data' => $params,
                'result' => $rzt
            )
        );
        if (intval($rzt) > 0) {
            $this->setErrorMsg('请求成功');
            $rzt['pkg_rule_id'] = $rzt;
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('创建失败');
            return $this->outputFormat($rzt, 404);
        }
    }

    /*PackageRule end*/

    //这个原来不能用
    public function addMemberVoucherByCode(Request $request)
    {
        $data = $this->getContentArray($request);
        $member_id = $data['member_id'];
        $json_data = $data['data'];
        $voucher = new VoucherMember();
        $rzt = $voucher->addMemberVoucherByCode($member_id, $json_data);
        \Neigou\Logger::General(
            'Voucher.MemberVoucher.CreateByCode',
            array(
                'action' => 'Voucher/addMemberVoucherByCode',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt === true) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg($rzt);
            return $this->outputFormat(null, 404);
        }
    }

    //用户用券查询
    public function queryMemberVoucher(Request $request)
    {
        $member_id = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->queryMemberVoucher($member_id);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.Used',
            array(
                'action' => 'Voucher/queryMemberVoucher',
                'data' => $member_id,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }


    }

    public function queryMemberVoucherByCompany(Request $request): array
    {
        $data = $this->getContentArray($request);
        $member_id = $data['member_id'];
        $company_id = $data['company_id'];
        if(empty($member_id) || empty($company_id)){
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }
        $obj = new Voucher();
        $rzt = $obj->queryMemberVoucher($member_id,$company_id);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.Used',
            array(
                'action' => 'Voucher/queryMemberVoucherByCompany',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }


    }

    //创建用户券
    public function createMemVoucher(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new VoucherMember();
        $member_id = $params['member_id'];
        $json_data = $params['json_data'];
        $rzt = $obj->createMemVoucher($member_id, json_encode($json_data));
        \Neigou\Logger::General(
            'Voucher.MemberVoucher.Create',
            array(
                'action' => 'Voucher/createMemVoucher',
                'data' => $params,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 返还用户券
     *
     * member_id 被退还用户
     * old_voucher 被退还的券信息
     * new_voucher 新券的信息
     */
    public function refundMemVoucher(Request $request)
    {
        $params = $this->getContentArray($request);
        $memberId = $params['member_id'];
        $refundInfo = $params['refundinfo'];
        $voucherService = new VoucherRefund();
        $res = $voucherService->Refund($memberId, $refundInfo);
        if ($res['error_code'] != 200) {
            $this->setErrorMsg($res['error_msg']);
            return $this->outputFormat($res['data'], $res['error_code']);
        } else {
            $this->setErrorMsg('创建成功');
            return $this->outputFormat($res['data']);
        }
    }

    public function bindMemVoucher(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new VoucherMember();
        $member_id = $params['member_id'];
        $voucher_number = $params['voucher_number'];
        $source_type = $params['source_type'];
        $rzt = $obj->bindMemVoucher($member_id, $voucher_number, $source_type);
        \Neigou\Logger::General(
            'Voucher.MemberVoucher.Bind',
            array(
                'action' => 'Voucher/bindMemVoucher',
                'data' => $params,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }

    }

    /*MemberVoucher end*/

    public function largeBindMemVoucher(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $obj = new VoucherMember();
        $rzt = $obj->largeBindMemVoucher($json_data);
        \Neigou\Logger::General(
            'Voucher.MemberVoucher.BatchBind',
            array(
                'action' => 'Voucher/largeBindMemVoucher',
                'data' => $json_data,
                'result' => $rzt,
                'microtime' => number_format(microtime(true) - $request->server('REQUEST_TIME_FLOAT'), 2) . ' s',
            )
        );

        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }

    }

    //用户绑定的券列表
    public function queryMemberBindedVoucher(Request $request)
    {
        $member_id = $this->getContentArray($request);
        $obj = new VoucherMember();
        $rzt = $obj->queryMemberBindedVoucher($member_id);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.GetBinded',
            array(
                'action' => 'Voucher/queryMemberBindedVoucher',
                'data' => $member_id,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }

    }

    public function queryMemberBindedVoucherByGuid(Request $request)
    {
        $guid = $this->getContentArray($request);
        $voucher = new VoucherMember();
        $rzt = $voucher->queryMemVoucherListByGuid($guid);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.GetByGuid',
            array(
                'action' => 'Voucher/queryMemberBindedVoucherByGuid',
                'data' => $guid,
                'result' => $rzt
            )
        );
        if (intval($rzt) > 0) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    public function queryMemberBindedVoucherByCompany(Request $request)
    {
        $data = $this->getContentArray($request);
        $member_id = $data['member_id'];
        $company_id = $data['company_id'];
        if (empty($member_id) || empty($company_id)) {
            $this->setErrorMsg('参数异常');
            return $this->outputFormat(null, 404);
        }
        $voucher = new VoucherMember();
        $rzt = $voucher->queryMemVoucherListByCompany($member_id, $company_id);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.GetByCompany',
            array(
                'action' => 'Voucher/queryMemberBindedVoucherByCompany',
                'data' => $data,
                'result' => $rzt
            )
        );
        if (intval($rzt) > 0) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }


    public function GetByCreateID(Request $request)
    {
        $data = $this->getContentArray($request);
        $voucher = new VoucherMember();
        $rzt = $voucher->queryMemVoucherListByCreateId($data['create_ids']);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.GetByCreateID',
            array(
                'action' => 'Voucher/GetByCreateID',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    //订单用券查询
    public function queryOrderVoucher(Request $request)
    {
        $order_id = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->queryOrderVoucher($order_id);
        \Neigou\Logger::Debug(
            'Voucher.Voucher.GetOrderVoucher',
            array(
                'action' => 'Voucher/queryOrderVoucher',
                'data' => $order_id,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
        } else {
            $this->setErrorMsg('fail');
        }
        return $this->outputFormat($rzt);
    }

    //订单用券查询2 根据calclog进行查询
    public function getOrderVoucher(Request $request)
    {
        $order_id = $this->getContentArray($request);
        //查询 order_calc_voucher表中的订单记录
        $obj = new OrderCalcLog();
        $map['order_id'] = $order_id;
        $rzt = $obj->getVoucherLog($map);
        if (count($rzt) > 0) {
            $this->setErrorMsg('success');
        } else {
            $this->setErrorMsg('fail');
        }
        return $this->outputFormat($rzt);
    }

    /**
     * 订单页面用券
     * @for zfc
     * @return array
     */
    public function queryVoucherWithRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->queryVoucherWithRule($params['json_data'], $params['filter_data']);
        \Neigou\Logger::Debug(
            'Voucher.Voucher.GetWithRule',
            array(
                'action' => 'Voucher/queryVoucherWithRule',
                'data' => $params,
                'result' => json_encode($rzt)
            )
        );
        if (is_object($rzt)) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }
    }


    public function disableVoucherForCreateID(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->disableVoucherForCreateID($data['create_id'], $data['memo']);
        \Neigou\Logger::General(
            'Voucher.DisableForCreateID',
            array(
                'action' => 'Voucher/disableVoucherForCreateID',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat(null, 404);
        }

    }

    public function exchangeStatus(Request $request)
    {
        $params = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->exchangeStatus($params['voucher_number_list'], $params['status'], $params['memo']);
        \Neigou\Logger::General(
            'Voucher.exchangeStatus',
            array(
                'action' => 'Voucher/exchangeStatus',
                'data' => $params,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg($rzt);
            return $this->outputFormat(null, 10001);
        }
    }

    public function useVoucherWithRule(Request $request)
    {
        $params = $this->getContentArray($request);
        $data = $params['data'];
        $filter_data = $params['filter_data'];
        $obj = new Voucher();
        $rzt = $obj->useVoucherWithRule($data, $filter_data);
        \Neigou\Logger::General(
            'Voucher.UseWithRule',
            array(
                'action' => 'Voucher/useVoucherWithRule',
                'data' => $params,
                'result' => $rzt
            )
        );
        if (is_array($rzt)) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat($rzt, 404);
        }
    }


    public function queryMemberBindedVoucherWithRule(Request $request)
    {
        $data = $this->getContentArray($request);
        $voucher = new VoucherMember();
        $member_id = $data['member_id'];
        $json_filter_data = $data['json_data'];
        $rzt = $voucher->queryMemberBindedVoucherWithRule($member_id, $json_filter_data);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.GetBindedWithRule',
            array(
                'action' => 'Voucher/queryMemberBindedVoucherWithRule',
                'data' => $data,
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


    public function createPackage()
    {
        // Model return true
        // confirm this function is in_use
    }

    /**
     * @param string $apply_params
     * @return string
     * TODO please check 1104 night
     */
    public function applyVoucherPkg(Request $request)
    {
        $apply_params = $this->getContentArray($request);
        $voucher_package = new VoucherPackage();
        $rzt = $voucher_package->applyVoucherPkg($apply_params, $create_result_ids);
        \Neigou\Logger::General(
            'Voucher.Package.Apply',
            array(
                'action' => 'Voucher/applyVoucherPkg',
                'data' => $apply_params,
                'result' => $rzt
            )
        );
        if (intval($rzt) > 0) {
            $this->setErrorMsg('请求成功');
            $out['voucher_pkg_member_id'] = $rzt;
            $out['create_result_ids'] = $create_result_ids;
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat($rzt, 404);
        }
    }

    /**
     * @param string $query_params
     * @return string
     */
    public function queryVoucherPkg(Request $request)
    {
        $query_params = $this->getContentArray($request);
        $voucher_package = new VoucherPackage();
        $rzt = $voucher_package->queryVoucherPkg($query_params);
        \Neigou\Logger::General(
            'Voucher.Package.Get',
            array(
                'action' => 'Voucher/queryVoucherPkg',
                'data' => $query_params,
                'result' => json_encode($rzt)
            )
        );
        if (is_object($rzt)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat($rzt, 404);
        }
    }



    /**
     * 保存规则
     */


    /**
     * queryBlackList
     * @return array
     */
    public function queryBlackList(Request $request)
    {
        $json_data = $this->getContentArray($request);
        $voucher = new Voucher();
        $list = $voucher->queryBlackList($json_data);
        \Neigou\Logger::Debug(
            'Voucher.BlackList.Get',
            array(
                'action' => 'Voucher/queryBlackList',
                'data' => $json_data,
                'result' => json_encode($list)
            )
        );
        if (is_object($list)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($list);
        } else {
            $this->setErrorMsg($list);
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * queryBlackList
     * @return array
     */
    public function batchQueryBlackList(Request $request)
    {
        $json_data = $this->getContentArray($request);
        if (empty($json_data) ||
            !isset($json_data['type']) ||
            !isset($json_data['rule']) ||
            !isset($json_data['time'])

        ) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'batchQueryBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }
        if (!is_numeric($json_data['type']) ||
            !is_numeric($json_data['time'])
        ) {
            \Neigou\Logger::General(
                'action.voucher',
                array(
                    'action' => 'batchQueryBlackList',
                    'success' => 0,
                    'reason' => 'unvalid_params',
                    "params_data" => $json_data
                )
            );
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(null, 404);
        }

        $voucher = new Voucher();
        $list = $voucher->batchQueryBlackList($json_data);
        if (is_array($list)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($list);
        } else {
            $this->setErrorMsg($list);
            return $this->outputFormat(null, 404);
        }
    }


    /**
     * @for zfc
     * @return array
     */
    public function transferVoucher(Request $request)
    {
        $data = $this->getContentArray($request);
        $member_id = $data['member_id'];
        $json_data = $data['data'];
        $voucher = new VoucherMember();
        $rzt = $voucher->transferVoucher($member_id, $json_data);
        \Neigou\Logger::General(
            'Voucher.Transfer',
            array(
                'action' => 'Voucher/transferVoucher',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 查询用户券列表
     * @return string
     */
    public function queryMemberVoucherList(Request $request)
    {
        $data = $this->getContentArray($request);
        $voucher = new VoucherMember();
        $member_id = $data['member_id'];
        $json_data = $data['json_data'];
        $rzt = $voucher->queryMemberVoucherList($member_id, $json_data);
        \Neigou\Logger::Debug(
            'Voucher.MemberVoucher.GetList',
            array(
                'action' => 'Voucher/queryMemberVoucherList',
                'data' => $data,
                'result' => $rzt
            )
        );
        if (is_array($rzt)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    ///////////////////////////////// V2 新版购物车 //////////////////////////////////

    /**
     * 通过商品列表数据获取用户所有的优惠券列表
     * @return array
     */
    public function getVoucherByProduct(Request $request)
    {
        $data = $this->getContentArray($request);
        $service = new VoucherService();
        //$rzt = $service->getVoucherWithProduct($data['member_id'], $data['filter_data']);
        $company_id = $data['company_id'] ?? 0;
        $rzt = $service->getVoucherByFilter($data['member_id'], $company_id, $data['filter_data']);
        if ($rzt) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    //用券 V2
    public function multiUseVoucher(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->multiUseVoucher($data);
        \Neigou\Logger::General(
            'Voucher.Use',
            array(
                'action' => 'Voucher/useVoucher',
                'data' => $data,
                'result' => $rzt
            )
        );
        if ($rzt['status'] == true) {
            $code = 0;
            $this->setErrorMsg('请求成功');
        } else {
            $this->setErrorMsg($rzt['msg']);
            $code = 404;
        }
        return $this->outputFormat($rzt, $code);
    }

    //With Rule
    public function multiUseVoucherWithRule(Request $request)
    {
        $data = $this->getContentArray($request);
        $obj = new Voucher();
        $rzt = $obj->MultiUseVoucherWithRule($data);
        \Neigou\Logger::General(
            'Voucher.UseWithRule',
            array(
                'action' => 'Voucher/useVoucherWithRule',
                'data' => $data,
                'result' => $rzt
            )
        );
        if (is_array($rzt)) {
            $this->setErrorMsg('success');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('fail');
            return $this->outputFormat($rzt, 404);
        }
    }

    //通过商品列表获取对应的规则ID
    public function getRuleIdByProducts(Request $request)
    {
        $data = $this->getContentArray($request);
        $service = new VoucherService();
        $gidA = array();
        foreach ($data['filter_data']['products'] as $goods) {
            $gidA[] = $goods['goods_id'];
        }
        $rzt = $service->getRuleIdWithProduct($gidA);
        if ($rzt) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    //查询优惠券使用信息
    public function usedQuery(Request $request)
    {
        $voucher_number = $this->getContentArray($request);
        //查询优惠券信息
        $voucher_model = new Voucher();
        $data['voucher_info'] = $voucher_model->queryVoucher($voucher_number);
        //查询规则信息
        $rule_model = new RuleManager();
        $map["rule_id_list"] = array($data['voucher_info']->rule_id);
        $data['rule_info'] = $rule_model->getRuleList($map);
        //查询券使用信息
        $data['used'] = $voucher_model->queryVoucherUsedInfo($data['voucher_info']->voucher_id);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($data);
    }
}
