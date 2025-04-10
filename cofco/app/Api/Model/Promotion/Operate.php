<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/31
 * Time: 11:24
 */

namespace App\Api\Model\Promotion;

use Neigou\Logger;
use Neigou\RedisClient;

class Operate
{

    protected $_db;
    private $_table;
    private $_cache_version = 'v3';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    private function setTable($table)
    {
        $this->_table = $table;
    }

    /**
     * 获取Promotion list
     * @param $limit
     * @return mixed
     */
    public function ruleList($limit)
    {
        $this->setTable('server_promotion_edit');
        $where = [
            ['status', '!=', 'deleted']
        ];
        $data['count'] = $this->_db->table($this->_table)->where($where)->count();
        $data['list'] = $this->_db->table($this->_table)
            ->where($where)
            ->orderBy('id', 'desc')
            ->offset($limit['offset'])
            ->limit($limit['limit'])
            ->get()
            ->all();
        return $data;
    }


    /**
     * 获取company item list
     * @param $table
     * @param $rule_id
     * @param $type
     * @return mixed
     */
    public function specialList($table, $rule_id, $type = '', $limit)
    {
        $this->setTable('server_promotion_' . $table);
        $where = [
            ['pid', $rule_id],
            ['affect', $type]
        ];
        $data['count'] = $this->_db->table($this->_table)->where($where)->count();
        $data['list'] = $this->_db->table($this->_table)->where($where)->offset(intval($limit['offset']))->limit(intval($limit['limit']))->get()->all();
        return $data;
    }


    /**
     * 获取一条promotion
     * @param $rid
     * @param $table
     * @return mixed
     */
    public function findOne($rid, $table = '_edit')
    {
        $this->setTable('server_promotion' . $table);
        return $this->_db->table($this->_table)->find($rid);
    }

    /**
     * 修改和创建Promotion Rule
     * @param $rid
     * @param $data
     * @return mixed
     */
    public function execPromotion($rid, $data)
    {
        $this->setTable('server_promotion_edit');
        $sync_status = array('disable', 'deleted');
        if ($rid > 0) {//edit
            $data['updated_at'] = date('Y-m-d H:i:s', time());
            $data['push_status'] = 'offline';
            $data['companys'] = json_encode($data['companys']);
            if (in_array($data['status'], $sync_status)) {
                //同步线上停用或者删除策略
                $sync_data['status'] = $data['status'];
                $this->updateBasic($rid, $sync_data);
            }
            $rzt = $this->_db->table($this->_table)->where('id', $rid)->update($data);
            Logger::General('promotion.updateRule', array('fparam1' => $rid, 'data' => $data, 'result' => $rzt));
        } else {//create
            $data['created_at'] = date('Y-m-d H:i:s', time());
            $data['push_status'] = 'offline';
            $data['companys'] = json_encode($data['companys']);
            $rzt = $this->_db->table($this->_table)->insertGetId($data);
            Logger::General('promotion.createRule', array('fparam1' => $rzt, 'data' => $data, 'result' => $rzt));
        }
        return $rzt;
    }

    /**
     * 修改运营 表
     * @param $rid
     * @param $data
     * @return mixed
     */
    public function updateBasic($rid, $data)
    {
        $rzt = $this->_db->table('server_promotion')->where('id', $rid)->update($data);
        Logger::General('promotionBasic.updateRule', array('fparam1' => $rid, 'data' => $data, 'result' => $rzt));
        return $rzt;
    }

    /**
     * 修改和创建Promotion Rule
     * @param $rid
     * @param $data
     * @return mixed
     */
    public function execPromotionBasic($rid, $data)
    {
        unset($data['items']);
        unset($data['companys']);
        unset($data['push_status']);
        $this->setTable('server_promotion');
        $info = $this->findOne($rid, '');
        if ($info->id > 0) {//edit
            $data['updated_at'] = date('Y-m-d H:i:s', time());
            $rzt = $this->updateBasic($rid, $data);
        } else {//create
            $data['created_at'] = date('Y-m-d H:i:s', time());
            $data['id'] = $rid;
            $rzt = $this->_db->table($this->_table)->insert($data);
            Logger::General('promotionBasic.createRule', array('fparam1' => $rzt, 'data' => $data, 'result' => $rzt));
        }
        return $rzt;
    }

