<?php

namespace App\Api\Model\AfterSale;

use App\Api\Model\AfterSale\V2\AfterSaleProducts;

class AfterSale
{
    private static $_channel_name = 'service';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    /**
     * @param $afterSaleBn
     * @return mixed
     */
    public static function GetAfterSaleInfoByBn($afterSaleBn)
    {
        return app('api_db')->table('server_after_sales')->where('after_sale_bn', $afterSaleBn)->first();
    }

    public function publishMessage($after_sale_bn)
    {
        $mq = new \Neigou\AMQP();
        $routing_key = 'aftersale.status.update';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'after_sale_bn' => $after_sale_bn,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        return $res;
    }

    public function publishCreateMessage($after_sale_bn)
    {
        $mq = new \Neigou\AMQP();
        $routing_key = 'aftersale.status.create';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'after_sale_bn' => $after_sale_bn,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        \Neigou\Logger::General('after_sale_create_log_v1', array(
            'data' => $send_data,
            'reason' =>$res
        ));
        return $res;
    }

    /*
     * 写入数据
     */
    public function create($data)
    {
        return $this->_db->table('server_after_sales')->insert($data);
    }

    /*
     * 更新数据
     */
    public function update($where = array(), $param = array())
    {
        if (empty($where) || empty($param)) {
            return false;
        }
        return  $this->_db->table('server_after_sales')->where($where)->update($param);
    }

    /*
     * 获取总的提交记录
     */
    public function getAllAfterSaleOrder($orderId = 0, $productBn = 0)
    {
        if (empty($orderId) || empty($productBn)) {
            return false;
        }

        $where1 = function ($query) use($orderId,$productBn){
            $query->where(array('order_id'=>$orderId,'product_bn' => $productBn))->whereIn('status',array(1,2,4,5,9,10,11,12,14));
        };

        $where4 = function ( $query ) use ( $orderId, $productBn )
        {
            $query->where( array( 'order_id' => $orderId, 'product_bn' => $productBn ) )->where( 'status', 6 )->where( 'after_type', 1 );
        };

        $where2 = function ($query) use($orderId,$productBn){
            $query->where(array('order_id'=>$orderId,'product_bn' => $productBn,'status' => 7,'is_reissue' => 1));
        };
        $where3 = function ($query) use($orderId,$productBn){
            $query->where(array('order_id'=>$orderId,'product_bn' => $productBn,'status' => 13,'is_reissue' => 1));
        };
        return $res = $this->_db->table('server_after_sales')->where($where1)->orWhere($where2)->orWhere($where3)->orWhere($where4)->sum('product_num');

        //$sql = "SELECT sum(product_num) as total_product_num FROM server_after_sales WHERE (order_id = $orderId AND product_bn = '{$productBn}' AND status IN (1,2,4,5,6,9,10,11,12,14)) OR (order_id = $orderId AND product_bn = '{$productBn}'  AND status = 7 and is_reissue = 1) OR (order_id = $orderId AND product_bn = '{$productBn}' AND status = 13 and is_reissue = 1);";
        //$res = $this->_db->select($sql);
        //return empty($res[0]->aggregate) ? 0 : $res[0]->aggregate;
    }

    /*
     * 获取根订单商品数量
     */
    public function getAllRootOrderSaleNum($rootPid = 0, $productBn = '')
    {

        if (empty($rootPid) || empty($productBn)) {
            return false;
        }

        $sql = "SELECT sum(product_num) as roo_total_product_num FROM server_after_sales WHERE (root_pid = ? AND product_bn = ? AND status IN (1,2,4,5,9,10,11,12,14)) OR (root_pid = ? AND product_bn = ? AND status = 6 AND after_type = 1) OR (root_pid = ? AND product_bn = ?  AND status = 7 and is_reissue = 1) OR (root_pid = ? AND product_bn = ? AND status = 13 and is_reissue = 1);";

        $res = $this->_db->select($sql,array($rootPid,$productBn,$rootPid,$productBn,$rootPid,$productBn,$rootPid,$productBn));

        //$sql = "SELECT sum(product_num) as roo_total_product_num FROM server_after_sales WHERE (root_pid = $rootPid AND product_bn = '{$productBn}' AND status IN (1,2,4,5,6,9,10,11,12,14)) OR (root_pid = $rootPid AND product_bn = '{$productBn}'  AND status = 7 and is_reissue = 1) OR (root_pid = $rootPid AND product_bn = '{$productBn}' AND status = 13 and is_reissue = 1);";
        //$res = $this->_db->select($sql);

        return empty($res[0]->roo_total_product_num) ? 0 : $res[0]->roo_total_product_num;
    }

