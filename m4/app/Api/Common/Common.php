<?php
/**
 * Created by PhpStorm.
 * User: maojz
 * Date: 17/6/8
 * Time: 11:20
 */

namespace App\Api\Common;

class Common
{
    /*
     * @todo 获取salyut签名验证
     */
    public static function GetSalyutOrderSign($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        $str = "";
        foreach ($arr as $k => $v) {
            $str .= $k . ":" . $v . ",";
        }
        $str = substr($str, 0, -1);
        $infomd5Ori = config('neigou.SALYUT_ORDER_SIGN') . $str . config('neigou.SALYUT_ORDER_SIGN');
        return md5($infomd5Ori);
    }

    /*
     * @todo 获取EC签名验证
     */
    public static function GetEcStoreSign($arr)
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
        $sign_ori_string .= ("&key=" . config('neigou.STORE_SIGN'));
        return strtoupper(md5($sign_ori_string));
    }

    /*
     * @todo 获取SHOP签名验证
     */
    public static function GetShopSign($arr)
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

    /*
    * @todo 获取SHOP 签名验证
    */
    public static function GetShopV2Sign($arr, $appSecret)
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
        $sign_ori_string .= ("&key=" . $appSecret);
        return strtoupper(md5($sign_ori_string));
    }

    /*
     * @todo 获取MVP签名验证
     */
    public static function GetCommonSign($arr, $sign)
    {
        ksort($arr);
        $signString = json_encode($arr);
        $signString .= ('&key=' . $sign);
        return strtoupper(md5($signString));
    }

    /**
     * 判断点是否在不规则多边形中
     * @param $point
     * @param $polygon
     * @return bool
     */
    public static function pointIsInPolygon($point, $polygon)
    {
        $pointX = $point['lat'];
        $pointY = $point['lng'];

        $inLine = false;
        for ($i = 0, $L = count($polygon), $j = $L - 1; $i < $L; $j = $i, $i++) {
            //获取相邻2个点
            $sx = $polygon[$i]['lat'];
            $sy = $polygon[$i]['lng'];
            $tx = $polygon[$j]['lat'];
            $ty = $polygon[$j]['lng'];

            // 点与多边形顶点重合
            if (($sx === $pointX && $sy === $pointY) || ($tx === $pointX && $ty === $pointY)) {
                return true;
            }

            // 判断线段两端点是否在射线两侧
            if (($sy < $pointY && $ty >= $pointY) || ($sy >= $pointY && $ty < $pointY)) {
                // 线段上与射线 Y 坐标相同的点的 X 坐标
                $x = $sx + ($pointY - $sy) * ($tx - $sx) / ($ty - $sy);

                // 点在多边形的边上
                if ($x === $pointX) {
                    return true;
                }

                // 射线穿过多边形的边界
                if ($x > $pointX) {
                    $inLine = !$inLine;
                }
            }
        }
        return $inLine;
    }
}
