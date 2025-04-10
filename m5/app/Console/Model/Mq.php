<?php
namespace App\Console\Model;
use App\Api\Model\BaseModel ;

class Mq  extends  BaseModel
{
    protected $table = 'server_mq' ;
    protected $primaryKey = 'mq_id' ;
    /*
     *  获取消息
     */
    public function GetMessage($type,$limit=20){
        if(empty($type))  {
            return false;
        }
        $where  = " type = '{$type}' and status = 'ready'";
        $sql = "select * from `server_mq` where {$where} order by mq_id asc limit {$limit} ";
        return $this->select($sql);
    }

     /*
      * 消息删除
      */
    public function DelMessage($ids){
        if(empty($ids) || !is_array($ids)) return array();
         return  $this->getBaseTable()->whereIn('mq_id' , $ids)->delete() ;
    }

    public function UpMessageStatus($mq_ids,$status){
        if(empty($mq_ids) || empty($status)) {
            return false;
        }
        $up_data    = array(
            'status'    => $status
        );
        return  $this->baseUpdate(['mq_id' => $mq_ids ],$up_data);
    }

}
?>
