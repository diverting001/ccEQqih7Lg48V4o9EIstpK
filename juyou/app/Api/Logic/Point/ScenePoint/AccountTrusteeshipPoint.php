<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-26
 * Time: 15:00
 */

namespace App\Api\Logic\Point\ScenePoint;

use App\Api\Logic\Service;

class AccountTrusteeshipPoint extends ScenePoint
{
    private $config = array();
    private $serviceLogic = null;


    public function __construct($config)
    {
        parent::__construct();
        $this->config       = $config;
        $this->serviceLogic = new Service();
    }

    //获取用户积分
    public function GetMemberPoint($data)
    {
        if (empty($data['member_bn'])) {
            return false;
        }
        $post_data = [
            'member_id'  => $data['member_bn'],
            'company_id' => $data['company_bn'],
        ];
        $res_data  = $this->SendData('get_member_point_uri', $post_data);
        return $res_data;

    }

    public function GetMemberPointByOverdueTime($data)
    {
        if (empty($data['member_bn'])) {
            return false;
        }
        $post_data = [
            'member_id'  => $data['member_bn'],
            'company_id' => $data['company_bn'],
            'channel'    => $data['channel'],
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
        ];
        $res_data  = $this->SendData('get_member_point_by_overdue_uri', $post_data);
        return $res_data;
    }


    public function LockMemberPoint($data)
    {
        if (
            empty($data['member_bn']) ||
            empty($data['out_trade_no']) ||
            empty($data['channel']) ||
            empty($data['point'])
        ) {
            return false;
        }
        $post_data = [
            'system_code'     => $data['system_code'],
            'company_id'      => $data['company_bn'],
            'member_id'       => $data['member_bn'],
            'trade_no'        => $data['out_trade_no'],
            'overdue_time'    => $data['overdue_time'],
            'point'           => $data['point'],
            'money'           => $data['money'],
            'third_point_pwd' => $data['third_point_pwd'],
            'memo'            => $data['memo'],
            'account_list'    => $data['account_list'],
            'channel'         => $data['channel'],
        ];
        $res_data  = $this->SendData('lock_member_point_uri', $post_data);
        return $res_data;
    }

    public function CancelLockMemberPoint($data)
    {
        if (empty($data['member_bn']) || empty($data['out_trade_no'])) {
            return false;
        }
        $post_data = [
            'system_code' => $data['system_code'],
            'company_id'  => $data['company_id'],
            'member_id'   => $data['member_bn'],
            'trade_no'    => $data['out_trade_no'],
            'memo'        => $data['memo'],
            'channel'     => $data['channel'],
        ];
        $res_data  = $this->SendData('cancel_member_point_uri', $post_data);
        return $res_data;
    }

    public function ConfirmLockMemberPoint($data)
    {
        if (empty($data['member_bn']) || empty($data['out_trade_no'])) {
            return false;
        }
        $post_data = [
            'system_code'  => $data['system_code'],
            'company_id'   => $data['company_id'],
            'member_id'    => $data['member_bn'],
            'trade_no'     => $data['out_trade_no'],
            "point"        => $data['point'],
            "money"        => $data['money'],
            "memo"         => $data['memo'],
            "account_list" => $data['account_list'],
            'channel'      => $data['channel'],
        ];
        $res_data  = $this->SendData('confirm_member_point_uri', $post_data);
        return $res_data;
    }

    //用户返还锁定积分
    public function RefundPoint($data)
    {
        if (empty($data['member_id']) || empty($data['order_id']) || empty($data['point'])) {
            return false;
        }
        $post_data = [
            'system_code'  => $data['system_code'],
            'company_id'   => $data['company_id'],
            'member_id'    => $data['member_id'],
            'trade_no'     => $data['order_id'],
            'refund_id'    => $data['refund_id'],
            'point'        => $data['point'],
            'money'        => $data['money'],
            'account_list' => $data['account_list'],
            'memo'         => $data['memo'] ? $data['memo'] : '',
            'channel'      => $data['channel'],
        ];
        $res_data  = $this->SendData('refund_member_point_uri', $post_data);
        return $res_data;
    }

    private function SendData($uri, $post_data)
    {
        if (empty($post_data)) {
            return false;
        }

        if (!isset($this->config[$uri])) {
            return false;
        }

        $post_data['time'] = time();
        $send_data         = array('data' => base64_encode(json_encode($post_data)));

        $token = \App\Api\Common\Common::GetEcStoreSign($send_data);

        $send_data['token'] = $token;

        $curl       = new \Neigou\Curl();
        $result_str = $curl->Post(config('neigou.STORE_DOMIN') . $this->config[$uri], $send_data);
        $result     = trim($result_str, "\xEF\xBB\xBF");

        $result = json_decode($result, true);

        \Neigou\Logger::Debug('AccountTrusteeshipPoint', array(
            'action'  => config('neigou.STORE_DOMIN') . $this->config[$uri],
            'sender'  => json_encode($send_data),
            'reason'  => json_encode($result),
            'sparam1' => json_encode($post_data)
        ));

        if ($result['Result'] == 'SUCCESS') {
            $result['Result'] = 'true';
        } else {
            $result['Result'] = 'false';
        }

        return $result;
    }

    /**
     * 用户积分流水
     */
    public function GetMemberRecord($data)
    {
        // TODO: Implement GetMemberRecord() method.
        return ['Result' => 'false'];
    }
}
