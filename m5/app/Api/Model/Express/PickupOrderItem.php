<?php

namespace App\Api\Model\Express;

use Illuminate\Database\Eloquent\Model;


class PickupOrderItem extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'server_express_pickup_order_item';

    public $timestamps = false;

    /**
     * @param $item
     * @return mixed
     */
    public function savePickupItem($item) {
        return $this->insert($item);
    }

    /**
     * @param $pickupOrderId
     * @return mixed
     */
    public function getPickupOrderItemList($pickupOrderId) {
        $where = [
            'pickup_order_id' => $pickupOrderId,
        ];
        return $this->where($where)->get()->toArray();
    }

    public function getItemListByOrderId($pickupOrderId) {

        return $this->whereIn('pickup_order_id', $pickupOrderId)->get()->toArray();
    }

}
