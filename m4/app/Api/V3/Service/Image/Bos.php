<?php


namespace App\Api\V3\Service\Image;


use BaiduBce\Services\Bos\BosClient;
use Neigou\Logger;

class Bos
{
    public function upload($name,$file,$path){
        $config = [
            'credentials' => [
                'ak' => config('neigou.BOS_AK'),
                'sk' => config('neigou.BOS_SK'),
            ],
            'endpoint' => config('neigou.BOS_ENDPOINT'),
        ];
         $client = new BosClient($config);
         $bucket = config('neigou.BOS_BUCKET');

         $orgPath = $path . '/' . date('Ymd') . '/' . $name;

         try{
             $client->putObjectFromFile($bucket , $orgPath, $file);
             return config('neigou.CDN_WEB_NEIGOU_WWW').'/'.$orgPath;
         } catch (\Exception $e){
            Logger::General('upload.bos.err',['remark'=>'bos_up_err','config'=>$config,'bucket'=>$bucket,'res'=>$e->getMessage()]);
         }
    }

}