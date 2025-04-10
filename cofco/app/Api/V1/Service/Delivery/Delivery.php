<?php

namespace App\Api\V1\Service\Delivery;

use App\Api\Model\Delivery\Delivery as DeliveryModel;
use App\Api\Model\Freight\Freight as FreightModel;

class Delivery
{
    public function createDelivery($data = array())
    {
        if (!isset($data['freight']) || !is_numeric($data['freight'])) {
            return $this->Response(500, 'param invalid[freight]');
        }
        if (!$data['shop_id'] || !is_numeric($data['shop_id'])) {
            return $this->Response(500, 'param invalid[shop_id]');
        }

        $_model = new DeliveryModel();

        $data = [
            'shop_id' => $data['shop_id'],
            'freight' => $data['freight']
        ];

        $result = $_model->add($data);
        if ($result) {
            return $this->Response(0, 'succ', []);
        }
        return $this->Response(100, 'db operate data error');
    }

    public function deleteDeliveryById($id = 0)
    {
        if (!$id || !is_numeric($id)) {
            return $this->Response(500, 'param invalid[id]');
        }

        $_model = new DeliveryModel();

        $where = array();
        $where['id'] = $id;

        $result = $_model->del($where);
        if ($result) {
            return $this->Response(0, 'succ', []);
        }
        return $this->Response(100, 'db operate data error');
    }

    public function saveDeliveryById($id = 0, $param = array())
    {
        if (!$id || !is_numeric($id)) {
            return $this->Response(500, 'param invalid[id]');
        }

        if (!$param) {
            return $this->Response(600, 'no update data');
        }

        if (isset($param['freight']) && !is_numeric($param['freight'])) {
            return $this->Response(500, 'param invalid[freight]');
        }

        $_model = new DeliveryModel();

        $where = array();
        $where['id'] = $id;

        $data = array();
        $data['freight'] = $param['freight'];

        $result = $_model->save($where, $data);
        if ($result) {
            return $this->Response(0, 'succ', []);
        }
        return $this->Response(100, 'db operate data error');
    }

    public function getDeliveryById($id = 0)
    {
        if (!$id || !is_numeric($id)) {
            return $this->Response(500, 'param invalid[id]');
        }

        $_model = new DeliveryModel();

        $where = array();
        $where['id'] = $id;

        $result = $_model->find($where);
        if ($result) {
            return $this->Response(0, 'succ', $result[0]);
        }
        return $this->Response(100, 'db operate data error');
    }

    public function getDeliveryListByShopId($shop_id = 0)
    {
        if (!$shop_id || !is_numeric($shop_id)) {
            return $this->Response(500, 'param invalid[shop_id]');
        }

        $_model = new DeliveryModel();

        $where = array();
        $where['shop_id'] = $shop_id;

        $result = $_model->find($where);
        if ($result) {
            return $this->Response(0, 'succ', $result);
        }
        return $this->Response(100, 'db operate data error');
    }

