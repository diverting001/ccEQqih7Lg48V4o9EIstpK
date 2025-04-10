<?php


namespace App\Api\Logic\AssetOrder;


use App\Api\Logic\Service;
use App\Api\Model\Company\ClubCompany;

class Credit
{


    /**
     * 额度 消费
     * @param $data
     * @param $credit_id
     * @return bool
     */
    public static  function record($data ){
        $req['rmb_amount'] = $data['rmb_amount'];
        $req['channel'] = $data['channel'];
        $req['company_id'] = $data['company_id'];
        $req['trans_date'] = time();
        $req['trans_type'] = 'order';
        $req['trans_id'] = $data['order_id'];
        $req['settle_status'] = 0;
        $req['part'] = $data['part'] ?? '';

        $serviceObj = new Service() ;
        $res = $serviceObj->ServiceCall('credit_bill_record' ,$req);
        if( 'SUCCESS' == $res['error_code'] && !empty($res['data'])) {
            $credit_id = $res['data'];
            return ['code' => 200 ,'msg' =>'成功' ,'data' => ['credit_id' => $credit_id ]];
        }
        \Neigou\Logger::General('service.credit.record.fail',array(
            'remark'=>'额度使用失败',
            'order_id' => $data['order_id'] ,
            'req'=>$req,
            'res'=>$res
        ));
        $errorCode =  $res['error_detail_code'];
        return ['code' => $errorCode ,'msg' =>'额度使用失败' ];
    }



    /**
     * @param $companyId
     * @param $channel
     * @param $key
     * @return mixed
     */
    public static function GetCompanyChannelSetByKey($companyId,$channel,$key)
    {
        if((!$companyId && !$channel) || !$key){
            return array();
        }
        $company_model = new ClubCompany();
        $data = $company_model->getChannelAndCompanyScope($key,$channel,$companyId);
        if(!$data){
            return array();
        }

        $new_data = array();
        foreach ($data as $scope){
            $new_data[$scope['scope']] = $scope;
        }

        return isset($new_data['company'])?$new_data['company']:$new_data['channel'];
    }



    /*
    * 获取公司tag开通情况 --新版包含all版本
    */
    public static  function getScopeByWeight($key, $channel = '', $company = '')
    {
        $company_model = new ClubCompany();

        $whereSql = "`key`='" . $key . "'";

        $scopeWhereSql = "(`scope`='all' and `scope_value`='all')";

        $whereArr = array();
        if ($channel) {
            $whereArr[] = "(`scope`='channel' and `scope_value`='" . $channel . "')";
        }

        if ($company) {
            $whereArr[] = "(`scope`='company' and `scope_value`='" . $company . "')";
        }

        if ($whereArr) {
            $scopeWhereSql .= ' or ' . implode(' or ', $whereArr);
        }

        $whereSql .= " and (" . $scopeWhereSql . ")";
        $scopeResult = $company_model->getScopeByWhereSqlStr($whereSql);
        if (!$scopeResult) {
            return array();
        }

        $weightArr = array();
        foreach ($scopeResult as $item) {
            $weight = 0;
            switch ($item['scope']) {
                case 'all' :
                    $weight = 2;
                    break;
                case 'channel' :
                    $weight = 1;
                    break;
                case 'company' :
                    $weight = 0;
                    break;
            }
            $weightArr[$weight] = $item;
        }
        ksort($weightArr);
        return array_shift($weightArr);
    }


}
