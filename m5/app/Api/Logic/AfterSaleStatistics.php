<?php
/**
 * Created by PhpStorm.
 * User: liangtao
 * Date: 2018/12/3
 * Time: 下午3:56
 */

namespace App\Api\Logic;

use App\Api\Model\AfterSale\Statistics as Statistics;
use App\Api\Logic\Service as Service;

class AfterSaleStatistics
{
    /*
     * 统计创建
     */
    public function create($param = array(), &$err = '')
    {
        if (!$param['source_bn'] || !$param['source_type'] || !$param['status'] || !$param['order_id']) {
            $err = '参数错误';
            return false;
        }

        //订单检查
        $service_logic = new Service();
        $orderData = $service_logic->ServiceCall('order_info', ['order_id' => $param['order_id']]);
        if ('SUCCESS' !== $orderData['error_code']) {
            $err = '订单数据错误';
            return false;
        }

        $orderInfo = $orderData['data'];

        $data = array();

        if ($param['order_id']) {
            $data['order_id'] = $param['order_id'];
        }

        if ($param['source_bn']) {
            $data['source_bn'] = $param['source_bn'];
        }

        if ($param['source_type']) {
            $data['source_type'] = $param['source_type'];
        }

        if ($param['status']) {
            $data['status'] = $param['status'];
        }

        if ($param['memo']) {
            $data['memo'] = $param['memo'];
        }

        if ($param['responsible']) {
            $data['responsible'] = $param['responsible'];
        }

        if ($param['responsible_msg']) {
            $data['responsible_msg'] = $param['responsible_msg'];
        }

        if ($param['level']) {
            $data['level'] = $param['level'];
        }

        //获取统计状态
        $StatisticsMdl = new Statistics();
        $Statistics_data = $StatisticsMdl->getStatus(array('id'=>$param['status']));
        if(!$Statistics_data){
            $err = '统计状态错误';
            return false;
        }

        $Statistics_data = current($Statistics_data);

        $time = time();
        $data['create_time'] = $param['create_time'] ? $param['create_time'] : $time;
        $data['update_time'] = $param['update_time'] ? $param['update_time'] : $time;

        $res = $StatisticsMdl->create($data);
        if (!$res) {
            $err = '提交失败';
            return false;
        }

        //自营第三方部分
        $post_data = array(
            'after_sale_bn'=>$param['source_bn'],
            'return_cause'=>$Statistics_data['name']?$Statistics_data['name']:''
        );

        $url = '';
        if ( in_array( $orderInfo['wms_code'], array( 'SHOP', 'SHOPNG' ) ) )
        {
            if ( $orderInfo['wms_code'] == 'SHOP' )
            {
                $appSecret = config( 'neigou.SHOP_APPSECRET' );
                $appKey = config( 'neigou.SHOP_APPKEY' );
            } elseif ( $orderInfo['wms_code'] == 'SHOPNG' )
            {
                $appSecret = config( 'neigou.SHOPNG_APPSECRET' );
                $appKey = config( 'neigou.SHOPNG_APPKEY' );
            } else
            {
                $err = '系统异常';
                return false;
            }

            $token = \App\Api\Common\Common::GetShopV2Sign( $post_data, $appSecret );

            $post_data = array(
                'appkey' => $appKey,
                'data' => json_encode( $post_data ),
                'sign' => $token,
                'time' => date( 'Y-m-d H:i:s' ),
            );
            $url = config( 'neigou.SHOP_DOMIN' ) . '/Shop/OpenApi/Channel/V1/Order/Return/createCauseNotify';
        }

        $this->_post($url,$post_data);

        return true;
    }

    public function _post( $url = '', $post_data = array() )
    {
        if(!$url || !$post_data){
            return false;
        }

        $_curl = new \Neigou\Curl();
        $_curl->time_out = 7;
        $result = $_curl->Post( $url, $post_data );
        $result = json_decode( $result, true );
        \Neigou\Logger::Debug(
            'after_sale_statistics_create_notify',
            array(
                'data' => json_encode( $post_data ),
                'result' => json_encode( $result ),
                'remark' => $url
            )
        );

        if ( $result['Result'] == 'false' )
        {
            \Neigou\Logger::General(
                'after_sale_statistics_create_notify_fail',
                array(
                    'data' => json_encode( $post_data ),
                    'result' => json_encode( $result )
                )
            );
            return false;
        } else
        {
            return true;
        }
    }

    public function update($param = array(), &$err = '')
    {

        if (empty($param['id']) || empty($param['source_bn'])) {
            $err = '参数错误';
            return false;
        }

        $data = array();
        if ($param['status']) {
            $data['status'] = $param['status'];
        }

        if (isset($param['memo'])) {
            $data['memo'] = $param['memo'];
        }

        if (isset($param['responsible'])) {
            $data['responsible'] = $param['responsible'];
        }

        if (isset($param['responsible_msg'])) {
            $data['responsible_msg'] = $param['responsible_msg'];
        }

        if (isset($param['level'])) {
            $data['level'] = $param['level'];
        }

        $time = time();
        $data['update_time'] = $time;

        $where = array(
            'id' => $param['id'],
            'source_bn' => $param['source_bn']
        );
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->update($where, $data);
        if (!$res) {
            $err = '提交失败';
            return false;
        }
        return true;
    }

    public function get($where = array(), &$err)
    {
        if (empty($where['source_bn']) || empty($where['source_type'])) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->get($where);
        return $res ? $res : array();
    }

    public function getList($param = array(), &$err)
    {
        if (empty($param['source_bn']) || empty($param['source_type'])) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->getList($param);
        return $res ? $res : array();
    }

    public function getStatus($where = array(), &$err)
    {
        if (empty($where['source_type'])) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->getStatus($where);
        return $res ? $res : array();
    }

    public function getStatusById($id, &$err)
    {
        if (empty($id)) {
            $err = '参数错误';
            return false;
        }
        $StatisticsMdl = new Statistics();
        $res = $StatisticsMdl->getStatusById($id);
        return $res ? $res : array();
    }

}
