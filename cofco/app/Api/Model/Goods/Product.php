<?php

namespace app\Api\Model\Goods;

class Product{
    private $_db = null;
    private $db_b2c_products = null;

    /**
     * Cat constructor.
     */
    public function __construct(){
        $this->_db             = app('api_db')->connection('neigou_store');
        $this->db_b2c_products     = 'sdb_b2c_products';
    }


    //获取货品列表
    public function GetProductList($where,$columns=['*']){
        if(empty($where)) return array();
        $product_db = $this->_db->table($this->db_b2c_products);
        //兼容in查询
        foreach ($where as  $key=>$value){
            if($value[0] == 'in'){
                $product_db->whereIn($key,$value[1]);
                unset($where[$key]);
            }
        }
        return $product_db->select($columns)->where($where)->get()->toArray();
    }

}
