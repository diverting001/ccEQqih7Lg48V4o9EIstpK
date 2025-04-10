<?php
namespace App\Api\Model\Message;

use Illuminate\Support\Facades\DB;

class ChannelPush{

    /**
     * Notes: 获取一行记录
     * @param array $where
     * @return mixed
     * Author: liuming
     * Date: 2021/1/20
     */
    public function findRow($where = []){
        DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_channel_push as channel_push')
            ->leftJoin('server_message_channel as channel','channel_push.channel_id','=','channel.id')
            ->select('channel_push.*','channel.channel','channel.type as channel_type');
        if ($where){
            $query->where($where);
        }
        $row =$query->first();
        //dd(dump(DB::getQueryLog()));
        return $row;
    }

    /**
     * Notes: 获取列表
     * @param array $where
     * @param array $exWhere
     * @return mixed
     * Author: liuming
     * Date: 2021/1/20
     */
    public function findList($where = [],$exWhere = []){
        DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_channel_push');
            //->leftJoin('server_message_channel as channel','channel_push.channel_id','=','channel.id')
            //->select('channel_push.*','channel.channel','channel.type as channel_type');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                if ($whereV['express'] == 'in'){
                    $query->whereIn($whereV['key'],$whereV['value']);
                }else{
                    $query->where($whereV['key'],$whereV['express'],$whereV['value']);
                }
            }
        }
        $list =$query->get();
        //dd(dump(DB::getQueryLog()));
        return $list;
    }

    public function findChannelCompanyList($where = [],$exWhere = []){
        //DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_channel_push_company as channel_push_company')
            ->select('*');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                if ($whereV['express'] == 'in'){
                    $query->whereIn($whereV['key'],$whereV['value']);
                }else{
                    $query->where($whereV['key'],$whereV['express'],$whereV['value']);
                }
            }
        }
        $list =$query->get();
        //dd(dump(DB::getQueryLog()));
        return $list;
    }

    /**
     * Notes: 创建渠道推送
     * @param array $data
     * @return bool
     * Author: liuming
     * Date: 2021/1/20
     */
    public function createChannelPush($data = []){
        if (empty($data)){
            return false;
        }
        if (empty($data['created_at'])){
            $data['created_at'] = date('Y-m-d H:i:s',time());
        }
        $db = app('api_db');
        $insertId = $db->table('server_message_channel_push')->insertGetId($data);
        return $insertId;
    }

    /**
     * Notes:更新渠道推送
     * @param array $where
     * @param array $data
     * @return bool
     * Author: liuming
     * Date: 2021/1/20
     */
    public function updateChannelPush($where = [],$data = []){
        if (empty($data) || empty($where)){
            return false;
        }
        if (empty($data['created_at'])){
            $data['created_at'] = date('Y-m-d H:i:s',time());
        }
        $db = app('api_db');
        return $db->table('server_message_channel_push')->where($where)->update($data);
    }

    /**
     * Notes: 创建渠道推送公司明细
     * @param array $data
     * @return bool
     * Author: liuming
     * Date: 2021/1/20
     */
    public function createPushCompany($data = []){
        if (empty($data)){
            return false;
        }
        $db = app('api_db');
        $res = $db->table('server_message_channel_push_company')->insert($data);
        return $res;
    }

    /**
     * Notes:删除推送公司明细
     * @param array $where
     * @return bool
     * Author: liuming
     * Date: 2021/1/20
     */
    public function deletePushCompany($where = []){
        if (empty($where)){
            return  false;
        }
        $db = app('api_db');
        $res = $db->table('server_message_channel_push_company')->where($where)->delete();
        return $res;
    }

    /**
     * Notes: 删除渠道推送
     * @param array $where
     * @return bool
     * Author: liuming
     * Date: 2021/2/3
     */
    public function deleteChannelPush($where = []){
        if (empty($where)){
            return  false;
        }
        $db = app('api_db');
        $res = $db->table('server_message_channel_push')->where($where)->delete();
        return $res;
    }

    /**
     * Notes: 获取公司和渠道推送信息
     * @param array $where
     * @param array $exWhere
     * @return mixed
     * Author: liuming
     * Date: 2021/1/21
     */
    public function findChannelAndCompanyList($where = [],$exWhere = []){
        //DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_channel_push as channel_push')
            //->leftJoin('server_message_channel as channel','channel_push.channel_id','=','channel.id')
            ->leftJoin('server_message_channel_push_company as channel_push_company','channel_push.id','=','channel_push_company.channel_push_id')
           // ->select('channel_push.*','channel.channel','channel.type as channel_type','channel_push_company.company_id');
            ->select('channel_push.*','channel_push_company.company_id');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                $query->where($whereV['key'],$whereV['express'],$whereV['value']);
            }
        }
        $row =$query->get();
        //dd(dump(DB::getQueryLog()));
        return $row;
    }

    public function findBaseAll($where = [],$exWhere=[]){
        //DB::connection()->enableQueryLog();  // 开启QueryLog
        $db = app('api_db');
        $query = $db->table('server_message_channel_push');
        if ($where){
            $query->where($where);
        }
        if ($exWhere){
            foreach ($exWhere as $whereV){
                if ($whereV['express'] == 'in'){
                    $query->whereIn($whereV['key'],$whereV['value']);
                }else{
                    $query->where($whereV['key'],$whereV['express'],$whereV['value']);
                }
            }
        }
        $list =$query->get();
        //dd(dump(DB::getQueryLog()));
        return $list;
    }

    /**
     * 获取与某公司相关的推送/排除列表
     * @param $companyId
     * @return mixed
     */
    public function getCompanyChannelPushIds($companyId)
    {
        return app('api_db')
            ->table('server_message_channel_push_company')
            ->where('company_id', $companyId)
            ->pluck('channel_push_id');
    }

}
