<?php

namespace App\Api\Logic\AssetOrder;
use App\Api\Common\Common;
use App\Api\Logic\Service;
#积分服务
class Point
{
    protected  $serviceLogic ;
    protected $member_id ;
    protected $commpany_id ;
    protected $point_channel ;
    protected $instanceData = [] ;
    protected $member_point  ;

    public function __construct($member_id,$company_id,$point_channel)
    {
        $this->serviceLogic = new Service();
        $this->member_id = $member_id ;
        $this->commpany_id = $company_id ;
        $this->point_channel  = $point_channel ;
    }
    /**
     * 获取指定渠道积分信息
     */
    public function pointInfo()
    {
        if ( ! $this->point_channel) {
            return array();
        }
        if (isset($this->instanceData[$this->point_channel])) {
            return $this->instanceData[$this->point_channel];
        }
        $res = $this->serviceLogic->ServiceCall(
            'get_channel_point',
            [
                'channel'    => $this->point_channel,
                'member_id'  => $this->memberId,
                'company_id' => $this->companyId,
            ]
        );
        if ('SUCCESS' == $res['error_code']) {
            $this->instanceData[$this->point_channel] = $res['data'];
            return $res['data'];
        } else {
            \Neigou\Logger::Debug("calculate.v6.false", array(
                'action'  => 'get_freight',
                "sparam1" => json_encode([
                    'channel'    => $this->point_channel,
                    'member_id'  => $this->memberId,
                    'company_id' => $this->companyId,
                ]),
                "sparam2" => json_encode($res),
            ));
        }
        return array();
    }

    public function getMemberPoint()
    {
        $post_data =  [
            'member_id'  => $this->member_id,
            'company_id' => $this->commpany_id,
            'channel'    => $this->point_channel,
        ] ;
        if(is_array($this->member_point) && !empty($this->member_point)) {
            return $this->member_point ;
        }
        $res = $this->serviceLogic->ServiceCall('get_member_point',
            $post_data  ,
            'v3'
        );
        if ('SUCCESS' == $res['error_code']) {
            foreach ($res['data'] as &$account) {
                unset($account['sons']);
            }
            $this->member_point = $res['data'] ;
            return $res['data'];
        }
        return array();
    }

    /**
     * 各种积分转钱
     */
    public function point2money($point = 0, $account = '')
    {
        if(bccomp($point ,'0' ,2) == 0) {
            return 0 ;
        }
        if ( ! $point || ! $this->point_channel) {
            return 0;
        }
        $rate1 = 0;
        if ($account) {
            $rate1 = $this->member_point[$account]['exchange_rate'] ?? 0;
        }
        if ( ! $rate1) {
            $info  = $this->pointInfo();
            $rate1 = $info['exchange_rate'] ?? 0;
        }

        $rate   = 1 / $rate1;
        $money1 = $point * $rate;
        $money  = Common::number2price($money1);
        return $money;
    }
    /**
     * 钱转积分
     */
    public function money2point($money = 0, $account = '')
    {
        if(bccomp($money ,'0' ,2) == 0) {
            return 0 ;
        }
        if ( ! $money || ! $this->point_channel) {
            return 0;
        }
        $rate1 = 0;
        if ($account) {
            $rate1 = $this->member_point[$account]['exchange_rate'] ?? 0;
        }
        if ( ! $rate1) {
            $info  = $this->pointInfo();
            $rate1 = $info['exchange_rate'] ?? 0;
        }
        $expArr    = explode('.', $rate1 / 100);
        $precision = isset($expArr[1]) ? strlen($expArr[1]) : 0;
        $rate      = 1 / $rate1;
        $point     = Common::number2price($money / $rate, $precision);
        return $point;
    }
}
