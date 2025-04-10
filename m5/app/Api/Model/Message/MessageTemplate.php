<?php

namespace App\Api\Model\Message;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MessageTemplate
{
    private $db;

    public function __construct()
    {
        $this->db = app('api_db');
    }

    public function insertTemplate($data)
    {
        $data['param'] = json_encode($data['param']);
        $data['created_at'] = Carbon::now()->toDateTime();
        return $this->db->table('server_message_template')->insertGetId($data);
    }

    public function updateTemplate($id, $data)
    {
        //过滤掉无用参数
        if (isset($data['param']) && is_array($data['param'])) {
            $data['param'] = json_encode($data['param']);
        }
        $data['update_at'] = Carbon::now()->toDateTime();
        return $this->db->table('server_message_template')->where('id', $id)->update($data);
    }

    /**
     * Notes: 更新发送渠道模板
     * @param $id
     * @param $data
     * @return mixed
     * Author: liuming
     * Date: 2021/3/18
     */
    public function updateTemplateChannel($templateId, $data)
    {
        return $this->db->table('server_message_template_channel')->where('template_id', $templateId)->update($data);
    }


    public function templateExists($field, $value,$isDelete)
    {
        $db = $this->db->table('server_message_template');
        $where = [
            $field => $value
        ];
        if ($isDelete){
            $where['is_delete'] = $isDelete;
        }
        $db->where($where);
        $res = $db->exists();
        return $res;
    }

    public function getTemplate($name = null)
    {
        $query = $this->db->table('server_message_template');
        if ($name) {
            $query = $query->where('name', $name);
        }
        return $query->get()
            ->map(function ($item, $key) {
                $item->param = json_decode($item->param);
                return $item;
            });
    }

    public function firstTemplate($id,$channelId = 0)
    {
        $query = $this->db->table('server_message_template as t')
            ->select('t.*')
            ->leftJoin('server_message_template_channel as tc', 't.id', '=', 'tc.template_id')
            ->where('t.id', $id);
        if (!empty($channelId)){
            $query->where('tc.channel_id',$channelId);
        }
        return $query->first();
    }

    public function getPlatformTemplate($templateId, $channelId)
    {
        return $this->db->table('server_message_template as t')
            ->leftJoin('server_message_template_channel as tc', 't.id', '=', 'tc.template_id')
            ->where('t.id', $templateId)
            ->where('tc.channel_id', $channelId)
            ->get()
            ->map(function ($item, $key) {
                $item->param = json_decode($item->param);
                if ($item->param_mapping) {
                    $item->param_mapping = json_decode($item->param_mapping, true);
                }
                return $item;
            })
            ->first();
    }

    /**
     * Notes:模版和渠道绑定
     * @param $data 新增的数据
     * @return mixed
     * Author: liuming
     * Date: 2021/1/15
     */
    public function insertTemplateChannel($data)
    {
        $data['created_at'] = Carbon::now()->toDateTime();
        return $this->db->table('server_message_template_channel')->insertGetId($data);
    }

    /**
     * Notes: 批量新增
     * @param $data
     * @return mixed
     * Author: liuming
     * Date: 2021/1/27
     */
    public function batchInsert($data)
    {
        foreach ($data as &$v){
            $v['created_at'] = Carbon::now()->toDateTime();
        }
        return $this->db->table('server_message_template_channel')->insert($data);
    }

    /**
     * Notes: 查询模板列表
     *
     * @param array $where
     * @param int $offset
     * @param int $limit
     * @return mixed
     * Author: liuming
     * Date: 2021/1/19
     */
    public function findTemplateList($where = [],$offset = 0,$limit = 20,$order = 'id',$sort = 'desc')
    {
        DB::connection()->enableQueryLog();  // 开启QueryLog
        $query = $this->db->table('server_message_template as template')
            ->select('template.*');
            //->leftJoin('server_message_template_channel as template_channel','template.id','=','template_channel.template_id');
        if ($where) {
            $query = $query->where($where);
        }
        $res =  $query->offset($offset)->limit($limit)->orderBy($order,$sort)->get()
            ->map(function ($item, $key) {
                $item->param = json_decode($item->param);
                return $item;
            });
        //dd(dump(DB::getQueryLog()));
        return $res;
    }

    /**
     * Notes: 获取所有复合模板的信息
     * @param array $where
     * @return mixed
     * Author: liuming
     * Date: 2021/1/27
     */
    public function findTemplateAll($where = [],$exWhere = [])
    {

        $query = $this->db->table('server_message_template');
        if ($where) {
            $query = $query->where($where);
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
        $res =  $query->get()
            ->map(function ($item, $key) {
                $item->param = json_decode($item->param);
                return $item;
            });
        return $res;
    }

    /**
     * Notes: 获取模板数量
     * @param array $where
     * @return mixed
     * Author: liuming
     * Date: 2021/1/19
     */
    public function findTemplateCount($where = [])
    {
        $query = $this->db->table('server_message_template as template')
            ->select('template.*');
            //->join('server_message_template_channel as template_channel','template.id','=','template_channel.template_id');
        if ($where) {
            $query = $query->where($where);
        }
        return $query->count();
    }

    public function deleteTemplateByTemplateAndChannelIds($templateId,$channelIds){
        if (empty($templateId) || empty($channelIds)){
            return false;
        }

        return $this->db->table('server_message_template_channel')->where('template_id',$templateId)->whereNotIn('channel_id',$channelIds)->delete();
    }
}
