<?php

namespace App\Api\Model\Shop;

class Shop
{
    private $_db;
    private $popShopGuarded = ['pop_shop_id','pop_owner_id'];

    public function __construct(){
        $this->_db = app('db')->connection('neigou_store');
    }

    /**
     * 更新pop店铺信息
     * @param $pop_shop_id
     * @param $data
     * @return false
     */
    public function updatePopShopInfo($pop_shop_id, $data){
        if(array_intersect($this->popShopGuarded,array_keys($data))){
            return false;
        }
        try {
            $status = $this->_db->table('sdb_b2c_pop_shop')
                ->where(['pop_shop_id' => $pop_shop_id])
                ->update($data);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