    public function getDeliveryFreight($param = array())
    {
        // param check
        foreach ($param as $shop) {
            if (!isset($shop['subtotal']) || !is_numeric($shop['subtotal'])) {
                return $this->Response(500, 'param invalid[subtotal]');
            }

            if (!isset($shop['shop_id']) || !is_numeric($shop['shop_id'])) {
                return $this->Response(500, 'param invalid[shop_id]');
            }

            if (!isset($shop['shipping_area']['area_id'])) {
                return $this->Response(500, 'param invalid[shipping_area.area_id]');
            }

            if (!isset($shop['shipping_id']) || !is_numeric($shop['shipping_id'])) {
                return $this->Response(500, 'param invalid[shipping_id]');
            }

            if (!isset($shop['weight']) || !is_numeric($shop['weight'])) {
                return $this->Response(500, 'param invalid[weight]');
            }
        }

        $data = $param;

        $shopId = array();
        foreach ($data as $shop) {
            $shopId[] = $shop['shop_id'];
        }

        $shopFreightRule = $this->_getShopFreightRule($shopId);

        $combineFreight = array();
        // check success
        foreach ($data as &$shop) {
            if (!isset($shopFreightRule[$shop['shop_id']])) {
                $shop['freight'] = number_format($this->getShopFreight($shop), 2, ".", "");
                continue;
            }
            $rule = $shopFreightRule[$shop['shop_id']];

            if ($rule['combine_count'] > 1 && !isset($combineFreight[$rule['freight_id']])) {
                $combineFreight[$rule['freight_id']] = array(
                    'count' => 0,
                    'subtotal' => 0,
                    'weight' => 0,
                    'freight_per' => 0,
                    'shipping_id' => $rule['rule_bn'],
                    'shipping_area' => $shop['shipping_area'],
                );
            }

            // 组合计算运费
            if ($rule['combine_count'] > 1) {
                $freightId = $rule['freight_id'];
                $combineFreight[$freightId]['count']++;
                $combineFreight[$freightId]['subtotal'] += $shop['subtotal'];
                $combineFreight[$freightId]['weight'] += $shop['weight'];

                if ($combineFreight[$freightId]['count'] < $rule['combine_count']) {
                    $shop['freight'] = &$combineFreight[$freightId]['freight_per'];
                    continue;
                }

                if ($rule['rule_type'] == 'NEIGOU') {
                    $freight = number_format($this->getNeigouFreightV2($combineFreight[$freightId]), 2, ".", "");
                } else {
                    $freight = number_format($this->getShopFreight(array('shop_id' => $rule['rule_bn'])), 2, ".", "");
                }

                // 运费四舍五入均摊
                $freightPer = number_format(round($freight / $rule['combine_count'], 2), 2, '.', '');

                $combineFreight[$freightId]['freight_per'] = $freightPer;

                // 最后一个承包余下运费
                $shop['freight'] = number_format($freight - $freightPer * ($rule['combine_count'] - 1), 2, '.', '');
            } else {
                if ($rule['rule_type'] == 'NEIGOU') {
                    $shop['shipping_id'] = $rule['rule_bn'];
                    $shop['freight'] = number_format($this->getNeigouFreightV2($shop), 2, ".", "");
                } else {
                    $shop['freight'] = number_format($this->getShopFreight(array('shop_id' => $rule['rule_bn'])), 2,
                        ".", "");
                }
            }
        }

        return $this->Response(0, 'succ', $data);
    }

    /** 获取运费显示名称
     *
     * @param array $param array("shop_id" => '商城id',"bn" => "商品bn");
     * @return array|string
     * @author liuming
     */
    public function getFreightDetail($param = array())
    {
        //todo 参数检查
        //检查shop_id
        if (!isset($param['shop_id']) || !is_numeric($param['shop_id'])) {
            return $this->Response(500, 'param invalid[shop_id]');
        }

        if (!isset($param['bn']) || !is_string($param['bn'])) {
            return $this->Response(500, 'param invalid[bn]');
        }

        $freightDetail = '';

        // 店铺运费规则
        $shopFreightRule = $this->_getShopFreightRule($param['shop_id']);

        if (!empty($shopFreightRule) && $shopFreightRule[$param['shop_id']]['rule_type'] == 'NEIGOU' && intval($shopFreightRule[$param['shop_id']]['rule_bn']) > 0) {
            $res = $this->getDlyTypeInfoByDt_id($shopFreightRule[$param['shop_id']]['rule_bn']);
            if (!empty($res) && isset($res['detail'])) {
                $freightDetail = $res['detail'];
            }
        } else {
            if (!empty($shopFreightRule) && $shopFreightRule[$param['shop_id']]['rule_type'] == 'SHOP' && intval($shopFreightRule[$param['shop_id']]['rule_bn']) > 0) {
                $where = array('shop_id' => $shopFreightRule[$param['shop_id']]['rule_bn']);
            } else {
                $where = array('shop_id' => $param['shop_id']);
            }

            //没有设置规则, 如果有数据显示空,没有数据显示该商品已包邮
            $_model = new DeliveryModel();
            $result = $_model->find($where);
            if (!isset($result[0]->freight) || empty($result)) {
                $freightDetail = '该商品已包邮';
            }
        }

        return $this->Response(0, 'succ', array('freightDetail' => $freightDetail));
    }