    /*
    * 获取根订单在途和已完成的商品数量
    */
    public function getAllRootOrderSaleV2( $rootPid = 0, $productBn = '' )
    {
        if ( empty( $rootPid ) || empty( $productBn ) )
        {
            return false;
        }

        $sql = "SELECT SUM(p.nums) AS total_product_num FROM server_after_sales s LEFT JOIN server_after_sales_products p ON s.`after_sale_bn` = p.`after_sale_bn` WHERE s.`root_pid` = ? AND p.product_bn = ? AND (s.status IN (1, 2, 4, 5, 9, 10, 11, 12, 14) OR (s.`status` = 6 AND s.`after_type` = 1) OR  (s.`status` = 13 AND is_reissue = 1) OR  (s.`status` = 7 AND is_reissue = 1));";

        $res = $this->_db->select( $sql, array( $rootPid, $productBn ) );

        return empty( $res[0]->total_product_num ) ? 0 : $res[0]->total_product_num;
    }

    /**
     * @param int $orderId
     * @param string $productBn
     * @return bool|int
     */
    public function getOrderSaleV2List( $orderId = 0, $productBn = '' )
    {
        if ( empty( $orderId ) || empty( $productBn ) )
        {
            return false;
        }

        $sql = "SELECT s.* FROM server_after_sales s LEFT JOIN server_after_sales_products p ON s.`after_sale_bn` = p.`after_sale_bn` WHERE s.`order_id` = ? AND p.product_bn = ? ";
        $data = $this->_db->select( $sql, array( $orderId, $productBn ) );

        if ( empty( $data ) )
        {
            return false;
        }

        $return = array();
        foreach ( $data as $item )
        {
            $return[] = get_object_vars( $item );
        }

        return $return;
    }

    /*
     * 获取售后单
     */
    public function getSearchPageList($where, $page = 1, $limit = 20, $order = null)
    {
        $return = array();
        if (empty($where)) {
            $sql = "SELECT count(*) count FROM server_after_sales";
            $listCount = $this->_db->selectOne($sql);
        } else {
            $sql = "SELECT count(*) count FROM server_after_sales WHERE {$where} ";
            $listCount = $this->_db->selectOne($sql);
        }
        $listCount = get_object_vars($listCount);
        $listCount = $listCount['count'];

        $offset = ($page - 1) * $limit;
        $offsetLimit = $offset . ',' . $limit;

        $totalPage = ceil($listCount / $limit);
        $orderBy = "ORDER BY ". ($order ? $order : 'update_time DESC,id DESC');

        if (empty($where)) {
            $sql = "SELECT * FROM server_after_sales {$orderBy} LIMIT $offsetLimit";
            $list = $this->_db->select($sql);
        } else {
            $sql = "SELECT *  FROM server_after_sales WHERE  {$where} {$orderBy} LIMIT $offsetLimit";
            $list = $this->_db->select($sql);
        }

        $after_sale_bns = array();
        foreach ($list as $item) {
            $return[$item->after_sale_bn] = get_object_vars($item);
            $after_sale_bns[] = $item->after_sale_bn;
        }

        //V1适配V2增加多商品字段
        $productModel = new AfterSaleProducts();
        $product_data = $productModel->getListByAfterSaleBns($after_sale_bns);
        foreach ($return as &$return_item) {
            $product_item = $product_data[$return_item['after_sale_bn']] ? $product_data[$return_item['after_sale_bn']] : [];
            $return_item['product_data'] = $product_item;

            //适配原表
            $current_product = current($product_item);
            $return_item['product_id'] = $current_product['product_id'];
            $return_item['product_bn'] = $current_product['product_bn'];
            $return_item['product_num'] = $current_product['nums'];
        }

        return array('page' => $page, 'totalCount' => $listCount, 'totalPage' => $totalPage, 'data' => $return);
    }


