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
    const BCSCALE = 3 ;
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


    /**
     * @todo 获取salyut openapi签名验证
     * @param $arr
     * @return string
     */
    public static function GetSalyutSign($arr)
    {
        ksort($arr);
        $sign_ori_string = "";
        foreach ($arr as $key => $value)
        {
            if ( ! empty($value) && ! is_array($value))
            {
                if ( ! empty($sign_ori_string))
                {
                    $sign_ori_string .= "&$key=$value";
                }
                else
                {
                    $sign_ori_string = "$key=$value";
                }
            }
        }
        $sign_ori_string .= ("&key=" . config('neigou.SALYUT_SIGN'));

        return strtoupper(md5($sign_ori_string));
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

    public static function GetMvpSign($arr)
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
        $sign_ori_string .= ("&key=" . config('neigou.MVP_SIGN'));
        return strtoupper(md5($sign_ori_string));
    }

    public static function array_rebuild($result ,$filed) {
        $data = [] ;
        if(empty($result)) {
            return $data ;
        }
        foreach ($result as $item) {
            if(is_object($item)) {
                $item = get_object_vars($item) ;
            }
            $data[$item[$filed]]  = $item ;
        }
        return $data ;
    }

    // 对数据进行分组
    public static  function  array_group($array,$filed) {
        $data = [] ;
        if(empty($array)) {
            return $data ;
        }
        foreach ($array as $item) {
            if(is_object($item)) {
                $item = get_object_vars($item) ;
            }
            $data[$item[$filed]][] =$item ;
        }
        return $data ;
    }

    // 加发运算
    public static function bcfunc($numbers ,$type="+" ,$scale=2) {
        $total = '0' ;
        if(empty($numbers)) {
            return $total ;
        }
        $dict = [
            "+" => 'bcadd' ,
            "-" => 'bcsub' ,
            "*" => 'bcmul' ,
            "/" => 'bcdiv' ,
        ] ;
        $scale = $scale > 0 ? $scale : self::BCSCALE ;
        bcscale($scale) ;
        $func = isset($dict[$type]) ? $dict[$type] : false ;
        if(empty($func)) {
            return $total ;
        }
        $total = strval($numbers[0]);
        for( $i = 1; $i<count($numbers); $i++ ) {
            $numbers[$i] = trim($numbers[$i]);
            $total = $func(strval($total), strval($numbers[$i]));
        }
        return $total;
    }
    //向下取整
    public static function bcget($number,$decimals=0) {
        $decimals = $decimals > 0 ? $decimals : self::BCSCALE ;
        bcscale($decimals) ;
        $result = call_user_func_array( 'floor' , array($number * pow( 10 ,$decimals )) );
        return bcdiv(strval($result), strval(pow( 10 , $decimals)));
    }

    /**
     * 数字转化成价格
     *
     * @param   $number     string  字符串
     * @param   $precision  int     保留的位数
     * @param   $mode       mixed   浮点数处理策略 null or 0 floor 1 ceil 2 round
     *
     * @return string
     */
   public static function number2price($number = null, $precision = 2, $mode = 3)
    {
        if ( ! is_numeric($number)) {
            $number = floatval($number);
        }

        $p = pow(10, $precision);

        // 设置精度计算小数位
        bcscale($precision );

        $number1 = bcmul($number, $p);
        switch ($mode) {
            case 0:
                $number = floor($number1);
                break;
            case 1:
                $number = ceil($number1);
                break;
            case 2:
                $number = round($number1);
                break;
            default:
                $number = floor($number1);
                break;
        }

        return sprintf("%.{$precision}f", bcdiv($number, $p));
    }


}
