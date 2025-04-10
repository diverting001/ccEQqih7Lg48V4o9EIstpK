<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-07-06
 * Time: 13:21
 */

namespace App\Api\Common\Controllers;

use Illuminate\Http\Request;

class BaseController
{
    /**
     * 获取Request数据数组
     * @param Request $request
     * @return array|mixed
     */
    public function getContentArray(Request $request)
    {
        $content = $request->getContent();
        return $content ? json_decode($content, true) : [];
    }

    /**
     * @param $data
     * @param int $code
     * @return array
     */
    public function outputFormat($data, $code = 0)
    {
        $curRequest = app('request');
        $out_data = array();
        $out_data['error_code'] = $this->getStatus($code);
        $out_data['error_detail_code'] = $code;
        $out_data['error_msg'] = $curRequest->businessData['error_msg'] ?? [];
        $out_data['debug_msg'] = $curRequest->businessData['debug_msg'] ?? [];
        $out_data['trace_id'] = $curRequest->businessData['traceId'] ?? '';
        $out_data['timestamp'] = $curRequest->businessData['timestamp'] ?? '';
        $out_data['timestamp_end'] = $this->getTimestamp();
        $out_data['data'] = $data;

        return $out_data;
    }

    /**
     * @param $msg
     */
    public function setErrorMsg($msg)
    {
        $curRequest = app('request');
        $curRequest->businessData['error_msg'][] = $msg;
    }

    /**
     * @param $msg
     */
    public function setDebugMsg($msg)
    {
        $curRequest = app('request');
        $curRequest->businessData['debug_msg'][] = $msg;
    }

    public function getStatus($code)
    {
        if ($code == 0) {
            return 'SUCCESS';
        } else {
            if ($code >= 400 && $code < 500) {
                return 'FAIL';
            } else {
                if ($code >= 500 && $code < 600) {
                    return 'ERROR';
                } else {
                    return 'ERROR';
                }
            }
        }
    }

    public function getTimestamp()
    {
        $mtime = explode(' ', microtime());
        return $mtime[1] + $mtime[0];
    }

}
