<?php

namespace App\Api\Logic\Point\ScenePoint;

use App\Api\Logic\Openapi;

class OpenapiPoint extends ScenePoint
{

    private $config = array();


    public function __construct($config)
    {
        parent::__construct();
        $this->config = $config;
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
            'channel'    => $data['channel']
        ];
        $res_data  = $this->SendData('get_member_point_uri', $post_data);
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
            'order_id'        => $data['out_trade_no'],
            'channel'         => $data['channel'],
            'company_id'      => $data['company_bn'],
            'member_id'       => $data['member_bn'],
            'money'           => $data['money'],
            'point'           => $data['point'],
            'third_point_pwd' => $data['third_point_pwd'],
            'overdue_time'    => $data['overdue_time'],
            'account_list'    => $data['account_list'],
            'memo'            => $data['memo'],
            'extend_data'     => $data['extend_data'],
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
            'channel'     => $data['channel'],
            'member_id'   => $data['member_bn'],
            'company_id'  => $data['company_id'],
            'order_id'    => $data['out_trade_no'],
            'memo'        => $data['memo'],
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
            'channel'      => $data['channel'],
            'order_id'     => $data['out_trade_no'],
            'member_id'    => $data['member_bn'],
            'company_id'   => $data['company_id'],
            "point"        => $data['point'],
            "money"        => $data['money'],
            "account_list" => $data['account_list'],
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
            'channel'      => $data['channel'],
            'refund_id'    => $data['refund_id'],
            'order_id'     => $data['order_id'],
            'member_id'    => $data['member_id'],
            'company_id'   => $data['company_id'],
            'point'        => $data['point'],
            'money'        => $data['money'],
            'account_list' => $data['account_list'],
            'memo'         => $data['memo'] ? $data['memo'] : '',
        ];
        $res_data  = $this->SendData('refund_member_point_uri', $post_data);
        return $res_data;
    }

    public function GetMemberRecord($data)
    {
        if (empty($data['member_bn'])) {
            return false;
        }
        $post_data = [
            'channel'     => $data['channel'],
            'member_ids'  => array($data['member_bn']),
            'company_ids' => array($data['company_bn']),
            'scene_ids'   => $data['scene_ids'],
            'accounts'    => $data['accounts'],
            'begin_time'  => $data['begin_time'],
            'end_time'    => $data['end_time'],
            'record_type' => $data['record_type'],
            'page'        => $data['page'],
            'page_size'   => $data['page_size']
        ];
        $res_data  = $this->SendData('member_record_uri', $post_data);
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

        $post_data['channel'] = $this->config['channel'];

        $openapi_logic = new Openapi();

        $result = $openapi_logic->Query($this->config[$uri], $post_data);

        if ($result['Result'] != 'true') {
            \Neigou\Logger::Debug('third_scene_point_post_fail', array(
                'sender' => json_encode($post_data),
                'reason' => json_encode($result),
                'action' => $this->config[$uri]
            ));
        }

        return $result;
    }
}