    private function getShopFreight($param = array())
    {
        // 店铺暂时只支持设置一个运费故这里用shop_id 查询运费取第0(1)条记录
        $_model = new DeliveryModel();

        $where = array();
        $where['shop_id'] = $param['shop_id'];

        $result = $_model->find($where);
        if ($result && $result[0] && property_exists($result[0], 'freight')) {
            return $result[0]->freight ? $result[0]->freight : 0;
        }
        \Neigou\Logger::General('delivery.freight', array('action' => 'getfreight.error', "response" => $result));
        return 0;
    }

    private function getNeigouFreight($param = array())
    {
        $post_data = array();
        $post_data['shipping_id'] = $param['shipping_id'];
        $post_data['weight'] = $param['weight'];
        $post_data['subtotal'] = $param['subtotal'];
        $post_data['shipping_area']['area_id'] = $param['shipping_area']['area_id'];
        $post_data['token'] = \App\Api\Common\Common::GetEcStoreSign($post_data);

        $freight = 0;
        $_curl = new \Neigou\Curl();
        $_curl->time_out = 7;
        $result = $_curl->Post(config('neigou.STORE_DOMIN') . '/openapi/operate/freight', $post_data);
        $result = json_decode($result, true);

        if ($result['Result'] != 'true' || !isset($result['Data']['freight']) || !$result['Data']['shipping_id']) {
            $freight = 10;
            \Neigou\Logger::General('delivery.freight', array('action' => 'getfreight.error', "response" => $result));
        }
        $freight = (!isset($result['Data']['freight']) || !is_numeric($result['Data']['freight'])) ? 10 : $result['Data']['freight'];

        return $freight;
    }


    /**
     * [freight description]
     * @todo   运费计算 考虑 1.地区规则免邮
     * subtotal:12.9
     * shipping_area[area_id]:8
     * weight:100
     * // company_id:151
     * @return [type]        [description]
     */
    public function getNeigouFreightV2($param = array())
    {
        $post_data = array();
        $post_data['shipping_id'] = $param['shipping_id'];
        $post_data['weight'] = $param['weight'];
        $post_data['subtotal'] = $param['subtotal'];
        $post_data['shipping_area']['area_id'] = $param['shipping_area']['area_id'];


        $shipping = $post_data['shipping_area'];
        $deliveryModel = new DeliveryModel();


        $default_freight = 10;
        $freight = 0;
        $shipping_id = intval($post_data['shipping_id']);
        if ($shipping['area_id']) {
            $dlytype_info = $deliveryModel->getDlyTypeInfo($shipping_id);
            if (is_null($dlytype_info)) {
                return $default_freight;
            }
            if ($dlytype_info['is_threshold']) {
                if ($dlytype_info['threshold']) {
                    $dlytype_info['threshold'] = unserialize(stripslashes($dlytype_info['threshold']));
                    if (isset($dlytype_info['threshold']) && $dlytype_info['threshold']) {
                        foreach ($dlytype_info['threshold'] as $res) {
                            if ($res['area'][1] > 0) {
                                if ($post_data['subtotal'] >= $res['area'][0] && $post_data['subtotal'] < $res['area'][1]) {
                                    $dlytype_info['firstprice'] = $res['first_price'];
                                    $dlytype_info['continueprice'] = $res['continue_price'];
                                }
                            } else {
                                if ($post_data['subtotal'] >= $res['area'][0]) {
                                    $dlytype_info['firstprice'] = $res['first_price'];
                                    $dlytype_info['continueprice'] = $res['continue_price'];
                                }
                            }
                        }
                    }
                }
            }

            // 是否统一设置运费 1统一设置 0指定区域设置
            if (!$dlytype_info['setting']) {
                $area_id = $shipping['area_id'];
                if (isset($dlytype_info['area_fee_conf']) && $dlytype_info['area_fee_conf']) {
                    $region_info = $deliveryModel->getRegionById($area_id);
                    $region_path = trim($region_info['region_path'], ',');
                    $region_ids = explode(',', $region_path);
                    $area_fee_conf = unserialize($dlytype_info['area_fee_conf']);
                    foreach ($area_fee_conf as $k => $v) {
                        $areas = explode(',', $v['areaGroupId']);
                        foreach ($areas as &$str_area) {
                            if (strpos($str_area, '|') !== false) {
                                $str_area = substr($str_area, 0, strpos($str_area, '|'));
                            }
                            if (in_array($str_area, $region_ids)) {
                                //如果地区在其中，优先使用地区设置的配送费用，及公式
                                if ($dlytype_info['firstprice']) {
                                    $dlytype_info['firstprice'] = $v['firstprice'];
                                }
                                $dlytype_info['continueprice'] = $v['continueprice'];
                                $dlytype_info['dt_expressions'] = $v['dt_expressions'];
                                break 2;
                            }
                        }
                    }
                }
            }

            $freight = Utils::cal_fee($dlytype_info['dt_expressions'], $post_data['weight'], $post_data['subtotal'],
                $dlytype_info['firstprice'], $dlytype_info['continueprice']);//配送费
        }

        return $freight;
    }

