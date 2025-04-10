<?php
/**
 * Created by PhpStorm.
 * User: liuming
 * Date: 2018/9/17
 * Time: 下午2:47
 */

namespace App\Api\V1\Service\DeliveryToB\Channel;

use App\Api\Model\DeliveryToB\Delivery as DeliveryModel;

class Shop extends ADelivery
{
    const CODE_SHOP = 2;
    private static $error_code = array(
        10000,
    );

    public function GetRule($shop_id = 0)
    {
        $_model = new DeliveryModel();
        $row = $_model->shopFind(array('shop_id' => $shop_id));

        try {
            if (empty($row[0])) {
                return 'return 0;';
            }
            return 'return ' . $row[0]->freight . ';';
        } catch (\Exception $e) {
            if (in_array($e->getCode(), self::$error_code)) {
                throw new \Exception($e->getMessage());
            }
            return array();
        }
    }

}
