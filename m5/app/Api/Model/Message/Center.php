<?php

namespace App\Api\Model\Message;

use Illuminate\Support\Facades\DB;

class Center
{
    /**
     * Notes: 创建消息中心
     * @param array $data
     * @return bool
     * Author: liuming
     * Date: 2021/1/21
     */
    public function createCenter($data = [])
    {
        if (empty($data)) {
            return false;
        }
        $data['params'] = isset($data['params']) ? json_encode($data['params']) : '';
        if (empty($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s', time());
        }
        $db = app('api_db');
        $insertId = $db->table('server_message_center')->insertGetId($data);
        return $insertId;
    }

    /**
     * Notes: 创建消息中心明细
     * @param array $data
     * @return bool
     * Author: liuming
     * Date: 2021/1/21
     */
    public function createCenterItems($data = [])
    {
        if (empty($data)) {
            return false;
        }

        $db = app('api_db');
        $res = $db->table('server_message_center_items')->insert($data);
        return $res;
    }

    /**
     * Notes: 查询消息中心数量
     * @param array $where
     * @param array $exWhere
     * @return mixed
     * Author: liuming
     * Date: 2021/1/21
     */
    public function findCenterCount($where = [], $exWhere = [])
    {
        $db = app('api_db');
        $query = $db->table('server_message_center');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                $query->where($whereV['key'],$whereV['express'],$whereV['value']);
            }
        }

        $count = $query->count();
        return $count;
    }

    /**
     * Notes: 查询消息中心列表
     * @param array $where
     * @param array $exWhere
     * @return mixed
     * Author: liuming
     * Date: 2021/1/21
     */
    public function findCenterList($where = [], $exWhere = [],$offset = 0,$limit = 20,$orderBy = ['id','desc'], $fileds  = ['*'])
    {
        $db = app('api_db');
        $query = $db->table('server_message_center');
            //->select('center.*','channel.channel')
            //->leftJoin('server_message_channel as channel','center.channel_id','=','channel.id');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                $query->where($whereV['key'],$whereV['express'],$whereV['value']);
            }
        }

        $list = $query->offset($offset)->limit($limit)->orderBy($orderBy[0],$orderBy[1])->get($fileds);
        return $list;

    }

    public function findRow($where = [], $exWhere = [])
    {
        $db = app('api_db');
        $query = $db->table('server_message_center');
            //->leftJoin('server_message_channel as channel','center.channel_id','=','channel.id');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                $query->where($whereV['key'],$whereV['express'],$whereV['value']);
            }
        }
        $row = $query->first();
        return $row;

    }

    /**
     * Notes: 获取消息明细
     * @param array $where
     * @param array $exWhere
     * @return mixed
     * Author: liuming
     * Date: 2021/1/25
     */
    public function findItemsList($where = [], $exWhere = [],$limit)
    {
        DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_center_items');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                $query->where($whereV['key'],$whereV['express'],$whereV['value']);
            }
        }

        $list = $query->limit($limit)->orderBy('id','asc')->get();
        //dd(dump(DB::getQueryLog()));
        return $list;

    }

    /**
     * Notes: 更新消息中心数据
     * @param array $where
     * @param array $data
     * @return bool
     * Author: liuming
     * Date: 2021/1/25
     */
    public function updateCenter($where = [],$data = [])
    {
        if (empty($where) || empty($data)) {
            return false;
        }

        $db = app('api_db');
        $res = $db->table('server_message_center')->where($where)->update($data);
        return $res;
    }

    public function addBatchItems($data){
        if (empty($data)) {
            return false;
        }

        $db = app('api_db');
        $res = $db->table('server_message_center_batch_items')->insert($data);
        return $res;
    }

    public function findBatchIds($id){
        $db = app('api_db');
        $query = $db->table('server_message_center_batch_items')->where('message_center_id',$id);

        $list = $query->get();
        return $list;
    }


    public function findItemscount($where = [], $exWhere = [])
    {
        DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_center_items');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                $query->where($whereV['key'],$whereV['express'],$whereV['value']);
            }
        }

        $count = $query->count();
        //dd(dump(DB::getQueryLog()));
        return $count;

    }

    public function updateSendStatusById($id, $status)
    {
        if(empty($id) || empty($status)){
            return false;
        }

        $res = app('api_db')->table('server_message_center')->where(array('id' => $id))->update(array('send_status' => $status));
        return $res;
    }
}
