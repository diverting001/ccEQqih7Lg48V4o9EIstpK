<?php
namespace App\Console\Model;
use App\Api\Model\BaseModel ;

class PricingRange extends  BaseModel{

    protected $table = 'server_pricing_range' ;
    protected $primaryKey = 'range_id' ;

    /*
     * @todo 获取商品范围信息
     */
    public function GetRangeInfo($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range where range_id = '".intval($range_id)."'";
        $range_info = $this->select($sql);

        if(!empty($range_info)){
            return $range_info[0];
        }else{
            return array();
        }
    }

    /**
     * 基于范围id数组批量查询范围信息
     * @param array $range_ids
     * @return array
     */
    public function GetRangeInfoByRangeIds(array $range_ids)
    {
        if(empty($range_ids)) return array();
        return $this->query()->whereIn('range_id',$range_ids)->get()->toArray();
    }

    /*
     * @todo 获取商品范围所包含的商品bn
     */
    public function GetRangeroduct($range_data){
        $product_bns    = array();
        if(empty($range_data)) return $product_bns;
        switch ($range_data['type']){
            case 2:
                if(!empty($range_data['value'])){
                    $sql = "select p.bn from sdb_b2c_products as p
                        inner join sdb_b2c_goods as g on g.goods_id = p.goods_id where g.brand_id in(".implode(',',$range_data['data']).")";
                    $products_list  = $this->select($sql);
                    if(!empty($products_list)){
                        foreach ($products_list as $v){
                            $product_bns[]   = $v['bn'];
                        }
                    }
                }
                break;
            case 4:
                $product_bns    = $range_data['value'];
        }
        return $product_bns;
    }

