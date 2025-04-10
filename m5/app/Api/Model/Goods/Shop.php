<?php
namespace App\Api\Model\Goods;
use App\Api\Common\Common;
use \App\Api\Model\BaseModel ;
/**
 * Class b2c_mdl_pop_shop
 * pop店铺
 */
class Shop extends BaseModel {
    protected $table = 'sdb_b2c_pop_shop' ;
    protected $primaryKey = 'pop_shop_id' ;

    public function getPosShops($shop_ids_arr){
        if(empty($shop_ids_arr)) {
            return false;
        }
        $result =  $this->getBaseInfo(['pop_shop_id' => $shop_ids_arr] ,['pop_shop_id', 'name as pop_shop_name','sup_name', 'pop_owner_id']) ;
        return Common::array_rebuild($result ,'pop_shop_id') ;
    }


    public  function getPosOwners($pop_owner_id_arr)
    {
        if(empty($pop_owner_id_arr)){
            return false;
        }
        $sql = "select pop_owner_id , `name` as pop_owner_name, pop_wms_id  from sdb_b2c_pop_owner where pop_owner_id IN (" . implode(',', $pop_owner_id_arr) . ")";
        $result   = $this->select($sql) ;
        return Common::array_rebuild($result ,'pop_owner_id') ;
    }

    public  function getPosWmss($pop_wns_id_arr){
        if(empty($pop_wns_id_arr)) {
            return false;
        }
        $sql = "select pop_wms_id , pop_wms_code  from sdb_b2c_pop_wms where pop_wms_id IN (" . implode(',', $pop_wns_id_arr) . ")";
        $info   = $this->select($sql);
        return Common::array_rebuild($info ,'pop_wms_id') ;
    }

    // 保存订单数据
    public function SavepreFerential($order_id,$sdf){
        if(empty($order_id) || empty($sdf)) return false;
        $save_data   = array();
        foreach ($sdf as $k=>$v){
            $save_data[]  = array(
                'order_id'  => $order_id,
                'type'  => $v['type'],
                'code'  => $v['code'],
                'pmt_sum'  => $v['pmt_sum'],
                'detail'  => isset($v['detail']) && !empty($v['detail']) ? $v['detail'] : '' ,
                'create_time'   => time(),
            );
        }
        $res = $this->setTable('sdb_b2c_pop_preferential')->mulitInsert($save_data) ;
        return $res;
    }


}
