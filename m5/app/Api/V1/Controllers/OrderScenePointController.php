<?php

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;

class OrderScenePointController extends BaseController
{
    /**
     * @param  Request  $request
     *
     * @return array
     */
    public function Get(Request $request)
    {
        $request_data = $this->getContentArray($request);

        if (empty($request_data['order_id']) || empty($request_data['product_bn']) || empty($request_data['goods_bn'])) {
            $this->setErrorMsg('miss prams');

            return $this->outputFormat([], 400);
        }

        $data['time']       = time();
        $data['order_id']   = $request_data['order_id'];
        $data['product_bn'] = $request_data['product_bn'];
        $data['goods_bn']   = $request_data['goods_bn'];

        $sendData = [
            'data' => base64_encode(json_encode($data)),
        ];

        $sendData['token'] = \App\Api\Common\Common::GetEcStoreSign($sendData);

        $curl      = new \Neigou\Curl();
        $resultStr = $curl->Post(config('neigou.STORE_DOMIN').'/openapi/orderScenePoint/getOrderSceneRow', $sendData);
        $result    = trim($resultStr, "\xEF\xBB\xBF");
        $result    = json_decode($result, true);
        if ($result['Result'] == 'true') {
            return $this->outputFormat($result['Data']);
        }

        return $this->outputFormat([], '441');
    }

    public function GetListByOrderId(Request $request)
    {
        $request_data = $this->getContentArray($request);

        if (empty($request_data['order_id'])) {
            $this->setErrorMsg('miss prams');

            return $this->outputFormat([], 400);
        }

        $data['time']       = time();
        $data['order_id']   = $request_data['order_id'];

        $sendData = [
            'data' => base64_encode(json_encode($data)),
        ];

        $sendData['token'] = \App\Api\Common\Common::GetEcStoreSign($sendData);

        $curl      = new \Neigou\Curl();
        $resultStr = $curl->Post(config('neigou.STORE_DOMIN').'/openapi/orderScenePoint/getOrderSceneByOrderId', $sendData);
        $result    = trim($resultStr, "\xEF\xBB\xBF");
        $result    = json_decode($result, true);
        if ($result['Result'] == 'true') {
            return $this->outputFormat($result['Data']);
        }

        return $this->outputFormat([], '441');
    }


    /**
     * @param  Request  $request
     *
     * @return array
     */
    public function Save(Request $request)
    {
        $request_data = $this->getContentArray($request);
        if (empty($request_data['order_scene_list'])) {
            $this->setErrorMsg('miss prams');

            return $this->outputFormat([], 400);
        }

        $data['time']             = time();
        $data['order_scene_list'] = $request_data['order_scene_list'];

        $sendData = [
            'data' => base64_encode(json_encode($data)),
        ];

        $sendData['token'] = \App\Api\Common\Common::GetEcStoreSign($sendData);

        $curl      = new \Neigou\Curl();
        $resultStr = $curl->Post(config('neigou.STORE_DOMIN').'/openapi/orderScenePoint/saveOrderSceneRow', $sendData);

        $result = trim($resultStr, "\xEF\xBB\xBF");
        $result = json_decode($result, true);

        if ($result['Result'] == 'true') {
            return $this->outputFormat($result['Data']);

        }

        return $this->outputFormat([], '443');
    }
}
