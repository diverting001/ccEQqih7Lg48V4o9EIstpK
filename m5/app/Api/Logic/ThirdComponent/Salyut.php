<?php

namespace App\Api\Logic\ThirdComponent;

class Salyut
{
    /**
     * 获取组件
     * @param $type
     * @param $extendData
     * @return array|mixed
     */
    public function getComponentInfo($type, $extendData = array())
    {
        $return = array();
        if (!in_array($type, array('evaluate', 'express'))) {
            return $return;
        }
        //请求salyut
        $client = new \Neigou\Curl();
        $requestData = array(
            'type'=>$type,
            'extend_data'=>json_encode($extendData)
        );
        $requestData['class_obj'] = 'SalyutComponent';
        $requestData['method'] = 'getComponentInfo';
        $requestData['token'] = \App\Api\Common\Common::GetSalyutSign($requestData);
        $res = $client->Post(config('neigou.SALYUT_DOMIN'). '/OpenApi/apirun/', $requestData);
        $res = json_decode($res, true);
        if ($res['Result'] == 'true' && !empty($res['Data'])) {
            $return = $res['Data'];
        }

        return  $return;
    }

}
