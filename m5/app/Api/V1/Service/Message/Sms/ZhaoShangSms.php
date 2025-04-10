<?php

    namespace App\Api\V1\Service\Message\Sms;

    use App\Api\V1\Service\Message\MessageHandler;
    use Neigou\RedisNeigou;

    class ZhaoShangSms extends MessageHandler
    {

        public const PLATFORM_NAME = "招商云Sms";
        const CACHE_TOKEN_KEY = "ZhaoShangSmsKey_";

        //token存于redis 如果没有那么生成存入
        private function getToken()
        {
            $redis = new RedisNeigou();
            $token = $redis->_redis_connection->get(self::CACHE_TOKEN_KEY);

            if ( empty($token) ) {
                //获取并存储
                $tokenUrl = $this->config[ 'get_token_url' ];
                $result = $this->httpClient($tokenUrl, ['grant_type' => "password", 'username' => $this->config[ 'client_id' ], 'password' => $this->config[ 'client_secret' ]], 'post', ['Accept:text/plain;charset=utf-8', 'Content-Type:application/json', "Authorization" => "Basic " . base64_encode($this->config[ 'client_id' ] . ":" . $this->config[ 'client_secret' ]),]);
                //校验结果
                $result = json_decode($result, true);
                if ( !isset($result[ 'access_token' ]) ) {
                    \Neigou\Logger::General("ZhaoShangSms_getToken_error", json_encode($result));
                    return '';
                }
                \Neigou\Logger::General("get_token_zhaoshang", json_encode($result));
                $token = $result[ 'access_token' ];
                $redis->_redis_connection->set(self::CACHE_TOKEN_KEY, $token);
                $redis->_redis_connection->expire(self::CACHE_TOKEN_KEY, $result[ 'expires_in' ] - 100);
            }

            return $token;

        }

        protected function errorMap()
        {
            return "";
            // TODO: Implement errorMap() method.
        }

        /**
         * @param $receiver
         * @param $templateRealData
         * @param $params
         *
         * @return void | boolean
         */
        protected function send($receiver, $templateRealData, $params)
        {
            return $this->sendLogic($receiver, $templateRealData, $params);
            // TODO: Implement send() method.
        }

        /**
         * @param $receiver
         * @param $templateRealData
         * @param $params
         * @param $action
         *
         * @return void | boolean|array
         */
        public function sendLogic($receiver, $templateRealData, $params, $action = 'SendSms')
        {
            $token = $this->getToken();
            $paramsInfo = array();
            foreach ( $params[ 'template_param' ] as $v ) {
                $paramsInfo[] = $v;
            }

            $tokenUrl = $this->config[ 'url' ];
            $header = array('appId:'.$this->config['lessee_code'].'_'.$this->config['project_code'], 'timestamp:'.time(), 'Authorization: Bearer ' . $token, 'Content-Type: application/json',);
            $result = $this->toCurl($tokenUrl, ['countryCode' => "86", 'mobile' => $receiver, 'params' => $paramsInfo, 'templateId' => $templateRealData->template_data, 'signId' => $this->config[ 'sign_id' ]], $header);
            $resultInfo = json_decode($result, true);
            if ( isset($resultInfo['message']) && $resultInfo[ 'message' ] == 'SUCCESS' ) {
                return $this->response(self::CODE_SUCCESS, $resultInfo);
            } else {
                \Neigou\Logger::General("message_zhaoshangsms_fail", ['result' => $result, 'token' => $token, 'param' => ['countryCode' => "86", 'mobile' => $receiver, 'params' => $paramsInfo, 'templateId' => $templateRealData->template_data, 'signId' => $this->config[ 'sign_id' ]], 'header' => $header,]);
                return $this->response(self::CODE_ERROR, $resultInfo);
            }


        }

        public function checkChildParam($param)
        {
            // TODO: Implement checkChildParam() method.
            return true;

        }

        /**
         * @desc 发送短信，job会调用这个方法
         *
         * @param $receivers
         * @param $templateRealData
         * @param $params
         */
        protected function batchSend($receivers, $templateRealData, $params)
        {
            // TODO: Implement batchSend() method.
            $result = array();
            foreach ($receivers as $mobile) {
                $result[$mobile] = $this->sendLogic($mobile, $templateRealData, $params);
            }
            return $result;

        }


        private function toCurl($url, $param, $header = array())
        {

            $curl = curl_init();

            curl_setopt_array($curl, array(CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => json_encode($param), CURLOPT_HTTPHEADER => $header,));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        }


    }
