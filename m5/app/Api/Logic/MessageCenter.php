<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/8
 * Time: 14:25
 */

namespace App\Api\Logic;

use App\Api\Logic\Config\Config;
use App\Api\Model\Order\ClubCompany;

require_once __DIR__.'/../../../vendor/NG_THRIFT_CLIENT/RPCClient/client/RPCClient.php';

class MessageCenter
{

    private $_db;

    public function __construct()
    {
        $this->_db = app('db')->connection('neigou_store');
    }

    public function sendMessage($member_money_list)
    {
        if (!is_array($member_money_list)) {
            return false;
        }
        $companyObj = new ClubCompany ;

        $member_id_list = array();
        $msg_data_list = array();
        foreach ($member_money_list as $msg_data) {
            $member_id_list[] = $msg_data["member_id"];
            $msg_data_list[$msg_data["member_id"]] = $msg_data;
        }
        $member_info = $this->_db->table('sdb_b2c_members')->whereIn('member_id', $member_id_list)->get()->all();
        $member_info = json_decode(json_encode($member_info), true);
        $weixinqy_member_info_temp = $this->_db->table('sdb_b2c_third_members')->where('internal_id', 'in',
            $member_id_str)->get()->all();
        $weixinqy_member_info_temp = json_decode(json_encode($weixinqy_member_info_temp), true);
        $weixinqy_member_info = array();
        foreach ($weixinqy_member_info_temp as $weixinqy_member_item) {
            $weixinqy_member_info[$weixinqy_member_item['internal_id']] = $weixinqy_member_item;
        }

        $count = count($member_info);
        foreach ($member_info as $ind => $member_item) {
            $platform = $companyObj->getCompanyPlatform($msg_data_list[$member_item["member_id"]]['company_id']);
            $voucherName = Config::getVoucherName($platform);
            $nickname = Config::getWebNickname($platform);

            $money_int = intval($msg_data_list[$member_item["member_id"]]["money"]);

            if ($msg_data_list[$member_item["member_id"]]["type"] == 1) {
                // 过期提醒
                $sms_content = "您有一张{$money_int}元{$voucherName}将于明天过期，请不要错过哟~快来内购一下吧。回复TD退订";
                $common_content = "您有一张{$money_int}元{$voucherName}将于明天过期。";
            } else {
                // 发券
                $sms_content = "您已获得{$money_int}元{$voucherName}";

                $valid_time = $msg_data_list[$member_item["member_id"]]["valid_time"];
                if (!empty($valid_time)) {
                    $valid_time = date("Y年m月d日", $valid_time);
                    $sms_content .= "，有效期至" . $valid_time;
                }
                $rule_name = $msg_data_list[$member_item["member_id"]]["rule_name"];
                if (!empty($rule_name)) {
                    $sms_content .= "，" . $rule_name;
                }
                $common_content = $sms_content;
                $sms_content = $sms_content . "。回复TD退订";
            }

            if ($msg_data_list[$member_item["member_id"]]["voucher_type"] == 'discount') {
                $discount = $msg_data_list[$member_item["member_id"]]["discount"] / 10;
                $discount = floatval($discount);
                if ($msg_data_list[$member_item["member_id"]]["type"] == 1) {
                    // 过期提醒
                    $sms_content = "您有一张{$discount}折{$voucherName}将于明天过期，请不要错过哟~快来内购一下吧。回复TD退订";
                    $common_content = "您有一张{$discount}折{$voucherName}将于明天过期。";
                } else {
                    // 发券
                    $sms_content = "您已获得{$discount}折{$voucherName}";

                    $valid_time = $msg_data_list[$member_item["member_id"]]["valid_time"];
                    if (!empty($valid_time)) {
                        $valid_time = date("Y年m月d日", $valid_time);
                        $sms_content .= "，有效期至" . $valid_time;
                    }
                    $rule_name = $msg_data_list[$member_item["member_id"]]["rule_name"];
                    if (!empty($rule_name)) {
                        $sms_content .= "，" . $rule_name;
                    }
                    $common_content = $sms_content;
                    $sms_content = $sms_content . "。回复TD退订";
                }
            }

            $message_channel = ($msg_data_list[$member_item["member_id"]] && isset($msg_data_list[$member_item["member_id"]]['message_channel']))?$msg_data_list[$member_item["member_id"]]['message_channel']:'';

            $message_data = array(
                "member_id" => $member_item["member_id"],
                'company_id' => $msg_data_list[$member_item["member_id"]]['company_id'],
                "mobile" => $member_item['mobile'],
                "message_channel" => $message_channel,
                "msg_type" => "voucher",
                "url" => config('neigou.STORE_DOMIN') . "/member-voucher.html",
                "title" => $voucherName,
                "sms_content" => $sms_content,
                "app_content" => $common_content,
                "weixin_openid" => $member_item['wxopenid'],
                "weixin_tmpl_type" => "event",
                "weixin_data" => array(
                    'url' => config('neigou.STORE_DOMIN') . "/member-voucher.html",
                    'first' => $common_content,
                    'keyword1' => $voucherName,
                    'keyword2' => date("Y-m-d"),
                    'remark' => "",
                ),
                "app_data" => array(
                    "type" => '1',
                )
            );

            if (isset($weixinqy_member_info[$member_item["member_id"]])) {
                list($company_bn, $member_bn) = explode('-',
                    $weixinqy_member_info[$member_item["member_id"]]['external_bn'], 2);
                if (!empty($company_bn) && !empty($member_bn)) {
                    $message_data['weixin_qy_companybn'] = $company_bn;
                    $message_data['weixin_qy_memberbn'] = $member_bn;
                    $message_data['weixin_qy_content'] = $common_content;
                }
                if (!empty($weixinqy_member_info[$member_item["member_id"]]['channel']) && !empty($weixinqy_member_info[$member_item["member_id"]]['external_bn'])) {
                    $message_data['external_channel'] = $weixinqy_member_info[$member_item["member_id"]]['channel'];
                    $message_data['external_bn'] = $weixinqy_member_info[$member_item["member_id"]]['external_bn'];
                    $message_data['external_content'] = $common_content;
                }
            }

            $msg_params[] = $message_data;
            if ($ind % 100 == 99 || $ind == $count - 1) {   // 每100个发送一次thrift
                $this->send(array("message_data" => $msg_params));
                $msg_params = array();
            }
        }

        return true;
    }

