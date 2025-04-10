<?php
namespace App\Api\Logic\AssetOrder;
use App\Api\Common\Common;

/**
 * Class SplitPrice
 * @package App\Api\Logic\Product
 * 根据 券服务返回的结果 分拆价格
 */
# 计算服务 商品分拆
class SplitPrice
{
    public static  function test() {
        $assetList = [
            [
                'voucher_id'=> 'v1' ,
                'match_use_money' => '945.010' ,
                'type' =>'point' ,
                'products' => [
                    'JD-1038225' => 	'47.900',
                    'JD-1095596'	=> '55.790' ,
                    'JD-1203982'	=> '510.010' ,
                    'JD-2015246'	=> '127.950' ,
                    'JD-2490446' => 	'84.990' ,
                    'JD-4130913'=>	'99.490' ,
                    'JD-5267006' =>	'18.880'
                ] ,
            ]
        ] ;
        return $assetList ;
    }
    public static function  Calculate($assetList)
    {
        bcscale(2) ;
        //参与使用券的商品总额
        if(empty($assetList)) {
            return true ;
        }
        $discount_voucher = '0' ;
        $assetListArr = [] ;
        foreach ($assetList as  $assetInfo) {
            $vid = $assetInfo['voucher_id'] ;
            $matchUseMoney =  $assetInfo['match_use_money'] ;
            // 优惠券折扣
            $discount_voucher = bcadd($discount_voucher ,$matchUseMoney);
            //参与使用券的商品总额
            $goodsSubtotal = Common::bcfunc(array_values($assetInfo['products']) ,'+') ;
            // 商品总价小于 0 则 不分摊
            if (bccomp($goodsSubtotal ,'0') <= 0 ) {
                continue;
            }
            // 运费是按照数量比例分摊
            if ($goodsSubtotal < $matchUseMoney && $assetInfo['type'] != 'freight') {
                $matchUseMoney = $goodsSubtotal;
            }
            $productDiscount = array();
            $count  = count($assetInfo['products']) ;
            // 排序 数值最大的在最后
            asort($assetInfo['products']) ;
            $i = 0 ;
            foreach ($assetInfo['products'] as $bn => $productPrice) {
                // 最后一个 计算
                $vdiscount = isset($assetListArr[$bn]['voucher_discount']) ? $assetListArr[$bn]['voucher_discount']:'0' ;
                $assetListArr[$bn]['amount']  = $productPrice ;
                if($i === $count - 1) {
                    $productDiscountAmount =  Common::bcfunc(array_values($productDiscount) ,'+') ;
                    $productVoucherDiscount =  bcsub($matchUseMoney, $productDiscountAmount) ;
                    $assetListArr[$bn]['voucher_discount'] = bcadd($vdiscount, $productVoucherDiscount);
                    $assetListArr[$bn]['asset_list'][$vid] = $productVoucherDiscount ;
                    break ;
                }
                // a/b*c == c/b*a
                if($assetInfo['type'] == 'freight') {
                    $productVoucherDiscount = Common::number2price(bcmul(bcdiv($matchUseMoney ,$goodsSubtotal,5),$productPrice,5) ,0,0);
                } else {
                    $productVoucherDiscount = Common::number2price(bcmul(bcdiv($matchUseMoney ,$goodsSubtotal,5),$productPrice,5) ,2,2);
                }
                //$productVoucherDiscount = Common::number2price($productPrice / $goodsSubtotal * $matchUseMoney) ;
                // 多张券累加
                if(isset($productDiscount[$bn])) {
                    $productDiscount[$bn]  =  bcadd($productDiscount[$bn], $productVoucherDiscount);
                } else {
                    $productDiscount[$bn]   = $productVoucherDiscount;
                }
                $assetListArr[$bn]['voucher_discount'] = bcadd($vdiscount, $productVoucherDiscount);
                $assetListArr[$bn]['asset_list'][$vid] = $productVoucherDiscount;
                $i ++ ;
            }
        }
        return [
            'total' => Common::number2price($discount_voucher) ,
            'asset_list' => $assetListArr ,
        ] ;
    }
}
