<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2018/11/15
 * Time: 8:21 PM
 */

namespace App\Console\Commands;

use App\Api\Logic\Service as Service;

use Illuminate\Console\Command;

/**
 * 订单任务
 *
 * @package     Console
 * @category    Command
 * @author        xupeng
 */
class OrderTask extends Command
{
    protected $signature = 'orderTask {method} {order_id?} ';

    /**
     * 每次处理最大数量
     */
    const PER_MAX_LIMIT = 1000;

    // 处理发票状态
    public function handle()
    {
        $method = $this->argument('method');

        $this->$method();
    }

    // --------------------------------------------------------------------

    /**
     * 临时生成salyut订单
     */
    public function tempOrderCreate()
    {
        // 订单号
        $orderId = $this->argument('order_id') ? $this->argument('order_id') : 0;

        echo "TEMP ORDER CREATE START \n";

        $page = 1;
        while($page < 99999)
        {
            $offset = ($page - 1) * self::PER_MAX_LIMIT;
            $sql = "select * from server_orders where order_id > $orderId and status != 2 and `create_source` = 'main' order by order_id asc limit $offset, ". self::PER_MAX_LIMIT;

            $orderList = app('api_db')->select($sql);

            if (empty($orderList)){
                break;
            }

            foreach ($orderList as $order_data)
            {
                $order_data = get_object_vars($order_data);
                echo $order_data['order_id']. "============";

                // 获取 split_info
                $sql = "select * from server_order_split where split_info like '%". $order_data['order_id']."%'";
                $orderSplit = app('api_db')->select($sql);

                if (empty($orderSplit))
                {
                    continue;
                }
                $split_data = get_object_vars(current($orderSplit));

                $create_order_data = array(
                    'order_id' => $order_data['order_id'],
                    //订单号
                    'member_id' => $order_data['member_id'],
                    //用户id
                    'company_id' => $order_data['company_id'],
                    //公司id
                    'pmt_amount' => $order_data['pmt_amount'],
                    // 优惠金额(满减券+免税券)
                    'point_amount' => $order_data['point_amount'],
                    // 积分支付金额
                    'ship_name' => $order_data['ship_name'],
                    //收货人姓名
                    'ship_addr' => $order_data['ship_addr'],
                    //收货人详情地址
                    'ship_zip' => $order_data['ship_zip'],
                    //收货人邮编
                    'ship_tel' => $order_data['ship_tel'],
                    //收货人电话
                    'ship_mobile' => $order_data['ship_mobile'],
                    //收货人手机号
                    'ship_province' => $order_data['ship_province'],
                    //收货人所在省
                    'ship_city' => $order_data['ship_city'],
                    //收货人所在市
                    'ship_county' => $order_data['ship_county'],
                    //收货人所在县
                    'ship_town' => $order_data['ship_town'],
                    //收货人所在镇
                    'idcardname' => $order_data['idcardname'],
                    //收货人证件类型 (身份证)
                    'idcardno' => $order_data['idcardno'],
                    //收货人证件号 (身份证号)
                    'terminal' => $order_data['terminal'],
                    //平台来源PC|手机
                    'point_channel' => $order_data['point_channel'],
                    //使用积分类型
                    'anonymous' => $order_data['anonymous'],
                    //匿名下单 yes|no
                    'receive_mode' => empty($order_data['receive_mode']) ? 1 : intval($order_data['receive_mode']),
                    //收货方式
                    'memo' => empty($order_data['memo']) ? '' : $order_data['memo'],
                    //订单附言
                    'payment_restriction' => $order_data['payment_restriction'],
                    //支付方式限制
                    'business_code' => empty($order_data['business_code']) ? '' : $order_data['business_code'],
                    //内购业务关系编码
                    'extend_info_code' => empty($order_data['extend_info_code']) ? '' : $order_data['extend_info_code'],
                    //业务扩展类型
                    'order_category' => empty($order_data['order_category']) ? '' : $order_data['order_category'],
                    //功能列表划分  (电商订单、福利、体检等）
                    'business_project_code' => empty($order_data['business_project_code']) ? '' : $order_data['business_project_code'],
                    //内购业务项目编码
                    'system_code' => empty($order_data['system_code']) ? '' : $order_data['system_code'],
                    //业务系统（内购会、积分宝等）
                    'extend_data' => is_array($order_data['extend_data']) ? json_encode($order_data['extend_data']) : $order_data['extend_data'],
                    //扩展数据（json）
                    'split_id' =>  $split_data['split_id'] ? $split_data['split_id'] : '',
                    //拆单结果
                    'channel' => $order_data['channel'],
                    //下单渠道
                    'lock_source' => $order_data['lock_source'],
                    //库存锁定来源
                    'preorder_order' => isset($order_data['preorder_order']) ? $order_data['preorder_order'] : array(),
                    //复用预下订单
                    'project_code' => $order_data['project_code'],
                    'freight_price' => $order_data['cost_freight'],
                    // 运费
                    'cost_freight' => $order_data['cost_freight'],
                    // 订单金额
                    'final_amount' => $order_data['final_amount'],
                    // 商品金额
                    'cost_item' => $order_data['cost_item'],
                );

                if (isset($order_data['extend_deliver_area'])) {
                    $create_order_data['extend_deliver_area'] = $order_data['extend_deliver_area']; //mryx详细地址
                }

                if (isset($order_data['extend_send_time'])) {
                    $create_order_data['extend_send_time'] = $order_data['extend_send_time']; //mryx配送时间
                }

                $order_data = $create_order_data;
                $split_data['split_info'] = json_decode($split_data['split_info'], true);
                // 格式化拆单数据，并生成订单号
                $split_info = $this->FormatSplitOrder($split_data['split_info'], $order_data);

                if (empty($split_info) || empty($split_info['items'])) {
                    continue;
                }

                foreach ($split_info['items'] as $item) {
                    if (!isset($preorder_itmes[$item['bn']])) {
                        $order_items[] = [
                            'product_bn' => $item['bn'],
                            'nums' => $item['nums'],
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'pmt_amount' => $item['pmt_amount'],
                            'cost_freight' => $item['cost_freight'],
                            'point_amount' => $item['point_amount'],
                            'payed_amount' => $item['payed_amount'],
                        ];
                    }
                }
                if (empty($order_items)) {
                    continue;
                }

                $result = $this->_createV2($order_data, $order_items, $salyut_response_data, $split_info);

                echo $result ? "true" : 'false';
                echo "\n";
            }
            $page++;
        }

        echo "TEMP ORDER CREATE START END \n";
    }

