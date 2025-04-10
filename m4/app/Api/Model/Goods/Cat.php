<?php

namespace app\Api\Model\Goods;

class Cat
{
    private $_db = null;
    private $b2cGoodsCat = null;
    private $b2cMallGoodsCat = null;

    /**
     * Cat constructor.
     */
    public function __construct()
    {
        $this->_db             = app('api_db')->connection('neigou_store');
        $this->b2cGoodsCat     = 'sdb_b2c_goods_cat';
        $this->b2cMallGoodsCat = 'sdb_b2c_mall_goods_cat';
    }

    public function getCatListByParentId($parentId = 0)
    {
         return $this->_db->table($this->b2cGoodsCat)->where(['parent_id'=>$parentId])->get()->all();
    }


    public function getMallCatListByParentId($parentId = 0)
    {
        return $this->_db->table($this->b2cMallGoodsCat)->where(['parent_id'=>$parentId])->get()->all();
    }

}
