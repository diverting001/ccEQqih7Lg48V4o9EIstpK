<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\V3\Service\Pricing\Setprice ;
use App\Api\V3\Service\Pricing\GetPrice;

class Pricingset extends Command
{
    protected $signature = 'Pricingset {--action=} {--bns=}';

    protected $description = '缓存定价redis';

    public function __construct()
    {
        parent::__construct();
    }
    public function handle()
    {
        $action = $this->option('action');
        if(empty($action)) {
            $array = ["insert_pricing",'up_pricing','get_price' ,'product_price_create' ,'contrast'] ;
            echo "--action= \n" ;
            print_r($array) ;
            exit(1) ;
        }
        $setPriceObj = new Setprice() ;
        if ($action == 'insert_pricing') {
            $setPriceObj->InsertPricing();
        } else if ($action == 'up_pricing') {
            $setPriceObj->UpPricing();
        } else if ($action == 'get_price') {
            $products_sync = new GetPrice();
            $bn_str = $this->option('bns');
            $bns = explode(',', trim($bn_str));
            $productList = array();
            foreach ($bns as $bn) {
                $productList[] = array('product_bn' => trim($bn));
            }
            $stages =  array();
            $a = $products_sync->GetPrice($productList, $stages);
            print_r($a);
        } else if ($action == 'product_price_create') {
            $bn_str = $this->option('bns');
            $bns = explode(',', $bn_str);
            $res =  $setPriceObj->CreateCache($bns);
            $msg =  $res == true ? "执行成功" : "执行失败";
            echo ($msg .'----result=' . json_encode(['res'=>$res ,'bns' => $bns]) ) ,"\n";
        }else if($action == 'update_price_rule'){
            $time_start = microtime(true);
            //更新redis中的规则数据
            $type = [1,2];
            foreach ($type as $type_v){
                $setPriceObj->UpdatePriceRule($type_v);
            }
            $end_start = microtime(true);
            $times = $end_start - $time_start;
           echo '更新redis中的规则数据，运行时间：'. $times .' s'.PHP_EOL;
        } else if ($action == 'contrast') {
            $setPriceObj->PriceDataDisparity();
        }
    }
}


