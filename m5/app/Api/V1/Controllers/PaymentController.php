<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-06-11
 * Time: 18:53
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;

use App\Api\V1\Service\Bill\Payment;
use Illuminate\Http\Request;
use Neigou\Logger;

class PaymentController extends BaseController
{
    public function GetConfig(Request $request){
        $app_id = $this->getContentArray($request);
        $obj = new Payment();
        $rzt = $obj->getConfigByAppId($app_id);
        if(!$rzt){
            Logger::General('service.payment.get.err',['app_id'=>$app_id,'response'=>$rzt]);
            $this->setErrorMsg('查询失败');
            return $this->outputFormat([],400);
        }else{
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        }
    }

    public function GetAppListByCode(Request $request){
        $code = $this->getContentArray($request);
        $obj = new Payment();
        $rzt = $obj->getAppListByCode($code);
        if(!$rzt){
            Logger::General('service.payment.get.err',['code'=>$code,'response'=>$rzt]);
            $this->setErrorMsg('查询失败');
            return $this->outputFormat([],400);
        }else{
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        }
    }

    public function AddAppListByCode(Request $request){
        $req = $this->getContentArray($request);
        $obj = new Payment();
        $rzt = $obj->addAppList($req);
        if(!$rzt){
            Logger::General('service.payment.get.err',['req'=>$req,'response'=>$rzt]);
            $this->setErrorMsg('查询失败');
            return $this->outputFormat([],400);
        }else{
            $this->setErrorMsg('请求成功');
            return $this->outputFormat(['result'=>$rzt]);
        }

    }

}
