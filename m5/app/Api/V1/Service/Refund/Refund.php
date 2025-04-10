<?php
/**
 * Created by PhpStorm.
 * User: Liuming
 * Date: 2020/6/17
 * Time: 11:51 AM
 */

namespace App\Api\V1\Service\Refund;

use App\Api\Model\Refund\Refund as RefundModel;

class Refund
{

    /** 获取售后单列表
     *
     * @param $request_data
     * @return array
     * @author liuming
     */
    public function getList($request_data)
    {
        //  开发思路:
        // 1. 获取refund_bill列表;
        // 2.查询mis_order_refund_bill_object_assets和mis_order_refund_bill_object_number
        // 3. 如果objec表object_id是订单, 查询订单服务, 获取商品信息.否则的话
        //
        $refundList = [];
        if (empty($request_data) || empty($request_data['filter'])) {
            return $refundList;
        }

        // 1. 获取refund_bill列表;
        $findRefundList = $this->findRefundList($request_data['filter'], $request_data['page_index'], $request_data['page_size']);
        if (empty($findRefundList)) {
            return $refundList;
        }
        // 2.根据refund_id查询mis_order_refund_bill_object_assets和mis_order_refund_bill_object_number
        $objectList = $this->setObjectList($findRefundList);
        if (empty($objectList)) {
            return $refundList;
        }

        // 3.组合数据
        foreach ($findRefundList as $refundV) {
            $isOrder = 0;
            if (!isset($objectList[$refundV['refund_id']])) {
                continue;
            }

            $currentRefundObject = $objectList[$refundV['refund_id']];
            if (empty($currentRefundObject)) {
                continue;
            }
            $productBnRefundList = [];
            foreach ($currentRefundObject as $productBnK => $currObjectV) {
                $tmpList = [];
                if ($productBnK == $refundV['order_id']) {
                    $isOrder = 1;
                } else if ($productBnK == $refundV['son_order_id']) {
                    $isOrder = 1;
                }

                // 组合已商品作为维度的信息
                $tmpList = [
                    //'refund_type' => $isOrder,// 退款类型 0,商品维度退款; 1.订单维度退款
                    //'order_id' => $refundV['order_id'],
                    //'split_order' => $refundV['son_order_id'],
                    'refund_id' => $refundV['refund_id'],
                    'object_id' => $productBnK,
                    //'source' => $this->getSourceName($refundV['source']), // 退货来源
                    'refund_num' => 0, // 退货数量
                    'refund_money' => 0, // 退货金额
                    'refund_point' => 0, // 退货积分
                    'refund_voucher' => 0, // 退券
                    'price_protection_num' => 0, // 价保数量
                    'price_protection_money' => 0, // 价保金额
                    'price_protection_point' => 0, // 价保积分
                    //'refund_update_time' => $refundV['update_time'], // 退款最后更新时间
                ];

                if ($refundV['source'] == 5){
                    $tmpList['price_protection_num'] =  $currObjectV['num']['count'];
                    $tmpList['price_protection_money'] =  $currObjectV['asset'][1]['money'] ? $currObjectV['asset'][1]['money'] : 0;
                    $tmpList['price_protection_point'] =  $currObjectV['asset'][2]['money'] ? $currObjectV['asset'][2]['money'] : 0;

                }else{
                    $tmpList['refund_num'] =  $currObjectV['num']['count']; // 退货数量
                    $tmpList['refund_money'] =  $currObjectV['asset'][1]['money'] ? $currObjectV['asset'][1]['money'] : 0;
                    $tmpList['refund_point'] =  $currObjectV['asset'][2]['money'] ? $currObjectV['asset'][2]['money'] : 0;
                    $tmpList['refund_voucher'] = $currObjectV['asset'][3]['money'] ? $currObjectV['asset'][3]['money'] : 0;
                }
                $productBnRefundList[] = $tmpList;
            }
            $refundV['refund_type'] = $isOrder;
            $refundList[$refundV['refund_id']] = $refundV;
            $refundList[$refundV['refund_id']]['items'] = $productBnRefundList;
        }

        return $refundList;
    }


