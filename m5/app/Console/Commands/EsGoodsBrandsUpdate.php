<?php

namespace App\Console\Commands;

use App\Api\V3\Service\Search\Datasource\GoodsOutletEs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EsGoodsBrandsUpdate extends Command
{
    protected $signature = 'EsGoodsBrandsUpdate';
    protected $description = '批量处理品牌logo地址更新ES';

    public function handle()
    {
        /** @var DB $db_store */
        $db_store = app('api_db')->connection('neigou_store');

        //实例化Es执行类
        $GoodsOutletEs = new GoodsOutletEs();

        //获取全部有logo图的品牌id
        $brand = $db_store->table('sdb_b2c_brand as b')->leftJoin('sdb_image_image as i',
            'b.brand_logo', '=', 'i.image_id')
            ->select(['b.brand_id', 'i.s_url'])
            ->where('s_url', '<>', '')
            ->get()->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        $brand_ids = array_column($brand, 'brand_id');

        //组合更新条件
        $str = "";
        $i = 0;
        foreach ($brand as $brand_v) {
            $s_url = '//' . PSR_CDN_WEB_NEIGOU_WWW_DOMAIN . '/' . $brand_v['s_url'];
            if ($i == 0) {
                $str .= 'if(';
            } else {
                $str .= 'else if(';
            }
            $str .= "ctx._source.brand_id == '{$brand_v['brand_id']}'){ctx._source.brand_logo = '$s_url'}";
            $i++;
        }

        //获取商品信息对应的品牌信息
        $query_data = [
            'query' => [
                'terms' => [
                    'brand_id' => $brand_ids
                ],
            ],
            'script' => [
                'inline' => $str
            ],
        ];
        $GoodsOutletEs->SetQueryData($query_data);
        //组合商品与品牌信息
        //批量更新es数据
        $ret = $GoodsOutletEs->Query('_update_by_query');
        dump($query_data, $ret, $i);
    }
}
