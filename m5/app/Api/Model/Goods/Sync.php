<?php
/**
 * Created by PhpStorm.
 * User: xu peng
 */

namespace App\Api\Model\Goods;

use Neigou\Logger;

class Sync
{
    /**
     * 商品同步
     */
    const TABLE_GOODS_SYNC = 'server_goods_sync';

    /**
     * 商品同步日志
     */
    const TABLE_GOODS_SYNC_LOG = 'server_goods_sync_log';

    /**
     * 商品同步消息
     */
    const TABLE_GOODS_SYNC_MESSAGE = 'server_goods_sync_message';

    /**
     * 商品同步审核日志
     */
    const TABLE_GOODS_SYNC_REVIEW_LOG = 'server_goods_sync_review_log';

    /**
     * Invoice constructor.
     *
     * @param   string $db
     */
    public function __construct($db = '')
    {
        $this->_db = app('api_db');
    }

    /**
     * 获取商品同步信息
     *
     * @param   $productBn   string   货品编码
     * @return  array
     */
    public function getGoodsSyncDetail($productBn)
    {
        $where = array(
            array('product_bn', $productBn),
        );

        $data = $this->_db->table(self::TABLE_GOODS_SYNC)->where($where)->first();

        return $data ? get_object_vars($data) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 添加商品同步信息
     *
     * @param   $data   array   商品信息
     * @return  mixed
     */
    public function addGoodsSync($data)
    {
        return app('api_db')->table(self::TABLE_GOODS_SYNC)->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 更新商品同步信息
     *
     * @param   $productBn      string  货品编码
     * @param   $updateData     array   商品信息
     * @return  mixed
     */
    public function updateGoodsSync($productBn, $updateData)
    {
        $where = array(
            'product_bn' => $productBn,
        );

        if ( ! isset($updateData['last_sync_time']))
        {
            $updateData['last_sync_time'] = time();
        }

        $result = $this->_db->table(self::TABLE_GOODS_SYNC)->where($where)->update($updateData);

        return $result ? true : false;
    }

    // --------------------------------------------------------------------

    /**
     * 添加商品同步日志
     *
     * @param   $data   array   商品信息
     * @return  mixed
     */
    public function addGoodsSyncLog($data)
    {
        return app('api_db')->table(self::TABLE_GOODS_SYNC_LOG)->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 添加商品同步消息
     *
     * @param   $data   array   商品信息
     * @return  mixed
     */
    public function addGoodsSyncMessage($data)
    {
        return app('api_db')->table(self::TABLE_GOODS_SYNC_MESSAGE)->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取商品同步消息
     *
     * @param   $type       int     类型(1:新增/全部 2:基础信息 3:核心价格上下架)
     * @param   $status     int     状态(0:待处理 1:待审核 2:待同步 3:同步中 4:同步成功 5:同步失败)
     * @return  mixed
     */
    public function getGoodsSyncMessageOne($type, $status = 0)
    {
        $where = array(
            array('type', $type),
            array('status', $status),
        );
        $data = $this->_db->table(self::TABLE_GOODS_SYNC_MESSAGE)->where($where)->orderBy('message_id', 'asc')->first();

        return $data ? get_object_vars($data) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 添加商品同步审核日志
     *
     * @param   $data   array   商品信息
     * @return  mixed
     */
    public function addGoodsSynReviewLog($data)
    {
        return app('api_db')->table(self::TABLE_GOODS_SYNC_REVIEW_LOG)->insertGetId($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取商品同步消息详情
     *
     * @param   $productBn  string  货品编码
     * @param   $type       int     类型(1:新增/全部 2:基础信息 3:核心价格上下架)
     * @param   $status     mixed   状态(0:待处理 1:待审核 2:待同步 3:同步中 4:同步成功 5:同步失败)
     * @return  array
     */
    public function getGoodsSyncMessageDetailByProduct($productBn, $type, $status = 0)
    {
        $where = array(
            array('product_bn', $productBn),
            array('type', $type),
            array('status', $status),
        );
        $data = $this->_db->table(self::TABLE_GOODS_SYNC_MESSAGE)->where($where)->orderBy('message_id', 'asc')->first();

        return $data ? get_object_vars($data) : array();
    }

}
