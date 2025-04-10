<?php
namespace app\Api\Model\Goods;

use App\Api\Model\BaseModel;

class Goods extends  BaseModel
{

    public function GetMallGoodsData($goods_ids){
        $new_list   = array();
        if(empty($goods_ids)) return $new_list;
        $sql    = "select mg.mall_id,mg.goods_id,mg.tag_id,gt.tag_name,gt.tag_class,mg.subtitle,mm.mall_bn,mg.vmall_cat_id,mm.type from mall_module_mall_goods as mg
                        left join mall_module_mall as mm on mm.id=mg.mall_id
                        left join mall_b2c_goods_tag as gt on mg.tag_id=gt.tag_id where mg.goods_id in (".implode(',',$goods_ids).")";
        $list   = $this->select($sql);
        if(!empty($list)){
            foreach ($list as $k=>$v){
                $new_list[$v['goods_id']]   = $v;
            }
        }
        return $list;
    }

}
