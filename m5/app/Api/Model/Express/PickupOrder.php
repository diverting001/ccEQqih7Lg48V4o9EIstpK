<?php

namespace App\Api\Model\Express;

use Illuminate\Database\Eloquent\Model;


class PickupOrder extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'server_express_pickup_order';

    public $timestamps = false;


    //未提交
    const STATUS_READY = 0;
    //已接单
    const STATUS_ORDERED = 1;
    //已下发
    const STATUS_DISTRIBUTE = 2;
    //揽收
    const STATUS_COLLECT = 3;
    //运输中
    const STATUS_TRANSPORT = 4;
    //派送中
    const STATUS_DELIVERY = 5;
    //妥投
    const STATUS_RECEIVED = 6;
    //拒签
    const STATUS_REFUSAL = 7;
    //已拦截
    const STATUS_INTERCEPT = 8;
    //取消
    const STATUS_CANCEL = 9;
    //异常
    const STATUS_BLOCKED = 10;

    /**
     * 状态
     * @var string[]
     */
    public static $statusMsg = array(
        self::STATUS_READY => '未提交',
        self::STATUS_ORDERED => '已接单',
        self::STATUS_DISTRIBUTE => '已下发',
        self::STATUS_COLLECT => '揽收',
        self::STATUS_TRANSPORT => '运输中',
        self::STATUS_DELIVERY => '派送中',
        self::STATUS_RECEIVED => '妥投',
        self::STATUS_REFUSAL => '拒签',
        self::STATUS_INTERCEPT => '已拦截',
        self::STATUS_CANCEL => '取消',
        self::STATUS_BLOCKED => '异常',
    );

    /**
     * @param $applyNo
     * @return array
     */
    public function getPickupOrderByApplyNo($applyNo) {
        $where = [
            'apply_no' => $applyNo,
        ];
        $res = $this->where($where)->first();
        return empty($res) ? [] : $res->toArray();
    }

    /**
     * @param $orderNo
     * @return array
     */
    public function getPickupOrderByOrderNo($orderNo) {
        $where = [
            'order_no' => $orderNo,
        ];
        $res = $this->where($where)->first();
        return empty($res) ? [] : $res->toArray();
    }

    /**
     * @param $orderData
     * @return mixed
     */
    public function savePickupOrder($orderData) {
        return $this->insertGetId($orderData);
    }

    /**
     * @param $id
     * @param $update
     * @return mixed
     */
    public function updatePickupOrderById($id, $update) {
        return $this->where('id', $id)->update($update) !== false;
    }

    public function getPickupOrderList($field = '*', $where = [], $whereIn = [], $limit = 10, $order = 'id desc') {
        $model = $this->where($where);
        if (!empty($whereIn)) {
            $model->whereIn($whereIn[0], $whereIn[1]);
        }
        return $model->select($field)->orderByRaw($order)->limit($limit)->get()->toArray();
    }


    public function getOrderLock($id) {
        $res = $this->where('id', $id)->lockForUpdate()->first();
        return empty($res) ? [] : $res->toArray();

    }
}
