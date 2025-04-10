<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Api\Logic\Cat;
use App\Api\Logic\Redis ;

/**
 * Class CatCache
 * @package App\Console\Commands
 * php artisan  CatCache
 */

class CatCache extends  Command
{

    protected $signature = 'CatCache';
    protected $description = '生成分类缓存';

    protected $redis_key = 'b2c_mall_goods_cat_hash' ;


    public function handle()
    {
        $catModel = new Cat() ;
        $data = $catModel->getAllCats();

        $config = array(
            'host' => config('neigou.REDIS_THIRD_WEB_HOST'),
            'port' => config('neigou.REDIS_THIRD_WEB_PORT'),
            'auth' => config('neigou.REDIS_THIRD_WEB_PWD'),
        );
        $redis_obj = new Redis($config);

        echo "item nums :" . count($data) , "\n" ;
        $i = 0 ;
        foreach ($data as $datum) {
            $i++ ;
            $redis_obj->hset($this->redis_key ,$datum->cat_id ,json_encode($datum)) ;
            if($i %100 == 0) {
                echo $i , "\n" ;
            }
        }

        echo "++++++++++END++++++++++++++++\n" ;
    }


}
