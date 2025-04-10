<?php

namespace App\Api\Logic;

class AfterSaleNotify
{
    /*
     * 创建通知业务方
     */
    public function createThird($post_data = array(), &$msg = '')
    {
        return true;

        if (empty($post_data)) {
            return false;
        }
        
        //自营第三方部分
        if (in_array($post_data['wms_code'], array('SHOP', 'SHOPNG'))) {

            if ($post_data['wms_code'] == 'SHOP') {
                $appSecret = config('neigou.SHOP_APPSECRET');
                $appKey = config('neigou.SHOP_APPKEY');

            } elseif ($post_data['wms_code'] == 'SHOPNG') {
                $appSecret = config('neigou.SHOPNG_APPSECRET');
                $appKey = config('neigou.SHOPNG_APPKEY');
            } else {
                return false;
            }

            $token = \App\Api\Common\Common::GetShopV2Sign($post_data, $appSecret);

            $post_data = array(
                'appkey' => $appKey,
                'data' => json_encode($post_data),
                'sign' => $token,
                'time' => date('Y-m-d H:i:s'),
            );
            $url = config('neigou.SHOP_DOMIN') . '/Shop/OpenApi/Channel/V1/Order/Return/createNotify';
        } else {
            $post_data['data'] = base64_encode(json_encode($post_data));
            $post_data['token'] = \App\Api\Common\Common::GetEcStoreSign($post_data);
            $url = config('neigou.STORE_DOMIN') . '/openapi/returnOrder/createNotify';
        }

        $_curl = new \Neigou\Curl();
        $_curl->time_out = 7;
        $result = $_curl->Post($url, $post_data);
        $result = json_decode($result, true);
        \Neigou\Logger::Debug(
            'after_sale_create_notify_create',
            array(
                'data' => json_encode($post_data),
                'result' => json_encode($result),
                'remark' => $url
            )
        );
        if ($result['Result'] == 'false') {
            $msg = $result['ErrorMsg'];
            \Neigou\Logger::General(
                'after_sale_create_notify_fail',
                array(
                    'data' => json_encode($post_data),
                    'result' => json_encode($result)
                )
            );
            return false;
        } else {
            return true;
        }
    }

    /*
     * 更新通知业务方
     */
    public function updateThird($wmsCode, $post_data = array(), &$msg = '')
    {
        return true;

        if (empty($post_data)) {
            return false;
        }

        if (in_array($wmsCode, array('SHOP', 'SHOPNG'))) {

            if ($wmsCode == 'SHOP') {
                $appSecret = config('neigou.SHOP_APPSECRET');
                $appKey = config('neigou.SHOP_APPKEY');

            } elseif ($wmsCode == 'SHOPNG') {
                $appSecret = config('neigou.SHOPNG_APPSECRET');
                $appKey = config('neigou.SHOPNG_APPKEY');
            } else {
                return false;
            }
            $token = \App\Api\Common\Common::GetShopV2Sign($post_data, $appSecret);
            $post_data = array(
                'appkey' => $appKey,
                'data' => json_encode($post_data),
                'sign' => $token,
                'time' => date('Y-m-d H:i:s'),
            );
            $url = config('neigou.SHOP_DOMIN') . '/Shop/OpenApi/Channel/V1/Order/Return/updateNotify';
        } else {
            $post_data['data'] = base64_encode(json_encode($post_data));
            $post_data['token'] = \App\Api\Common\Common::GetEcStoreSign($post_data);
            $url = config('neigou.STORE_DOMIN') . '/openapi/returnOrder/updateNotify';
        }

        $_curl = new \Neigou\Curl();
        $_curl->time_out = 7;
        $result = $_curl->Post($url, $post_data);
        $result = json_decode($result, true);
        \Neigou\Logger::Debug(
            'after_sale_update_notify_update',
            array(
                'data' => json_encode($post_data),
                'result' => json_encode($result),
                'remark' => $url
            )
        );
        if ($result['Result'] == 'false') {
            $msg = $result['ErrorMsg'];
            \Neigou\Logger::General(
                'after_sale_update_notify_fail',
                array(
                    'data' => json_encode($post_data),
                    'result' => json_encode($result)
                )
            );
            return false;
        } else {
            return true;
        }
    }
}