    public function getDlyTypeInfoByDt_id($dt_id = '')
    {
        $model = new DeliveryModel();
        return $model->getDlyTypeInfo($dt_id);
    }

    // --------------------------------------------------------------------

    /**
     * 获取店铺的运费规则
     *
     * @param   $shopId     mixed       店铺ID
     * @return  array
     *          freight_id      运费ID
     *          name            运费名称
     *          rule_type       运费规则类型 (NEIGOU、SHOP)
     *          rule_bn         运费规则编码
     *          combine         组合计算运费
     *          rule            运费规则唯一标识
     *          combine_count   参与本次组合计算店铺数量
     */
    private function _getShopFreightRule($shopId)
    {
        $return = array();

        if (empty($shopId)) {
            return $return;
        }

        if (!is_array($shopId)) {
            $shopId = array($shopId);
        }

        $freightModel = new FreightModel();

        // 获取店铺运费的规则
        $shopFreight = $freightModel->getShopFreight($shopId, 1);

        if (empty($shopFreight)) {
            return $shopFreight;
        }

        $freightId = array();
        foreach ($shopFreight as $key => $freight) {
            if ($freight['freight_id'] <= 0) {
                unset($shopFreight[$key]);
                continue;
            }
            $freightId[$freight['freight_id']] = $freight['freight_id'];
        }

        if (empty($freightId)) {
            return $return;
        }

        // 获取运费详情
        $freightList = $freightModel->getFreightInfo($freightId, 1);

        if (empty($freightList)) {
            return $return;
        }

        $combineCount = array();
        foreach ($shopFreight as $key => $freight) {
            if (empty($freightList[$freight['freight_id']]) OR empty($freightList[$freight['freight_id']]['rule_bn'])) {
                continue;
            }

            $freightInfo = $freightList[$freight['freight_id']];

            if ($freightInfo['combine'] == 1) {
                if (!isset($combineCount[$freight['freight_id']])) {
                    $combineCount[$freight['freight_id']] = 0;
                }
                $combineCount[$freight['freight_id']]++;
            } else {
                $combineCount[$freight['freight_id']] = 1;
            }

            $return[$freight['shop_id']] = array(
                'shop_id' => $freight['shop_id'],
                'freight_id' => $freight['freight_id'],
                'name' => $freightInfo['name'],
                'rule_type' => $freightInfo['rule_type'],
                'rule_bn' => $freightInfo['rule_bn'],
                'combine' => $freightInfo['combine'],
                'rule' => $freightInfo['rule_type'] . '_' . $freightInfo['rule_bn'],
                'combine_count' => & $combineCount[$freight['freight_id']],

            );
        }

        return $return;
    }

    private function Response($error_code, $error_msg, $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }
}
