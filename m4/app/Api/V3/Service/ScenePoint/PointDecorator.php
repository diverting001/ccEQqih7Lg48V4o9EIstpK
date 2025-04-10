<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-02
 * Time: 19:27
 */

namespace App\Api\V3\Service\ScenePoint;


class PointDecorator
{
    const  RATE_TYPE_INT = 1; //积分兑换比例. 1: 将 channel积分 转为 内购积分
    const  RATE_TYPE_OUT = 2; //积分兑换比例. 2: 将 内购积分 转为 channel积分

    const DEFAULT_ADAPTER_RATE = 100.00; //默认兑换比例是100
    const DEFAULT_NEIGOU_RATE  = 100.00; //内购默认兑换比例是100

    /**
     * 获取兑换比例转换过的积分数量
     * @param int $point
     * @param float $rate
     * @return int|string
     */
    public function GetPointByRate($point, $rate, $type)
    {
        $rate = $this->CalculateRate($rate);
        $flatNum = self::SetFloatLength($rate);
        if ($type == self::RATE_TYPE_INT) {
            $point = sprintf('%.'.$flatNum.'f', $point / $rate);
            return $point;
        }
        return sprintf('%.'.$flatNum.'f', $point * $rate);
    }

    private function CalculateRate($rate = 0.00)
    {
        if (!$rate) {
            return self::DEFAULT_NEIGOU_RATE / self::DEFAULT_ADAPTER_RATE;
        }
        $rate    = $rate / self::DEFAULT_NEIGOU_RATE;
        $flatNum = self::SetFloatLength($rate);
        return round($rate, $flatNum);
    }

    /** 计算小数点后有几位
     *
     * @param float $num
     * @return int
     * @author liuming
     */
    public function SetFloatLength($num = 0.00)
    {
        if ($num > 1) {
            $num = strstr($num, '.');
            $num = floatval($num);
        }
        $parts   = explode('E', $num);
        $exp     = abs(end($parts)) + 3;
        $decimal = number_format($num, $exp);
        $decimal = rtrim($decimal, '0');
        $num     = rtrim($decimal, '.');
        $pos     = strpos($num, '.');
        return strlen(substr($num, $pos)) - 1;
    }
}