    /** 获取refund object列表
     *
     * @param array $refundList
     * @return array
     * @author liuming
     */
    private function setObjectList($refundList = array())
    {
        if (empty($refundList)) {
            return [];
        }
        $refundIdList = array_column($refundList, 'refund_id');

        // 查询mis_order_refund_bill_object_assets和mis_order_refund_bill_object_number
        $refundModel = new RefundModel();
        $findObjectAssets = $refundModel->findObjectAssets($refundIdList);
        $findObjectNums = $refundModel->findObjectNums($refundIdList);
        if (empty($findObjectAssets) || empty($findObjectNums)) {
            return [];
        }

        // -------- 组合退款资源信息 begin --------//
        $refundObject = [];
        foreach ($findObjectAssets as $assetV) {
            $refundObject[$assetV['refund_id']][$assetV['object_id']]['asset'][$assetV['assets_type']] = $assetV;
        }

        foreach ($findObjectNums as $numV) {
            $refundObject[$numV['refund_id']][$numV['object_id']]['num'] = $numV;
        }
        // -------- 组合退款资源信息 end --------//

        return $refundObject;
    }


    /** 查找退款列表
     *
     * @param array $where
     * @param int $page
     * @param int $pageSize
     * @return array
     * @author liuming
     */
    private function findRefundList($where = array(), $page = 1, $pageSize = 20)
    {
        $refundList = [];
        if (empty($where)) {
            return $refundList;
        }

        // 获取where条件
        $where = $this->getWhereByFilter($where);
        if (empty($where)) {
            return $refundList;
        }
        $offset = max($page - 1, 0) * $pageSize;
        $limit = $pageSize;
        $refundModel = new RefundModel();
        $list = $refundModel->getList(array('*'), $where, $offset, $limit);
        return $list;
    }

    /** 查询总条数
     *
     * @param $where
     * @return mixed
     * @author liuming
     */
    public function getTotal($where)
    {
        $where = $this->getWhereByFilter($where);
        $refundModel = new RefundModel();
        $count = $refundModel->getTotal($where);
        return $count;
    }


    /** 设置where条件
     *
     * @param $filter_data
     * @return array
     * @author liuming
     */
    protected function getWhereByFilter($filter_data)
    {
        $where = [];
        if (empty($filter_data)) {
            return $where;
        }
        foreach ($filter_data as $field => $value) {
            switch ($field) {
                case 'create_time':
                    $where['create_time'] = [
                        'type' => 'between',
                        'value' => [
                            'egt' => ($value['start_time']),
                            'elt' => empty($value['end_time']) ? time() : ($value['end_time'])
                        ]
                    ];
                    break;
                case 'update_time':
                    $where['update_time'] = [
                        'type' => 'between',
                        'value' => [
                            'egt' => ($value['start_time']),
                            'elt' => empty($value['end_time']) ? time() : ($value['end_time'])
                        ]
                    ];
                    break;
                default:
                    if (is_array($value)) {
                        $where[$field] = [
                            'type' => 'in',
                            'value' => $value
                        ];
                    } else {
                        $where[$field] = [
                            'type' => 'eq',
                            'value' => $value
                        ];
                    }
            }
        }
        return $where;
    }


    /** 售后类型
     *
     * @param int $sourceType
     * @return mixed|string
     * @author liuming
     */
    private function getSourceName($sourceType = 0)
    {
        //1 :EC售后申请 ;2:其他wms_code售后申请 3 :整单取消;4 :OTO订单售后 5:价格保护',
        $sourceList = array(
            1 => '售后申请',
            2 => '售后申请',
            3 => '订单取消',
            4 => 'OTO订单售后',
            5 => '价格保护',
        );
        return $sourceList[$sourceType] ? $sourceList[$sourceType] : '其他退款';
    }

}
