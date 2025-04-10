<?php
namespace App\Api\V3\Service\Pricing;
use App\Api\Model\Goods\Product;
/**
 * 商品价格
 * @version 0.1
 * @package ectools.lib.api
 */

class ProductPrice{

    /*
     * @todo 获取商品基准价格
     */
    public function GetProductsPrice($product_bn_list){
        if(empty($product_bn_list)) return array();
        foreach ($product_bn_list as $k=>$product_bn){
            $product_bn_list[$k]    = addslashes($product_bn);
        }
        $product_bns    = "'".implode("','",$product_bn_list)."'";
        $product_mode   = app::get('b2c')->model('products');
        $sql = "select p.bn as product_bn,p.price,p.point_price,p.mktprice,g.cat_id,gc.cat_path,g.brand_id,p.goods_id,p.cost from sdb_b2c_products as p 
                  left join sdb_b2c_goods as g on g.goods_id = p.goods_id 
                  left join sdb_b2c_goods_cat as gc on gc.cat_id=g.cat_id where p.bn in ({$product_bns})";
        $product_list   = $product_mode->db->select($sql);
        if(empty($product_list)) return array();
        $goods_id_list  = array();
        //获取货品所在商城
        $mall_goods_mapping = array();
        foreach ($product_list as $k=>$v){
            $goods_id_list[$v['goods_id']]  = $v['goods_id'];
        }
        $goods_mode     = app::get('b2c')->model('goods');
        $mall_goods_list    = $goods_mode->GetMallGoodsData($goods_id_list);
        if(!empty($mall_goods_list)){
            foreach ($mall_goods_list as $mall_goods){
                $mall_goods_mapping[$mall_goods['goods_id']][] = intval($mall_goods['mall_id']);
            }
        }
        //货品数据
        if(!empty($product_list)){
            foreach ($product_list as $k=>&$v){
                $v['mall_list'] = isset($mall_goods_mapping[$v['goods_id']])?$mall_goods_mapping[$v['goods_id']]:array();
                $this->point_price($v);
            }
        }
        //货品所在微仓数据
        $product_branch_mapping = array();
        $branch_product_mode    = app::get('b2c')->model('branch_product');
        $branch_product_list    = $branch_product_mode->getList('branch_id,product_bn,price,cost,mktprice',array('product_bn'=>$product_bn_list));
        if(!empty($branch_product_list)){
            foreach ($branch_product_list as $branch_product){
                $product_branch_mapping[$branch_product['product_bn']][]  = array(
                    'branch_id' => $branch_product['branch_id'],
                    'price' => $branch_product['price'],
                    'cost' => $branch_product['cost'],
                    'mktprice' => $branch_product['mktprice']
                );
            }
        }
        //货品所在商品池
        $sql = "select product_bn,container_id from mall_container_products where product_bn in ({$product_bns})";
        $container_product_list = $product_mode->db->select($sql);
        $container_product_mapping  = array();
        if(!empty($container_product_list)){
            foreach ($container_product_list as $container_product){
                $container_product_mapping[$container_product['product_bn']][]   = $container_product['container_id'];
            }
        }

        foreach ($product_list as $key=>$product_info){
            $branch_list    = array();
            $container_list    = array();
            if(isset($product_branch_mapping[$product_info['product_bn']])){
                foreach ($product_branch_mapping[$product_info['product_bn']] as $branch_product){
                    $mapping_key    = $branch_product['price'].'_'.$branch_product['cost'].'_'.$branch_product['mktprice'];
                    if(!isset($branch_list[$mapping_key])){
                        $branch_list[$mapping_key]['price']  = $branch_product;
                        $branch_list[$mapping_key]['branch_list'][]  = $branch_product['branch_id'];
                    }else{
                        $branch_list[$mapping_key]['branch_list'][]  = $branch_product['branch_id'];
                    }
                }
                $branch_list    = array_values($branch_list);
            }
            //商品池
            if(isset($container_product_mapping[$product_info['product_bn']])){
                $container_list = $container_product_mapping[$product_info['product_bn']];
            }
            $product_list[$key]['branch_list']  = $branch_list;
            $product_list[$key]['container_list']  = $container_list;
        }
        return $product_list;
    }


    /*
     * @todo 获取product的积分价
     */
    private function point_price(&$row){
        if(!is_numeric($row['point_price']) || $row['point_price'] <= 0.01){
            if($row['cat_path'] != ','){
                $parent_cat = explode(',',trim($row['cat_path'],','));
                $parent_cat = current($parent_cat);
            }else{
                $parent_cat  = $row['cat_id'];
            }
            $this->SetDiscount();
            if(array_key_exists($parent_cat,$this -> discount)){
                $cat_discount = $this -> discount[$parent_cat];
                if($cat_discount['point_discount'] > 0){
                    $row['point_price'] = $row['price'] * $cat_discount['point_discount'];
                }
            }else{
                $row['point_price'] = $row['price'];
            }
        }

    }

    private function SetDiscount(){
        if(empty($this->discount)){
            $_discount = app::get('b2c') -> model('cat_discount');
            $this -> discount = $_discount -> getFormatList('*');
        }
    }


    /*
     * @todo 获取商品价格
     */
    public function GetProductsPriceV2($product_bn_list){
        if(empty($product_bn_list)) return array();
        $product_bns    = "'".implode("','",$product_bn_list)."'";
        $product_mode   = app::get('b2c')->model('products');
        $sql = "select p.bn as product_bn,p.price,p.point_price,p.mktprice,g.cat_id,gc.cat_path,g.brand_id,p.goods_id,p.cost from sdb_b2c_products as p 
                  left join sdb_b2c_goods as g on g.goods_id = p.goods_id 
                  left join sdb_b2c_goods_cat as gc on gc.cat_id=g.cat_id where p.bn in ({$product_bns})";
        $temp_product_list   = $product_mode->db->select($sql);
        if(empty($temp_product_list)) return array();
        //货品数据
        $product_list = array();
        if(!empty($temp_product_list)){
            foreach ($temp_product_list as $k=>$v){
                $this->point_price($v);
                $product_list[$v['goods_id']]   = $v;
            }
        }
        return $product_list;
    }

    /*
     * @todo 获取商品信息列表
     */
    public function GetProductList($product_bn_list = array()){
        $product_list = [];
        if(empty($product_bn_list)) return $product_list;
        if(!is_array($product_bn_list)) $product_bn_list = [$product_bn_list];
        $mdl_product    = new Product();
        $where  = [
            'bn'    => ['in',$product_bn_list],
        ];
        $columns    = ['bn as product_bn','price','point_price','mktprice','cost'];
        //查询货品数据
        $product_list   = $mdl_product->GetProductList($where,$columns);
        return $product_list;
    }
}
