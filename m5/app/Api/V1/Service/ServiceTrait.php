<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 16:14
 */

namespace App\Api\V1\Service;


use App\Api\Common\Controllers\BaseController;

trait ServiceTrait
{
    private function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }

    /**
     * 返回状态闭包
     * @param $data
     * @param  string  $msg
     * @param  int  $code
     * @return \Closure
     */
    private function outputFormat($data, $msg = '请求成功', $code = 0)
    {
        return function (BaseController $baseController) use ($data, $msg, $code) {
            $baseController->setErrorMsg($msg);
            return $baseController->outputFormat($data, $code);
        };
    }
}
