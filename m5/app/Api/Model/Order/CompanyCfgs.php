<?php


namespace App\Api\Model\Order;

use App\Api\Model\BaseModel;

class CompanyCfgs extends  BaseModel
{
    public $_db_store;
    public $table = 'sdb_b2c_payment_cfgs';

    public function __construct()
    {
        $this->_db_store = app('api_db')->connection('neigou_store');
    }

    // 获取所有的分类信息
    public function getCompanyCfgs ($key)
    {
        if (empty($key[0])) {
            return false;
        }
        $where['app_key'] = $key[0];
        $where['status'] = 'true';
        $data = $this->_db_store->table($this->table)->select(['payment_cfgs_id','payee'])->where($where)->get()->toArray();
        return $data[0];
    }
}