    /**
     * 检查item是否存在
     * @param $item_id ITEM_ID
     * @param $rid RULE_ID
     * @param string $type AFFECT
     * @return bool
     */
    public function checkItem($item_id, $rid, $type = '')
    {
        $where = [
            ['item_id', $item_id],
            ['pid', $rid],
            ['affect', $type]
        ];
        $count = $this->_db->table('server_promotion_item')->where($where)->count();
        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function checkItems($item_id, $rid, $type = '')
    {
        $where = [
            ['pid', $rid],
            ['affect', $type]
        ];
        $list = $this->_db->table('server_promotion_edit_item')->whereIn('item_id',
            $item_id)->where($where)->select('item_id')->get()->all();
        return $list;
    }

    /**
     * 新增可以执行Rule的公司
     * @param $data
     * @return mixed
     */
    public function insertCompany($data)
    {
        $insert['pid'] = $data['pid'];
        $insert['company_id'] = $data['company_id'];
        $rzt = $this->_db->table('server_promotion_company')->insertGetId($insert);
        Logger::General('promotion.insertCompany', array('fparam1' => $rzt, 'data' => $insert, 'result' => $rzt));
        if ($rzt <= 0) {
            return false;
        }
        return $rzt;
    }

    /**
     * 插入一条新数据 item
     * @param array $data
     * @return bool
     */
    public function insertItem($data = array())
    {
        $insert['pid'] = $data['pid'];
        $insert['item_id'] = $data['item_id'];
        $insert['affect'] = $data['affect'];
        $rzt = $this->_db->table('server_promotion_edit_item')->insertGetId($insert);
        Logger::General('promotion.insertItem', array('fparam1' => $rzt, 'data' => $insert, 'result' => $rzt));
        if ($rzt <= 0) {
            return false;
        }
        return $rzt;
    }

    public function insertItemDest($data = array())
    {
        $insert['pid'] = $data['pid'];
        $insert['item_id'] = $data['item_id'];
        $insert['affect'] = $data['affect'];
        $rzt = $this->_db->table('server_promotion_item')->insertGetId($insert);
        Logger::General('promotion.insertItemDest', array('fparam1' => $rzt, 'data' => $insert, 'result' => $rzt));
        if ($rzt <= 0) {
            return false;
        }
        return $rzt;
    }

    /**
     * 删除一个关联公司
     * @param $id
     * @return mixed
     */
    public function delCompany($id)
    {
        $rzt = $this->_db->table('server_promotion_company')->where('id', $id)->delete();
        Logger::General('promotion.deleteCompany', array('fparam1' => $rzt, 'data' => $id, 'result' => $rzt));
        return $rzt;
    }

    /**
     * 删除一个Item
     * @param $data
     * @return mixed
     */
    public function delItem($data)
    {
        $where = [
            ['item_id', $data['item_id']],
            ['pid', $data['pid']],
            ['affect', $data['affect']],
        ];
        $rzt = $this->_db->table('server_promotion_edit_item')->where($where)->delete();
        Logger::General('promotion.deleteItem', array('fparam1' => $rzt, 'data' => $data, 'result' => $rzt));
        return $rzt;
    }

    public function delItemDest($data)
    {
        $where = [
            ['item_id', $data['item_id']],
            ['pid', $data['pid']],
            ['affect', $data['affect']],
        ];
        $rzt = $this->_db->table('server_promotion_item')->where($where)->delete();
        Logger::General('promotion.deleteItemDest', array('fparam1' => $rzt, 'data' => $data, 'result' => $rzt));
        return $rzt;
    }

    /**
     * 获取公司列表 运营规则 作用表
     * @param $company_id
     * @param $rid
     * @return mixed
     */
    public function findCompany($rid, $company_id)
    {
        $where = [
            ['company_id', $company_id],
            ['pid', $rid]
        ];
        return $this->_db->table('server_promotion_company')->where($where)->count();
    }

    /**
     * 获取规则对应的列表
     * @param $rid
     * @param $type String item company
     * @return mixed
     */
    public function itemList($rid, $type = '')
    {
        return $this->_db->table('server_promotion_' . $type)->where('pid', $rid)->get()->all();
    }

    public function pushRule($rid)
    {
        //获取rule basic信息
        $basic = $this->findOne($rid);
        $basic = json_decode(json_encode($basic), true);
        $this->_db->beginTransaction();
        //修改basic信息
        unset($basic['id']);
        $basic['status'] = 'enable';
        $rzt = $this->execPromotionBasic($rid, $basic);
        Logger::General('pushRule',
            array('remark' => '1.update basic', 'data' => $basic, 'fparam1' => $rid, 'result' => $rzt));
        if ($rzt <= 0) {
            //basic修改成功
            $this->_db->rollback();
            Logger::General('promotion.pushRule',
                array('remark' => 'basic rule update fail', 'data' => $rid, 'result' => $rzt));
            return false;
        }
        //修改公司信息
        $companys = json_decode($basic['companys'], true);
        $source = $this->itemList($rid, 'company');
        foreach ($source as $key => $info) {
            if (!in_array($info->company_id, $companys)) {
                $this->delCompany($info->id);
                $out['del'][] = $info->company_id;
            }
        }
        //已经消失的规则 需要删除
        foreach ($companys as $company_id) {
            $rzt = $this->findCompany($rid, $company_id);
            if ($rzt <= 0) {
                $data['pid'] = $rid;
                $data['company_id'] = $company_id;
                $this->insertCompany($data);
                $out['add'][] = $company_id;
            }
        }
        Logger::General('pushRule',
            array('remark' => '2.update company', 'data' => $source, 'companys' => $companys, 'result' => $out));
        //获取最新的ITEM信息
        $edit_list = $this->_db->table('server_promotion_edit_item')->where('pid', $rid)->get()->all();
        $www_list = $this->_db->table('server_promotion_item')->where('pid', $rid)->get()->all();
        $redis = new RedisClient();

        foreach ($www_list as $key => $item) {
            $rzt = $this->checkItems(array($item->item_id), $rid, $item->affect);//dest和edit对比 如果edit不存在 执行删除操作
            if (count($rzt) <= 0) {
                //edit版本不存在 执行删除
                $del_data['pid'] = $rid;
                $del_data['item_id'] = $item->item_id;
                $del_data['affect'] = $item->affect;
                $this->delItemDest($del_data);
                $item_out['del'][] = $item->item_id;
                $cache_key = 'service:promotion:' . $this->_cache_version . ':' . $item->item_id;
                $redis->_redis_connection->del($cache_key);
            }
        }
        foreach ($edit_list as $key => $item) {
            $rzt = $this->checkItem($item->item_id, $rid, $item->affect);//edit 和dest对比 如果dest不存在 执行新增
            if ($rzt <= 0) {
                //edit版本不存在 执行删除
                $insert_data['pid'] = $rid;
                $insert_data['item_id'] = $item->item_id;
                $insert_data['affect'] = $item->affect;
                $this->insertItemDest($insert_data);
                $item_out['add'][] = $insert_data;
                $cache_key = 'service:promotion:' . $this->_cache_version . ':' . $item->item_id;
                $redis->_redis_connection->del($cache_key);
            }
        }
        Logger::General(
            'pushRule',
            array(
                'remark' => '3.update item',
                'data' => $source,
                '$www_list' => $edit_list,
                'result' => $item_out
            )
        );
        //修改edit表中的推送字段为已经推送
        $set['push_status'] = 'online';
        $set['status'] = 'enable';
        $this->_db->table('server_promotion_edit')->where('id', $rid)->update($set);
        $this->_db->commit();
        return true;
    }

}