    public function freeshippingSendMessage($member_list)
    {
        if (!is_array($member_list)) {
            return false;
        }
        $companyObj = new ClubCompany ;

        $member_id_list = array();
        $msg_data_list = array();
        foreach ($member_list as $msg_data) {
            $member_id_list[] = $msg_data["member_id"];
            $msg_data_list[$msg_data["member_id"]] = $msg_data;
        }

        $member_info = $this->_db->table('sdb_b2c_members')->whereIn('member_id', $member_id_list)->get()->all();
        $member_info = json_decode(json_encode($member_info), true);
        $weixinqy_member_info_temp = $this->_db->table('sdb_b2c_third_members')->whereIn('internal_id',
            $member_id_list)->get()->all();
        $weixinqy_member_info_temp = json_decode(json_encode($weixinqy_member_info_temp), true);

        $weixinqy_member_info = array();
        foreach ($weixinqy_member_info_temp as $weixinqy_member_item) {
            $weixinqy_member_info[$weixinqy_member_item['internal_id']] = $weixinqy_member_item;
        }

        $count = count($member_info);
        foreach ($member_info as $ind => $member_item) {
            $platform = $companyObj->getCompanyPlatform($msg_data_list[$member_item["member_id"]]["company_id"]);
            $voucherName = Config::getVoucherName($platform);

            if ($msg_data_list[$member_item["member_id"]]["type"] == 1) {
                // 过期提醒
                $sms_content = "您有一张免邮券将于明天过期，请不要错过哟~快来内购一下吧。回复TD退订";
                $common_content = "您有一张免邮券将于明天过期，请不要错过哟~快来内购一下吧。";
            } else {
                // 发券
                $sms_content = "您已获得一张免邮券";
                $common_content = "您已获得一张免邮券";

                $valid_time = $msg_data_list[$member_item["member_id"]]["valid_time"];
                if (!empty($valid_time)) {
                    $valid_time = date("Y年m月d日", $valid_time);
                    $sms_content .= "，有效期至" . $valid_time;
                    $common_content .= "，有效期至" . $valid_time;
                }
                $sms_content .= "，仅限指定内购平台商品使用。回复TD退订";
                $common_content .= "，仅限指定内购平台商品使用。";
            }

            $message_channel = ($msg_data_list[$member_item["member_id"]] && isset($msg_data_list[$member_item["member_id"]]['message_channel']))?$msg_data_list[$member_item["member_id"]]['message_channel']:'';

            $message_data = array(
                "member_id" => $member_item["member_id"],
                "msg_type" => "voucher",
                'company_id' => $msg_data_list[$member_item["member_id"]]["company_id"],
                "mobile" => $member_item['mobile'],
                "url" => config('neigou.STORE_DOMIN') . "/member-voucher.html",
                "title" => $voucherName,
                "sms_content" => $sms_content,
                'message_channel'=>$message_channel,
                "app_content" => $common_content,
                "weixin_openid" => $member_item['wxopenid'],
                "weixin_tmpl_type" => "event",
                "weixin_data" => array(
                    'url' => config('neigou.STORE_DOMIN') . "/member-voucher.html",
                    'first' => $common_content,
                    'keyword1' => $voucherName,
                    'keyword2' => date("Y-m-d"),
                    'remark' => "",
                ),
                "app_data" => array(
                    "type" => '1',
                )
            );

            if (isset($weixinqy_member_info[$member_item["member_id"]])) {
                list($company_bn, $member_bn) = explode('-',
                    $weixinqy_member_info[$member_item["member_id"]]['external_bn'], 2);
                if (!empty($company_bn) && !empty($member_bn)) {
                    $message_data['weixin_qy_companybn'] = $company_bn;
                    $message_data['weixin_qy_memberbn'] = $member_bn;
                    $message_data['weixin_qy_content'] = $common_content;
                }
                if (!empty($weixinqy_member_info[$member_item["member_id"]]['channel']) && !empty($weixinqy_member_info[$member_item["member_id"]]['external_bn'])) {
                    $message_data['external_channel'] = $weixinqy_member_info[$member_item["member_id"]]['channel'];
                    $message_data['external_bn'] = $weixinqy_member_info[$member_item["member_id"]]['external_bn'];
                    $message_data['external_content'] = $common_content;
                }
            }

            $msg_params[] = $message_data;
            if ($ind % 100 == 99 || $ind == $count - 1) {   // 每100个发送一次thrift
                $this->send(array("message_data" => $msg_params));
                $msg_params = array();
            }
        }

        return true;
    }

