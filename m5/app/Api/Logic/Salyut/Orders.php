<?php

namespace App\Api\Logic\Salyut;

class Orders
{
    private $_session_id = 's_v2';
    protected $_cookies = array();    //cookie信息
    /*
     * @todo 创建订单
     */
    public function Create($order_data, $order_items, &$result_data, $split_info, &$err_msg = '')
    {
        if (empty($order_data) || empty($order_items)) {
            $err_msg = 'salyut预下单缺少 order_data或 order_items关键参数';
            return false;
        }

        //订单货品bn
        $split_product_list = $this->_splitProduct($order_items, $split_info);

        if (empty($split_product_list)){
            return true;
        }

        $extend_data = json_decode($order_data['extend_data'], true);
        $deliver_extend_data = empty($extend_data['deliver_extend_data']) ? array() : json_decode($extend_data['deliver_extend_data'],
            true);

        $preorder_result_list = array();

        if (!empty($split_product_list)) {
            foreach ($split_product_list as $info) {
                $order_id = $info['order_id'];
                $items = $info['items'];
                $salyut_order = array(
                    'external_source_bn' => 'ECSTORE-PRE-'. $order_id,
                    'external_order_bn' => $order_data['order_id'],
                    'external_order_id' => $order_id,
                    'ship_province' => $order_data['ship_province'],
                    'ship_city' => $order_data['ship_city'],
                    'ship_county' => $order_data['ship_county'],
                    'ship_town' => $order_data['ship_town'],
                    'ship_name' => $order_data['ship_name'],
                    'ship_address' => $order_data['ship_addr'],
                    'ship_mobile' => $order_data['ship_mobile'],
                    'ship_phone' => $order_data['ship_tel'],
                    'idcardname' => $order_data['idcardname'],
                    'idcardno' => $order_data['idcardno'],
                    'company_id' => $order_data['company_id'],
                    'member_id' => $order_data['member_id'],
                    'pmt_amount' => $info['pmt_amount'],  // 优惠金额(满减券+免税券)
                    'point_amount' => $order_data['point_amount'],  // 积分支付金额
                    'idcard_zhengmian_pic_url' => empty($extend_data['idcard_zhengmian_pic_url']) ? '' : $extend_data['idcard_zhengmian_pic_url'],
                    'idcard_fanmian_pic_url' => empty($extend_data['idcard_fanmian_pic_url']) ? '' : $extend_data['idcard_fanmian_pic_url'],
                    'isprintprice' => empty($extend_data['isprintprice']) ? '' : $extend_data['isprintprice'],
                    'freight_price' => $info['cost_freight'],
                    'memo' => $info['memo'],
                    'items' => $items,
                    'extend_data' => $order_data['extend_data'],
                );

                if ( ! empty($extend_data['invoice_info']))
                {
                    $key = $salyut_order['external_source_bn'];
                    $this->setInvoiceInfo($key, json_encode($extend_data['invoice_info']));
                }

                $salyut_order['extend_deliver_area'] = $order_data['extend_deliver_area'];
                $salyut_order['extend_send_time'] = $order_data['extend_send_time'];
                $salyut_order['longitude'] = isset($deliver_extend_data['location']['lng']) ? $deliver_extend_data['location']['lng'] : '';   //经度
                $salyut_order['latitude'] = isset($deliver_extend_data['location']['lat']) ? $deliver_extend_data['location']['lat'] : '';    //纬度

                //需要检查订单的供应
                $check_order_supplier_bn = explode(',', config('neigou.CHECK_ORDER_SUPPLIER_BN'));

                //根据第一个商品判断供应商标识
                $supplier_bn = $this->_getItemSupplierBn(current($items));

                if (count($split_product_list) == 1)
                {
                    if (in_array($supplier_bn, $check_order_supplier_bn)) {
                        $preorder_result_list[] = $this->_checkOrder($salyut_order);
                    } else {
                        $preorder_result_list[] = $this->_sendOrder($salyut_order);
                    }
                }
                else
                {
                    //检测是否为需要检查订单的供应订单
                    if (in_array($supplier_bn, $check_order_supplier_bn)) {
                        $salyut_order_url = config('neigou.SALYUT_DOMIN'). '/Home/Orders/checkOrder';
                    } else {
                        //默认请求预下单url
                        $salyut_order_url = config('neigou.SALYUT_DOMIN'). '/Home/Orders/createPreOrder';
                    }
                    $requestData[] = array(
                        'url'       => $salyut_order_url,
                        'data'      => array(
                            'token' => \App\Api\Common\Common::GetSalyutOrderSign($salyut_order),
                            'data' => json_encode($salyut_order),
                        ),
                        'is_post'   => true,
                        'timeout'   => 30,
                    );;

                    if (count($requestData) == count($split_product_list))
                    {
                        $preorder_result_list = $this->_curl_multi_request($requestData);

                        \Neigou\Logger::General('salyut_service_multi_request', array('sender' => $requestData, 'remark' => $preorder_result_list));
                    }
                }
            }
            foreach ($preorder_result_list as $preorder_result)
            {
                if (false === $preorder_result) {
                    $err_msg = '请求salyut预下单结果是false，预下单失败;';
                    return false;
                }

                if (empty($preorder_result['success']) && !empty($preorder_result['msg']))
                {
                    $preorder_msg = json_decode($preorder_result['msg'], true);
                    foreach ($preorder_msg as $real_msg) {
                        if ($real_msg['error_code'] === 'SHOW_ORIGINAL_ERR') {
                            if (!empty($err_msg)) {
                                $err_msg .= ';';
                            }
                            $err_msg .= $real_msg['error_msg'];
                        }
                        if (!empty($real_msg['product_bn_list'])) {
                            foreach ($real_msg['product_bn_list'] as $product_bn) {
                                $result_data[] = $product_bn;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($result_data) || !empty($err_msg)) {
            return false;
        }

        return true;
    }

    public function setInvoiceInfo($key, $json = '', $rand = '', $timeout = 90000)
    {
        $key = 'INVOICE-' . $key . $rand;
        $RedisClient = new \Neigou\RedisClient();
        if (is_object($RedisClient->_redis_connection)) {
            $r = $RedisClient->_redis_connection->setex($key, $timeout, $json);
            if (!$r) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }

    /*
     * @todo 拆分商品
     */
    private function SplitProduct($order_items, $split_order)
    {
        \Neigou\Logger::General('salyut_service_order_SplitProduct', array('sender' => $split_order));
        $return = array();

        $split_product_list = array();
        if (empty($order_items)) {
            return $split_product_list;
        }

        $product_list = array();
        foreach ($order_items as $product)
        {
            $key = self::_createUniqueProductBn($product['product_bn'], $product['item_type'], $product['p_bn']);
            $product_list[$key] = array(
                'bn' => $product['product_bn'],
                'count' => $product['nums'],
                'name' => $product['name'],
                'price' => $product['price'],
                'pmt_amount' => $product['pmt_amount'],
                'cost_freight' => $product['cost_freight'],
                'point_amount' => $product['point_amount'],
                'payed_amount' => $product['payed_amount'],
                'item_type' => $product['item_type'],
                'p_bn' => $product['p_bn'],
            );
        }

        if (empty($split_order)) {
            return $return;
        }

        foreach ($split_order['split_orders'] as $order)
        {
            foreach ($order['items'] as $item)
            {
                $key = self::_createUniqueProductBn($item['bn'], $item['item_type'], $item['p_bn']);
                if (empty($product_list[$key]))
                {
                    continue;
                }
                $supplierBn = current(explode('-', $item['bn']));

                if (in_array($supplierBn, array('JD','YHD','YGSX','KL','YXKA','MRYX','VIP','YX','WM','WMX','WMNG','WMNGX','WMJC','WMJCX','YXSRBT','SF','MI','OFHF','JDHD', 'WMQY','XMLY','SF2','FLHF','JYFL')))
                {
                    if ( ! isset($return[$supplierBn. '-'. $order['order_id']]))
                    {
                        $return[$supplierBn. '-'. $order['order_id']] = array(
                            'order_id' => $order['order_id'],
                            'supplier_bn' => $supplierBn,
                            'cost_freight' => $order['cost_freight'],
                            'memo' => $order['memo'],
                            'pmt_amount' => $order['pmt_amount'],
                        );
                    }

                    $return[$supplierBn. '-'. $order['order_id']]['items'][] = $product_list[$key];
                }
            }
        }

        return $return;
    }


    /*
     * @todo 拆分商品
     */
    private function _splitProduct($order_items, $split_order)
    {
        \Neigou\Logger::General('salyut_service_order_SplitProduct', array('sender' => $split_order));
        $return = array();

        $split_product_list = array();
        if (empty($order_items)) {
            return $split_product_list;
        }

        $product_list = array();
        foreach ($order_items as $product)
        {
            $key = self::_createUniqueProductBn($product['product_bn'], $product['item_type'], $product['p_bn']);
            $product_list[$key] = array(
                'bn' => $product['product_bn'],
                'count' => $product['nums'],
                'name' => $product['name'],
                'price' => $product['price'],
                'pmt_amount' => $product['pmt_amount'],
                'cost_freight' => $product['cost_freight'],
                'point_amount' => $product['point_amount'],
                'payed_amount' => $product['payed_amount'],
                'item_type' => $product['item_type'],
                'p_bn' => $product['p_bn'],
            );
        }

        if (empty($split_order)) {
            return $return['wms_code'] == 'SALYUT' ? $return : array();
        }

        foreach ($split_order['split_orders'] as $order)
        {
            // 过滤非 salyut 订单
            if ($order['wms_code'] != 'SALYUT')
            {
                continue;
            }

            foreach ($order['items'] as $item)
            {
                $key = self::_createUniqueProductBn($item['bn'], $item['item_type'], $item['p_bn']);
                if (empty($product_list[$key]))
                {
                    continue;
                }

                if ( ! isset($return[$order['order_id']]))
                {
                    $return[$order['order_id']] = array(
                        'order_id' => $order['order_id'],
                        'cost_freight' => $order['cost_freight'],
                        'memo' => $order['memo'],
                        'pmt_amount' => $order['pmt_amount'],
                    );
                }
                $return[$order['order_id']]['items'][] = $product_list[$key];
            }
        }

        return $return;
    }

    /*
     * @todo 提交salyut预下单
     */
    private function _sendOrder($salyut_order)
    {
        $curl = new \Neigou\Curl();
        $curl->time_out = 7;
        $request_params = array(
            'token' => \App\Api\Common\Common::GetSalyutOrderSign($salyut_order),
            'data' => json_encode($salyut_order),
        );

        $result = $curl->Post(config('neigou.SALYUT_DOMIN'). '/Home/Orders/createPreOrder', $request_params);
        \Neigou\Logger::General('salyut_service_order_create', array('sender' => $request_params, 'remark' => $result));
        if ($curl->GetHttpCode() != 200) {
            $result = false;
        } else {
            $result = json_decode($result, true);
        }
        return $result;
    }

    /*
     * @todo 提交salyut检查订单
     */
    private function _checkOrder($salyut_order)
    {
        $curl = new \Neigou\Curl();
        $curl->time_out = 15;
        $request_params = array(
            'token' => \App\Api\Common\Common::GetSalyutOrderSign($salyut_order),
            'data' => json_encode($salyut_order),
        );

        $result = $curl->Post(config('neigou.SALYUT_DOMIN'). '/Home/Orders/checkOrder', $request_params);
        \Neigou\Logger::General('salyut_service_order_create', array('sender' => $request_params, 'remark' => $result));
        if ($curl->GetHttpCode() != 200) {
            $result = false;
        } else {
            $result = json_decode($result, true);
        }
        return $result;
    }

    /*
     * @todo 设置版本信息
     */
    protected function SetVersion()
    {
        if (function_exists('config') && config('laravels.server')) {
            $curRequest = app('request');
            $cookies = $curRequest->cookie();
            if ($cookies) {
                foreach ($cookies as $k => $v) {
                    if (strstr($k, 'com_AUTOCI_BRANCH')) {
                        $this->SetCookie($k, $v);
                    }
                }
            }
        } else {
            if (!empty($_COOKIE)) {
                foreach ($_COOKIE as $k => $v) {
                    if (strstr($k, 'com_AUTOCI_BRANCH')) {
                        $this->SetCookie($k, $v);
                    }
                }
            }
        }
    }
    /*
     * @tood 设置cookie信息
     */
    public function SetCookie($k, $v = '')
    {
        if (is_array($k)) {
            foreach ($k as $cookie_k => $cookie_v) {
                if (!is_string($cookie_v)) $cookie_v = json_encode($cookie_v);
                $this->_cookies[$cookie_k] = $cookie_v;
            }
        } else {
            $this->_cookies[$k] = $v;
        }
        return $this;
    }

    //多线程处理
    private function _curl_multi_request($request_data)
    {
        $return = array();
        if (empty($request_data))
        {
            return $return;
        }
        $this->SetVersion();
        //创建handle
        foreach ($request_data as $k => $rv)
        {
            if ( ! isset($rv['url']))
            {
                continue;
            }

            //设置session_id
            $url_parse = parse_url($rv['url']);
            if (stristr($url_parse['host'], '.' . PSR_NEIGOU_COOKIE_DOMAIN)) {
                if ($_COOKIE[$this->_session_id]) {
                    $this->SetCookie($this->_session_id, $_COOKIE[$this->_session_id]);
                }
            }

            $data = isset($rv['data']) ? $rv['data'] : array();
            $is_post = isset($rv['is_post']) ? $rv['is_post'] : NULL;
            $timeout = isset($rv['timeout']) ? $rv['timeout'] : NULL;

            $ch = $this->_curl_multi_handle($rv['url'], $data, $is_post, $timeout);
            if ( ! empty($ch))
            {
                $handle[$k] = $ch;
            }
        }
        //判断handle
        if ( ! isset($handle))
        {
            return $return;
        }
        $active = null;
        $mh = curl_multi_init();
        //增加句柄
        foreach ($handle as $hv)
        {
            curl_multi_add_handle($mh, $hv);
        }
        //阻塞运行处理
        do
        {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK)
        {
            while (curl_multi_exec($mh, $active) === CURLM_CALL_MULTI_PERFORM) ;
            if (curl_multi_select($mh) != -1)
            {
                do
                {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        //关闭全部句柄
        foreach ($handle as $k => $hv)
        {
            $return[$k] = json_decode(curl_multi_getcontent($hv), true);
            curl_multi_remove_handle($mh, $hv);
        }
        curl_multi_close($mh);

        return $return;
    }

    // 创建多线程handle
    private function _curl_multi_handle($url, $data = array(), $isPost = false, $timeout = 30)
    {
        $return = '';
        $url = trim(urldecode($url));
        if ( ! $url)
        {
            return $return;
        }
        $queryStr = '';
        if ( ! empty($data))
        {
            $queryStr   = http_build_query($data, '', '&');
        }
        $ch = curl_init();
        if ($isPost == true)
        {
            curl_setopt($ch, CURLOPT_URL, $url); // 配置网关地址
            curl_setopt($ch, CURLOPT_POST, 1); // 设置post提交
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryStr); // post传输数据
            curl_setopt($ch,CURLOPT_HTTPHEADER,array("content-type: application/x-www-form-urlencoded"));
        }
        else
        {
            curl_setopt($ch, CURLOPT_URL, $url . $queryStr); // 配置网关地址
        }
        curl_setopt($ch, CURLOPT_HEADER, 0); // 过滤HTTP头
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // 超时
        $cookie_data = http_build_query($this->_cookies, '', ';');
        if (!empty($cookie_data)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie_data);
        }
        return $ch;
    }

    /*
     * @todo 订单取消
     */
    public function OrderCancel($order_data)
    {
        if (empty($order_data)) {
            return false;
        }
        $curl = new \Neigou\Curl();
        $order_data['order_bn'] = $order_data['order_id'];
        $request_params = array(
            'token' => \App\Api\Common\Common::GetSalyutOrderSign($order_data),
            'data' => json_encode($order_data),
        );
        $result = $curl->Post(config('neigou.SALYUT_DOMIN') . '/Home/Orders/cancelOrder', $request_params);
        \Neigou\Logger::General('salyut_service_order_cancel', array('sender' => $request_params, 'remark' => $result));
        $result = json_decode($result, true);
        return $result;
    }

    /*
     * @todo 创建预下单
     */
    public function PreOrderCreate(
        $order_data,
        $order_items,
        &$result_data,
        &$msg = '',
        &$err_code = '',
        &$err_data = []
    ) {
        if (empty($order_data) || empty($order_items)) {
            return false;
        }
        //订单货品bn
        $split_product_list = $this->SplitProduct($order_items);
        $extend_data = json_decode($order_data['extend_data'], true);
        if (!empty($split_product_list)) {
            foreach ($split_product_list as $key => $info) {

                $supplier_bn = $info['supplier_bn'];
                $items = $info['items'];

                $salyut_order = array(
                    'supplier_bn' => $supplier_bn,
                    'external_source_bn' => $this->getExternalSourceBn($supplier_bn, $order_data['temp_order_id']),
                    'external_order_bn' => $order_data['temp_order_id'],
                    'ship_province' => $order_data['ship_province'],
                    'ship_city' => $order_data['ship_city'],
                    'ship_county' => $order_data['ship_county'],
                    'ship_town' => !empty($order_data['ship_town']) ? $order_data['ship_town'] : '',
                    'ship_name' => $order_data['ship_name'],
                    'ship_address' => $order_data['ship_addr'],
                    'ship_mobile' => $order_data['ship_mobile'],
                    'ship_phone' => $order_data['ship_tel'],
                    'idcardname' => $order_data['idcardname'],
                    'idcardno' => $order_data['idcardno'],
                    'idcard_zhengmian_pic_url' => empty($extend_data['idcard_zhengmian_pic_url']) ? '' : $extend_data['idcard_zhengmian_pic_url'],
                    'idcard_fanmian_pic_url' => empty($extend_data['idcard_fanmian_pic_url']) ? '' : $extend_data['idcard_fanmian_pic_url'],
                    'items' => $items,
                );
                //考拉限制预下单订单号长度 （最大32位）
                if ($supplier_bn == 'KL' || $supplier_bn == 'MRYX') {
                    $salyut_order['external_source_bn'] = $supplier_bn . '-' . $order_data['order_id'];
                }

                $result = $this->SendPreOrder($salyut_order);
                if (true == $result['Result'] && $result['ErrorId'] == 10000) {
                    foreach ($result['Data']['items'] as $product_bn => $item) {
                        $result_data[$product_bn] = $item;
                    }
                } else {
                    $err_code = $result['ErrorId'];
                    $msg = $result['ErrorMsg'];
                    $err_data = $result['Data'];
                    return false;
                }
            }
        }
        if (!empty($result_data)) {
            return true;
        }
        return false;
    }

    /*
    * @todo 获取预下单外部源编号
    */
    private function getExternalSourceBn($supplier_bn, $order_id)
    {
        return 'SERVICE-PRE-' . $supplier_bn . '-' . $order_id;
    }

    /*
     * @todo 提交salyut预下单
     */
    private function SendPreOrder($salyut_order)
    {
        $curl = new \Neigou\Curl();
        $curl->time_out = 5;
        $request_params = array(
            'token' => \App\Api\Common\Common::GetSalyutOrderSign($salyut_order),
            'data' => json_encode($salyut_order),
        );
        $result = $curl->Post(config('neigou.SALYUT_DOMIN') . '/Home/Orders/preOrderToB', $request_params);
        \Neigou\Logger::Debug('salyut_service_send_pre_order', array('sender' => $request_params, 'remark' => $result));
        return json_decode($result, true);
    }

    // 唯一的商品编码
    private static function _createUniqueProductBn($productBn, $itemType = 'product', $pBn = '', $split = '-')
    {
        return implode($split, array_filter(array($itemType, $productBn, $pBn)));
    }

    /*
   * @todo 获取预下单外部源编号
   */
    private function _getItemSupplierBn($item)
    {
        $supplierBn = '';
        if(!isset($item['bn']) || empty($item['bn'])) {
            return $supplierBn;
        }
        $supplierBn = current(explode('-', $item['bn']));
        return $supplierBn;
    }

}
