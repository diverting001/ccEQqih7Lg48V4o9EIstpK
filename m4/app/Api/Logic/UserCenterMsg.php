<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/8
 * Time: 14:38
 */

namespace App\Api\Logic;

use App\Api\Logic\Config\Config;

class UserCenterMsg
{

    public function sendUserCenterMsg($member_list)
    {
        if ($member_list) {
            $voucherName = Config::getVoucherName();
            $nickname = Config::getWebNickname();

            foreach ($member_list as $v) {
                $money_int = intval($v["money"]);
                if ($v["type"] == 1) {
                    // 过期提醒
                    $content = "{$nickname}提醒您，您有一张{$money_int}元{$voucherName}将于明天过期。";
                } else {
                    // 发券
                    $content = "{$nickname}提醒您，您已获得{$money_int}元{$voucherName}。";
                }

                if ($v['voucher_type'] == 'discount') {
                    if ($v['type'] == 1) {
                        $discount = $v['discount'] / 10;
                        $discount = floatval($discount);
                        $content = "{$nickname}提醒您，您有一张{$discount}折{$voucherName}将于明天过期。";
                    } else {
                        $discount = $v['discount'] / 10;
                        $discount = floatval($discount);
                        $content = "{$nickname}提醒您，您已获得{$discount}折{$voucherName}。";
                    }
                }

                $api_url = sprintf("%s/openapi/sms/regsms", config('neigou.STORE_DOMIN'));
                $arr = array();
                $arr['sms_name'] = $voucherName;
                $arr['member'] = $v['member_id'];
                $arr['sms_type'] = 'ngq';
                $arr['domain'] = config('neigou.STORE_DOMIN');
                $arr['url'] = '/member-voucher.html';
                $arr['sms_content'] = $content;
                if (!empty($v['company_id'])) {
                    $arr['company_id'] = $v['company_id'];
                }
                $result = CurlOpenApi($api_url, $arr);
            }

        }
    }

    public function freeshippingSendUserCenterMsg($member_list)
    {
        if ($member_list) {
            $voucherName = Config::getVoucherName();

            foreach ($member_list as $v) {
                if ($v["type"] == 1) {
                    // 过期提醒
                    $content = "您有一张免邮券将于明天过期，请不要错过哟~快来内购一下吧。";
                } else {
                    // 发券
                    $content = "您已获得一张免邮券，";
                    if ($v['valid_time']) {
                        $valid_date = date("Y年m月d日", $v['valid_time']);
                        $content .= "有效期至" . $valid_date . "，";
                    }
                    $content .= "仅限指定内购平台商品使用。";
                }

                $api_url = sprintf("%s/openapi/sms/regsms", config('neigou.STORE_DOMIN'));
                $arr = array();
                $arr['sms_name'] = $voucherName;
                $arr['member'] = $v['member_id'];
                $arr['sms_type'] = 'ngq';
                $arr['domain'] = config('neigou.STORE_DOMIN');
                $arr['url'] = '/member-voucher.html';
                $arr['sms_content'] = $content;
                if (!empty($v['company_id'])) {
                    $arr['company_id'] = $v['company_id'];
                }
                $result = CurlOpenApi($api_url, $arr);
            }
        }
    }

    public function dutyFreeSendUserCenterMsg($member_list)
    {
        if ($member_list) {
            $voucherName = Config::getVoucherName();

            foreach ($member_list as $v) {
                if ($v["type"] == 1) {
                    // 过期提醒
                    $content = "您有一张免税券将于明天过期，请不要错过哟~快来内购一下吧。";
                } else {
                    // 发券
                    $content = "您已获得一张免税券，";
                    if ($v['valid_time']) {
                        $valid_date = date("Y年m月d日", $v['valid_time']);
                        $content .= "有效期至" . $valid_date . "。";
                    }
//                    $content .= "仅限内购商品使用";
                }
                $api_url = sprintf("%s/openapi/sms/regsms", config('neigou.STORE_DOMIN'));
                $arr = array();
                $arr['sms_name'] = $voucherName;
                $arr['member'] = $v['member_id'];
                $arr['sms_type'] = 'ngq';
                $arr['domain'] = config('neigou.STORE_DOMIN');
                $arr['url'] = '/member-voucher.html';
                $arr['sms_content'] = $content;
                if (!empty($v['company_id'])) {
                    $arr['company_id'] = $v['company_id'];
                }
                $result = CurlOpenApi($api_url, $arr);
            }
        }
    }
}

function CurlOpenApi($api_url, $arr)
{
    $arr['token'] = get_token($arr);
    $result = actionPost($api_url, http_build_query($arr));
    return $result;
}

function get_token($arr)
{
    ksort($arr);
    $sign_ori_string = "";
    foreach ($arr as $key => $value) {
        if (!empty($value) && !is_array($value)) {
            if (!empty($sign_ori_string)) {
                $sign_ori_string .= "&$key=$value";
            } else {
                $sign_ori_string = "$key=$value";
            }
        }
    }
    $sign_ori_string .= ("&key=" . config('neigou.SHOP_SIGN'));
    return strtoupper(md5($sign_ori_string));
}

function actionPost($http_url, $postdata)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $http_url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Errno' . curl_error($ch);
    }
    curl_close($ch);
    return $result;
}
