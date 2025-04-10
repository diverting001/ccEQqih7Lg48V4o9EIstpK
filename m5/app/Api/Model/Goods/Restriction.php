<?php
namespace App\Api\Model\Goods;
use \App\Api\Model\BaseModel ;

class Restriction extends BaseModel {

    protected $table = 'club_company_order_restriction' ;
    protected $primaryKey = 'id' ;
    protected $connection = 'neigou_club' ;

    //检查公司下单金额限制
    function checkCompanyOrderAmount($company_id,$amount,&$msg) {
        $ret_val = TRUE;
        $results = $this->select("select * from club_company_order_restriction where company_id = {$company_id} order by id asc");

        if(empty($results)){
            return $ret_val ;
        }
        //是否满足最低限额
        $time = time();
        $min_limit_amount = 0;
        $max_weight = 0;
        foreach($results as $row){
            $begin_time = strtotime($row['begin_time']);
            $end_time = strtotime($row['end_time']);
            if($begin_time < $time && $time <= $end_time){
                if($row['weight'] > $max_weight){
                    $max_weight = $row['weight'];
                    $min_limit_amount = $row['limit_amount'];
                    $msg = ($row['error_msg'] ?: '小于规定的下单额度') . '【' . $row['id'] . '】';
                }elseif($row['weight'] == $max_weight){
                    if($row['limit_amount'] < $min_limit_amount){
                        $min_limit_amount = $row['limit_amount'];
                        $msg = ($row['error_msg'] ?: '小于规定的下单额度') . '【' . $row['id'] . '】';
                    }
                }
            }
        }
        return  $min_limit_amount <= $amount ? TRUE : FALSE;
    }




}
