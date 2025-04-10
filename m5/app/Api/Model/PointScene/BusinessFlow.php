<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;


class BusinessFlow
{
    /**
     * 创建业务流水
     */
    public static function Create($businessInfo)
    {
        $businessInfo = array(
            'business_flow_code' => $businessInfo['business_flow_code'],
            'business_type' => $businessInfo['business_type'],
            'business_bn' => $businessInfo['business_bn'],
            'system_code' => $businessInfo['system_code'],
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_business_flow')->insert($businessInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Delete($businessFlowCode)
    {
        return app('api_db')->table('server_new_point_business_flow')->where('business_flow_code', $businessFlowCode)->delete();
    }

    /**
     * 根据业务流水号查询业务流水
     */
    public static function FindByFlowCode($businessFlowCode)
    {
        $where = array(
            'business_flow_code' => strval($businessFlowCode),
        );
        return app('api_db')->table('server_new_point_business_flow')->where($where)->first();
    }

    public static function BindBillCode($businessFlowCode, $billCode)
    {
        $businessInfo = array(
            'business_flow_code' => $businessFlowCode,
            'bill_code' => $billCode,
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_business_bill_rel')->insert($businessInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function GetBillCodeByBusinessFlow($businessFlowCode)
    {
        $whereArr = array(
            'business_flow_code' => strval($businessFlowCode),
        );
        try {
            $relList = app('api_db')->table('server_new_point_business_bill_rel')->where($whereArr)->get();
        } catch (\Exception $e) {
            $relList = null;
        }
        return $relList;
    }

    public static function GetBillCodeByBusiness($businessType, $businessBns, $systemCode)
    {
        if(is_string($businessBns)){
            $businessBns = [$businessBns];
        }
        $whereArr = array(
            'flow.business_type' => $businessType,
            'flow.system_code' => strval($systemCode)
        );
        try {
            $relList = app('api_db')->table('server_new_point_business_flow as flow')
                ->leftJoin('server_new_point_business_bill_rel as rel', "flow.business_flow_code", "=", "rel.business_flow_code")
                ->select("flow.business_type", "flow.business_bn", "flow.system_code", "flow.business_flow_code", "rel.bill_code")
                ->where($whereArr)
                ->whereIn('flow.business_bn',$businessBns)
                ->get();
        } catch (\Exception $e) {
            $relList = null;
        }
        return $relList;
    }

    public static function GetBussineByBillCodes($billCodeList)
    {
        foreach ($billCodeList as &$item) {
            $item = strval($item);
        }
        try {
            $relList = app('api_db')->table('server_new_point_business_flow as flow')
                ->leftJoin('server_new_point_business_bill_rel as rel', "flow.business_flow_code", "=", "rel.business_flow_code")
                ->select("flow.business_type", "flow.business_bn", "flow.system_code", "flow.business_flow_code", "rel.bill_code")
                ->whereIn('rel.bill_code', $billCodeList)
                ->get();
        } catch (\Exception $e) {
            $relList = null;
        }
        return $relList;
    }

}