    /*
     * 根据条件获取多条售后单
     */
    public function getList($condition, $param)
    {
        $return = array();
        if ($condition == 'whereIn') {
            $list = $this->_db->table('server_after_sales')->whereIn($param['key'], $param['value'])->orderBy('id',
                'desc')->get()->toArray();
        } else {
            $list = $this->_db->table('server_after_sales')->where($param)->orderBy('id', 'desc')->get()->toArray();
        }
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    /*
     * 获取单个售后数据
     */
    public function getOne($param)
    {
        if (empty($param)) {
            return array();
        }
        $list = $this->_db->table('server_after_sales')->where($param)->first();
        return get_object_vars($list);
    }

    public function addLog($param)
    {
        if (empty($param)) {
            return false;
        }
        return $this->_db->table('server_after_sales_log')->insert($param);
    }

    public function getLog($param)
    {
        if (empty($param)) {
            return false;
        }
        $list = $this->_db->table('server_after_sales_log')->where($param)->orderBy('id', 'desc')->get()->toArray();
        $return = array();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    public function addRemark($param)
    {
        if (empty($param)) {
            return false;
        }
        return $this->_db->table('server_after_sales_remark')->insert($param);
    }

    public function getRemark($param)
    {
        if (empty($param)) {
            return false;
        }
        $list = $this->_db->table('server_after_sales_remark')->where($param)->orderBy('id', 'desc')->get()->toArray();
        $return = array();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    public function addDescribe($param)
    {
        if (empty($param)) {
            return false;
        }

        return $this->_db->table('server_after_sales_describe')->insert($param);
    }

    public function getDescribe($param)
    {
        if (empty($param)) {
            return false;
        }
        $list = $this->_db->table('server_after_sales_describe')->where($param)->orderBy('id', 'desc')->get()->toArray();
        $return = array();
        foreach ($list as $item) {
            $return[] = get_object_vars($item);
        }
        return $return;
    }

    /*
     * 仓库单
     */
    public function addWareHouse($param)
    {
        if (empty($param)) {
            return false;
        }
        return $this->_db->table('server_after_sales_warehouse')->insert($param);
    }

    public function updateWareHouse($where, $param)
    {
        if (empty($param)) {
            return false;
        }
        return $this->_db->table('server_after_sales_warehouse')->where($where)->update($param);
    }

    /*
     * 获取仓库单
     */
    public function getWareHousePageList($where, $page = 1, $limit = 20)
    {
        $return = array();
        if (empty($where)) {
            $sql = "SELECT count(*) count FROM server_after_sales_warehouse";
            //$listCount = $this->_db->selectOne($sql);
        } else {
//            $sql = "SELECT count(*) count FROM server_after_sales_warehouse ware  WHERE {$where} ";
            $sql = "SELECT count(*) count  FROM server_after_sales_warehouse ware join server_after_sales sales on ware.after_sale_bn = sales.after_sale_bn WHERE  {$where}";
            //$listCount = $this->_db->selectOne($sql);
        }

        $listCount = $this->_db->table($this->_db->raw("($sql) as t"))->first();
        $listCount = get_object_vars($listCount);
        $listCount = $listCount['count'];

        $offset = ($page - 1) * $limit;
        $offsetLimit = $offset . ',' . $limit;

        $totalPage = ceil($listCount / $limit);

        if (empty($where)) {
            $sql = "SELECT * FROM server_after_sales_warehouse ORDER BY id DESC LIMIT $offsetLimit";
            //$list = $this->_db->select($sql);
        } else {
            $sql = "SELECT ware.*,sales.pop_owner_id,sales.status as sale_status,sales.company_id,sales.is_reissue  FROM server_after_sales_warehouse ware join server_after_sales sales on ware.after_sale_bn = sales.after_sale_bn WHERE  {$where} ORDER BY ware.id DESC LIMIT $offsetLimit";
            //$list = $this->_db->select($sql);
        }

        $list = $this->_db->table($this->_db->raw("($sql) as t"))->get()->toArray();
        foreach ($list as $item) {
            $return[$item->after_sale_bn] = get_object_vars($item);
        }

        return array('page' => $page, 'totalCount' => $listCount, 'totalPage' => $totalPage, 'data' => $return);
    }

    /*
     *获取最后提交的单个仓储单
     */
    public function getWareHouse($where)
    {
        return $this->_db->table('server_after_sales_warehouse')->where($where)->orderBy('id', 'desc')->first();
    }

    public function getWareHouseList($where)
    {
        $sql = "SELECT *  FROM server_after_sales_warehouse WHERE  {$where} ORDER BY id DESC";
        //$list = $this->_db->select($sql);
        $list = $this->_db->table($this->_db->raw("($sql) as t"))->get()->toArray();
        $return = array();
        foreach ($list as $item) {
            $return[$item->warehouse_bn] = get_object_vars($item);
        }
        return $return;
    }


    public function createImage($data = array())
    {
        if (empty($data)) {
            return false;
        }
        return $this->_db->table('server_after_sales_image')->insertGetId($data);
    }

    public function getImage($data = array(), $is_new = 0)
    {
        if (empty($data)) {
            return array();
        }
        $list = $this->_db->table('server_after_sales_image')->whereIn('id', $data)->select('url',
            'id')->get()->toArray();
        $return = array();
        if ($is_new) {
            foreach ($list as $item) {
                $_data = get_object_vars($item);
                $return[$_data['id']] = $_data['url'];
            }
        } else {
            foreach ($list as $item) {
                $_data = get_object_vars($item);
                $return[] = $_data['url'];
            }
        }

        return $return;
    }

    public function voucherRuleList()
    {
        $sql = "SELECT *  FROM server_after_sales_back_voucher  ORDER BY id DESC";
        $list = $this->_db->select($sql);
        $return = array();
        foreach ($list as $item) {
            $return[$item->id] = get_object_vars($item);
        }
        return $return;
    }

    public function appoint($param = array())
    {
        if (!$param['after_sale_bns'] || !$param['customer_service'] || !is_array($param['after_sale_bns'])) {
            return false;
        }

        return $this->_db->table('server_after_sales')->whereIn('after_sale_bn',
            $param['after_sale_bns'])->update(['customer_service' => $param['customer_service']]);
    }
}