    /*
     * @todo 获取范围信息
     */
    public function GetRangeData($range_id){
        if(empty($range_id)) return false;
        $range_info = $this->GetRangeInfo($range_id);
        if(empty($range_info)) return array();
        $range_data = array();
        switch ($range_info['type']){
            case 2:
                $data   = $this->GetRangeBrand($range_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $range_data[]   = $v['brand_id'];
                    }
                }
                break;
            case 3:
                $data   = $this->GetRangeCat($range_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $range_data[]   = $v['cat_id'];
                    }
                }
                break;
            case 4:
                $data   = $this->GetRangeProduct($range_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $range_data[]   = $v['product_bn'];
                    }
                }
                break;
            case 5:
                $data   = $this->GetRangeSupplier($range_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $range_data[]   = $v['supplier_bn'];
                    }
                }
                break;
            case 6:
                $data   = $this->GetRangeMall($range_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $range_data[]   = $v['mall_id'];
                    }
                }
                break;
            case 7:
                $data   = $this->GetRangeContainer($range_id);
                if(!empty($data)){
                    foreach ($data as $k=>$v){
                        $range_data[]   = $v['container_id'];
                    }
                }
                break;
        }
        $return_data    = array(
            'type'  => $range_info['type'],
            'value' => $range_data
        );
        return $return_data;
    }



    /*=============================单独商品====================================*/
    /*
     * @todo 获取商品范围关联商品bn
     */
    public function GetRangeProduct($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range_products where range_id = '".intval($range_id)."'";
        $products_list = $this->select($sql);
        return $products_list;
    }
    /**
     * @todo 根据范围id批量获取商品范围关联商品bn
     */
    public function GetRangeProductByRangeIds($range_ids){
        if(empty($range_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_range_products')
            ->whereIn('range_id',$range_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
//        return $this->setTable('server_pricing_range_products')->whereIn('range_id',$range_ids)->get()->toArray();
    }



    /*=============================品牌====================================*/
    /*
     * @todo 获取商品范围关联品牌
     */
    public function GetRangeBrand($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range_brand where range_id = '".intval($range_id)."'";
        $brand_list = $this->select($sql);
        return $brand_list;
    }

    /**
     * 根据范围id批量获取商品范围关联品牌
     * @param $range_ids
     * @return array
     */
    public function GetRangeBrandByRangeIds($range_ids){
        if(empty($range_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_range_brand')
            ->whereIn('range_id',$range_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
        //return $this->setTable('server_pricing_range_brand')->whereIn('range_id',$range_ids)->get()->toArray();
    }

    /*
    * @todo 删除商品范围关联商品bn
    */

    private function DelRangeBrand($range_id){
        if(empty($range_id)) return false;
        $sql = "delete from  server_pricing_range_brand where range_id = ".intval($range_id);
        $res = $this->exec($sql);
        return $res;
    }

    /*
     * @todo 保存商品范围关联商品bn
     */
    private function AddRangeBrand($range_id,$save_data){
        if(empty($save_data)) return false;
        $time   = time();
        foreach ($save_data as $k=>$v){
            $save_data_list[]  = "('{$v}',{$range_id},{$time},{$time})";
        }
        $sql = "INSERT INTO `server_pricing_range_brand` (`brand_id`,`range_id`,`create_time`,`update_time`) VALUES ".implode(',',$save_data_list);
        $res = $this->exec($sql);
        return $res;
    }

    /*=============================分类====================================*/

    /*
     * @todo 获取商品范围关联分类
     */
    public function GetRangeCat($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range_cat where range_id = '".intval($range_id)."'";
        $cat_list = $this->select($sql);

        return $cat_list;
    }


    /*=============================供应商====================================*/
    /*
     * @todo 获取商品范围关联供应商
     */
    public function GetRangeSupplier($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range_supplier where range_id = '".intval($range_id)."'";
        $cat_list = $this->select($sql);
        return $cat_list;
    }
    /**
     * @todo 根据范围id批量获取商品范围关联供应商
     */
    public function GetRangeSupplierByRangeIds($range_ids){
        if(empty($range_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_range_supplier')
            ->whereIn('range_id',$range_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
//        return $this->setTable('server_pricing_range_supplier')->whereIn('range_id',$range_ids)->get()->toArray();
    }


    /*=============================mall商城====================================*/

    /*
     * @todo 获取商品范围关联mall商城
     */
    public function GetRangeMall($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range_mall where range_id = '".intval($range_id)."'";
        $cat_list = $this->select($sql);
        return $cat_list;
    }

    /**
     * 根据范围id批量获取商品范围关联mall商城
     * @param $range_ids
     * @return array
     */
    public function GetRangeMallByRangeIds($range_ids){
        if(empty($range_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_range_mall')
            ->whereIn('range_id',$range_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
    }


    /*
     * @todo 保存商品范围关联商品bn
     */
    private function AddRangeMall($range_id,$save_data){
        if(empty($save_data)) return false;
        $time   = time();
        foreach ($save_data as $k=>$v){
            $save_data_list[]  = "('{$v}',{$range_id},{$time},{$time})";
        }
        $sql = "INSERT INTO `server_pricing_range_mall` (`mall_id`,`range_id`,`create_time`,`update_time`) VALUES ".implode(',',$save_data_list);
        $res = $this->exec($sql);
        return $res;
    }

    /*=============================mall商城====================================*/

    /*
     * @todo 获取商品范围关联商品池
     */
    public function GetRangeContainer($range_id){
        if(empty($range_id)) return array();
        $sql = "select * from server_pricing_range_container where range_id = '".intval($range_id)."'";
        $cat_list = $this->select($sql);
        return $cat_list;
    }

    /**
     * @todo 根据范围id批量获取商品范围关联商品池
     */
    public function GetRangeContainerByRangeIds($range_ids){
        if(empty($range_ids)) return array();
        return app('api_db')->connection('neigou_store')
            ->table('server_pricing_range_container')
            ->whereIn('range_id',$range_ids)
            ->get()->map(function ($value){
                return (array)$value;
            })->toArray();
//        return $this->setTable('server_pricing_range_container')->query()->whereIn('range_id',$range_ids)->get()->toArray();
    }

    /*
     * @todo 删除商品范围关联商品池
     */

    private function DelRangeContainer($range_id){
        if(empty($range_id)) return false;
        $sql = "delete from  server_pricing_range_container where range_id = ".intval($range_id);
        $res = $this->exec($sql);
        return $res;
    }

    /*
     * @todo 保存商品范围关联商品池
     */
    private function AddRangeContainer($range_id,$save_data){
        if(empty($save_data)) return false;
        $time   = time();
        foreach ($save_data as $k=>$v){
            $save_data_list[]  = "('{$v}',{$range_id},{$time},{$time})";
        }
        $sql = "INSERT INTO `server_pricing_range_container` (`container_id`,`range_id`,`create_time`,`update_time`) VALUES ".implode(',',$save_data_list);
        $res = $this->exec($sql);
        return $res;
    }

    /**
     *
     * 定价变更记录
     *
     * @param $params
     * @param $info
     * @return array|false
     */
    public function addPricingRecord($params, $info = '')
    {
        $info = is_array($info)?json_encode($info):$info;

        $time = time();
        $sql = "INSERT INTO `server_pricing_range_set_record` (`params`,`info`,`status`,`create_time`) VALUES ('".$params."','{$info}','READY',{$time})";
        return  $this->exec($sql);
    }

    /**
     *
     * 获取记录
     *
     * @param  string  $status
     * @return mixed
     */
    function getPricingRecord($status = 'READY')
    {
        // $sql = "select * from server_pricing_range_set_record where `status` = '{$status}'";
        // return $this->db->selectrow($sql);
        $result =   $this->setTable("server_pricing_range_set_record")->getInfoRow(['status' => $status ]) ;
        $this->setTable($this->table) ;
        return $result ;
    }

    /**
     *
     * 获取记录
     *
     * @param int  $id
     * @return mixed
     */
    function getPricingRecordById($id)
    {
        if (!$id) {
            return false;
        }
        $result  = $this->setTable("server_pricing_range_set_record")->getInfoRow(['id' => $id ]) ;
        $this->setTable($this->table) ;
        return $result ;
        // $sql = "select * from server_pricing_range_set_record where `id` = {$id}";
        //return $this->db->selectrow($sql);
    }



    /**
     * @param $id
     * @param $info
     * @return array|false
     */
    function upPricingRecordInfo($id, $info)
    {
        if (!$id) {
            return false;
        }

        $info = is_array($info) ? json_encode($info) : $info;

        $time = time();

        $sql = "update server_pricing_range_set_record set `info` = '{$info}',`update_time` = {$time}  where id = {$id}";
        $res = $this->exec($sql);
        if ($res && $res['rs']) {
            return true;
        }
        return false;
    }

    /**
     * @param $params
     * @return false|mixed
     */
    function getReadyRecordByParam($params)
    {
        if (!$params) {
            return false;
        }
        //$sql = "select * from server_pricing_range_set_record where `params` = '{$params}' AND `status` in ('READY')";
        //return $this->db->selectrow($sql);
        $result =  $this->setTable("server_pricing_range_set_record")->getInfoRow(['params' => $params  ,'status' => 'READY' ]) ;
        $this->setTable($this->table) ;
        return $result ;
    }
}
