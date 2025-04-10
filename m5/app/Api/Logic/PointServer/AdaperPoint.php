<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-03-04
 * Time: 10:59
 */

namespace App\Api\Logic\PointServer;


use App\Api\Model\PointServer\AdapterPoint;

class AdaperPoint
{
    const  RATE_TYPE_INT = 1; //积分兑换比例. 1: 将 channel积分 转为 内购积分
    const  RATE_TYPE_OUT = 2; //积分兑换比例. 2: 将 内购积分 转为 channel积分
    const DEFAULT_ADAPTER_RATE = 100.00; //默认兑换比例是100
    const DEFAULT_NEIGOU_RATE = 100.00; //内购默认兑换比例是100

    private $channelList = [];

    static private $instance;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    static public function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** 获取兑换比例转换过的积分数量
     */
    public function GetPoint($point = 0, $channel = '', $type = 1)
    {
        if (isset($this->channelList[$channel])) {
            $rate = $this->channelList[$channel];
        } else {
            $rate = $this->GetRate($channel);

            $this->channelList[$channel] = $rate;
        }

        if ($type == self::RATE_TYPE_INT) {
            $point = sprintf('%.2f', $point / $rate);
            return (int)$point;
        }
        return sprintf('%.2f', $point * $rate);
    }

    /** 计算兑换比例
     *
     * @return mixed
     * @author liuming
     */
    public function GetRate($channel = 0)
    {
        $adapterInfo = AdapterPoint::GetAdapter($channel);
        $rate = $adapterInfo ? $adapterInfo->exchange_rate : self::DEFAULT_ADAPTER_RATE;
        return $this->CalculateRate($rate);
    }

    /** 获取兑换比例
     */
    private function CalculateRate($rate = 0.00)
    {
        if (!$rate) {
            return self::DEFAULT_NEIGOU_RATE / self::DEFAULT_ADAPTER_RATE;
        }
        $rate = $rate / self::DEFAULT_NEIGOU_RATE;
        $flatNum = self::SetFloatLength($rate);
        return round($rate, $flatNum);
    }

    /** 根据channel获取小数点后边的长度
     */
    public function GetFloatLengthByChannel($channel)
    {
        $adapterInfo = AdapterPoint::GetAdapter($channel);
        $rate = $adapterInfo ? $adapterInfo->exchange_rate : self::DEFAULT_ADAPTER_RATE;
        $rate = $rate / self::DEFAULT_NEIGOU_RATE;
        return static::setFloatLength($rate);
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
        $parts = explode('E', $num);
        $exp = abs(end($parts)) + 3;
        $decimal = number_format($num, $exp);
        $decimal = rtrim($decimal, '0');
        $num = rtrim($decimal, '.');
        $pos = strpos($num, '.');
        return strlen(substr($num, $pos)) - 1;
    }
}
