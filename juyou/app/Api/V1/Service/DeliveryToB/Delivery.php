<?php

namespace App\Api\V1\Service\DeliveryToB;

class Delivery
{

    const CODE_CHANNEL = 1; //渠道代码
    const CODE_SHOP = 2; //SHOP代码
    const CLASS_SHOP = 'Shop'; //SHOP类
    const CHANNEL_PATH = "App\Api\V1\Service\DeliveryToB\Channel\\";

    public function getDeliveryFreight($param)
    {
        try {
            // 调用各渠道运费计算
            foreach ($param as $channel => $data) {
                if (!isset($data['type'])) {
                    $data['type'] = self::CODE_CHANNEL;
                }

                //todo 根据type判断是渠道还是SHOP
                switch ($data['type']) {
                    case self::CODE_SHOP:
                        //todo 获取shop下的运费
                        //设置shop类
                        $className = ucfirst(strtolower(self::CLASS_SHOP));
                        $class = self::CHANNEL_PATH . $className;
                        if (!class_exists($class)) {
                            throw new \Exception('Shop 类异常', 500);
                        }
                        //向server_tob_delivery表获取expression字段中的运费规则
                        $_freight = new $class();
                        $rule = $_freight->GetRule($channel);
                        //判断运费规则是否存在, 如果存在根据运费规则返回运费信息
                        if ($rule) {
                            $freight = $_freight->ParseRule($data, $rule);
                        } else {
                            $freight = 0;
                        }
                        $param[$channel]['freight'] = $freight;
                        break;

                    case self::CODE_CHANNEL:
                        $channelClass = ucfirst(strtolower($channel));
                        $class = self::CHANNEL_PATH . $channelClass;
                        if (!class_exists($class)) {
                            throw new \Exception('Channel ' . $channel . " not allow", 400);
                        }
                        $_freight = new $class();
                        $rule = $_freight->GetRule();
                        if ($rule) {
                            $freight = $_freight->ParseRule($data, $rule);
                        } else {
                            $freight = 0;
                        }
                        $param[$channel]['freight'] = $freight;
                        break;

                    default:
                        throw new \Exception('type类型不正确', 400);
                }

            }
            return $this->Response(0, 'succ', $param);
        } catch (\Exception $e) {
            return $this->Response($e->getCode(), $e->getMessage());
        }
    }

    private function Response($error_code, $error_msg, $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }
}