    public function dutyFreeSendMessage($member_list)
    {
        if (!is_array($member_list)) {
            return false;
        }

        $member_id_list = array();
        $msg_data_list = array();
        foreach ($member_list as $msg_data) {
            $member_id_list[] = $msg_data["member_id"];
            $msg_data_list[$msg_data["member_id"]] = $msg_data;
        }

        $member_info = $this->_db->table('sdb_b2c_members')->whereIn('member_id', $member_id_list)->get()->all();
        $member_info = json_decode(json_encode($member_info), true);

        $weixinqy_member_info_temp = $this->_db->table('sdb_b2c_third_members')->whereIn('internal_id',
            $member_id_list)->get()->all();
        $weixinqy_member_info_temp = json_decode(json_encode($weixinqy_member_info_temp), true);

        $weixinqy_member_info = array();
        foreach ($weixinqy_member_info_temp as $weixinqy_member_item) {
            $weixinqy_member_info[$weixinqy_member_item['internal_id']] = $weixinqy_member_item;
        }

        $count = count($member_info);
        foreach ($member_info as $ind => $member_item) {
            if ($msg_data_list[$member_item["member_id"]]["type"] == 1) {
                // 过期提醒
                $sms_content = "您有一张免税券将于明天过期，请不要错过哟~快来内购一下吧。回复TD退订";
                $common_content = $sms_content;
            } else {
                // 发券
                $sms_content = "您已获得一张免税券";

                $valid_time = $msg_data_list[$member_item["member_id"]]["valid_time"];
                if (!empty($valid_time)) {
                    $valid_time = date("Y年m月d日", $valid_time);
                    $sms_content .= "，有效期至" . $valid_time;
                }
                $common_content = $sms_content;
                $sms_content = $sms_content . "。回复TD退订";
            }

            $message_channel = ($msg_data_list[$member_item["member_id"]] && isset($msg_data_list[$member_item["member_id"]]['message_channel']))?$msg_data_list[$member_item["member_id"]]['message_channel']:'';

            $message_data = array(
                "member_id" => $member_item["member_id"],
                "msg_type" => "voucher",
                'company_id' => $msg_data_list[$member_item["member_id"]]["company_id"],
                "mobile" => $member_item['mobile'],
                "url" => config('neigou.STORE_DOMIN') . "/member-voucher.html",
                "title" => "免税券",
                "sms_content" => $sms_content,
                'message_channel'=>$message_channel,
                "app_content" => $common_content,
                "weixin_openid" => $member_item['wxopenid'],
                "weixin_tmpl_type" => "event",
                "weixin_data" => array(
                    'url' => config('neigou.STORE_DOMIN') . "/member-voucher.html",
                    'first' => $common_content,
                    'keyword1' => "免税券",
                    'keyword2' => date("Y-m-d"),
                    'remark' => "",
                ),
                "app_data" => array(
                    "type" => '1',
                )
            );

            if (isset($weixinqy_member_info[$member_item["member_id"]])) {
                list($company_bn, $member_bn) = explode('-',
                    $weixinqy_member_info[$member_item["member_id"]]['external_bn'], 2);
                if (!empty($company_bn) && !empty($member_bn)) {
                    $message_data['weixin_qy_companybn'] = $company_bn;
                    $message_data['weixin_qy_memberbn'] = $member_bn;
                    $message_data['weixin_qy_content'] = $common_content;
                }
                if (!empty($weixinqy_member_info[$member_item["member_id"]]['channel']) && !empty($weixinqy_member_info[$member_item["member_id"]]['external_bn'])) {
                    $message_data['external_channel'] = $weixinqy_member_info[$member_item["member_id"]]['channel'];
                    $message_data['external_bn'] = $weixinqy_member_info[$member_item["member_id"]]['external_bn'];
                    $message_data['external_content'] = $common_content;
                }
            }

            $msg_params[] = $message_data;
            if ($ind % 100 == 99 || $ind == $count - 1) {
                $this->send(array("message_data" => $msg_params));
                $msg_params = array();
            }
        }

        return true;
    }

    private function send($data)
    {
        $script_name = 'common.service.proxy.messageCenter';
        $rpc_client = new \RPCClient();
        $rpc_client->dispatchScriptCommandTaskSimpleNoReply($script_name, json_encode($data));
    }


}