    /*
     * @todo 创建订单
     */
    public function _createV2($order_data, $order_items, &$result_data, $split_info)
    {
        if (empty($order_data) || empty($order_items)) {
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
                );

                $salyut_order['extend_deliver_area'] = $order_data['extend_deliver_area'];
                $salyut_order['extend_send_time'] = $order_data['extend_send_time'];
                $salyut_order['longitude'] = isset($deliver_extend_data['location']['lng']) ? $deliver_extend_data['location']['lng'] : '';   //经度
                $salyut_order['latitude'] = isset($deliver_extend_data['location']['lat']) ? $deliver_extend_data['location']['lat'] : '';    //纬度

                $preorder_result_list[] = $this->_sendOrder($salyut_order);
            }

            foreach ($preorder_result_list as $preorder_result)
            {
                if (false === $preorder_result) {
                    return false;
                }

                if (empty($preorder_result['success']) && !empty($preorder_result['msg']))
                {
                    $preorder_msg = json_decode($preorder_result['msg'], true);
                    foreach ($preorder_msg as $real_msg) {
                        if (!empty($real_msg['product_bn_list'])) {
                            foreach ($real_msg['product_bn_list'] as $product_bn) {
                                $result_data[] = $product_bn;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($result_data)) {
            return false;
        }

        return true;
    }

    /*
     * @todo 拆分商品
     */
    private function _splitProduct($order_items, $split_order)
    {
        $return = array();

        $split_product_list = array();
        if (empty($order_items)) {
            return $split_product_list;
        }

        $product_list = array();
        foreach ($order_items as $product)
        {
            $product_list[$product['product_bn']] = array(
                'count' => $product['nums'],
                'name' => $product['name'],
                'price' => $product['price'],
                'pmt_amount' => $product['pmt_amount'],
                'cost_freight' => $product['cost_freight'],
                'point_amount' => $product['point_amount'],
                'payed_amount' => $product['payed_amount'],
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
                if (empty($product_list[$item['bn']]))
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
                $return[$order['order_id']]['items'][$item['bn']] = $product_list[$item['bn']];
            }
        }

        return $return;
    }


    /**
     * 格式化拆单数据
     *
     * @param   $splitData      array   拆单数据
     * @param   $orderData      array   订单数据
     * @return  array
     */
    private function FormatSplitOrder($splitData, $orderData)
    {
        $return = $splitData;

        $return['items'] = array();
        // 商品详细
        foreach ($splitData['items'] as $item) {
            $return['items'][] = [
                'bn' => $item['product_bn'],
                'p_bn' => $item['p_bn'],
                'name' => $item['name'],
                'cost' => $item['cost'],
                'price' => $item['price'],
                'mktprice' => $item['mktprice'],
                'amount' => $item['amount'],
                'weight' => $item['weight'],
                'nums' => $item['nums'],
                'pmt_amount' => $item['pmt_amount'],
                'cost_freight' => $item['cost_freight'],
                'point_amount' => $item['point_amount'],
                'cost_tax' => $item['cost_tax'],
                'item_type' => $item['item_type'],
                'payed_amount' => $this->getPayed($item),
            ];
        }

        $return['split_orders'] = array();
        // 子订单拆分
        if (!empty($splitData['split_orders'])) {
            $splitOrderCount = count($splitData['split_orders']);
            foreach ($splitData['split_orders'] as $order) {
                // 商品明细
                $order_items = [];
                foreach ($order['items'] as $item) {
                    $order_items[] = [
                        'bn' => $item['product_bn'],
                        'p_bn' => $item['p_bn'],
                        'name' => $item['name'],
                        'cost' => $item['cost'],
                        'price' => $item['price'],
                        'mktprice' => $item['mktprice'],
                        'amount' => $item['amount'],
                        'weight' => $item['weight'],
                        'nums' => $item['nums'],
                        'pmt_amount' => $item['pmt_amount'],
                        'cost_freight' => $item['cost_freight'],
                        'cost_tax' => $item['cost_tax'],
                        'point_amount' => $item['point_amount'],
                        'item_type' => $item['item_type'],
                        'payed_amount' => $this->getPayed($item),
                    ];
                }

                $return['split_orders'][] = [
                    'order_id' => $splitOrderCount > 1 ? ($order['temp_order_id']) : $orderData['order_id'],
                    'create_source' => 'wms_order',
                    'final_amount' => $order['final_amount'],
                    'cur_money' => $order['cur_money'],
                    'point_amount' => $order['point_amount'],
                    'cost_freight' => $order['cost_freight'],
                    'cost_tax' => $order['cost_tax'],
                    'cost_item' => $order['cost_item'],
                    'pmt_amount' => $order['pmt_amount'],
                    'weight' => $order['weight'],
                    'wms_code' => $order['wms_code'],
                    'pop_owner_id' => $order['pop_owner_id'],
                    'items' => $order_items,
                    'memo' => isset($order['memo']) ? $order['memo'] : '',
                ];
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
        setcookie('salyut_q_womai_com_AUTOCI_BRANCH','jenkins-neigou-salyut-48', 60);

        $result = $curl->Post(config('neigou.SALYUT_DOMIN') . '/Home/Orders/createPreOrderTmp', $request_params);

        if ($curl->GetHttpCode() != 200) {
            $result = false;
        } else {
            $result = json_decode($result, true);
        }
        return $result;
    }
    private function getPayed($item)
    {
        $payed_amount = 0;
        $payed_amount = bcadd($payed_amount, $item['amount'], 2);
        $payed_amount = bcadd($payed_amount, $item['cost_freight'], 2);
        $payed_amount = bcadd($payed_amount, $item['cost_tax'], 2);
        $payed_amount = bcsub($payed_amount, $item['pmt_amount'], 2);
        $payed_amount = bcsub($payed_amount, $item['point_amount'], 2);
        return $payed_amount;
    }
}
