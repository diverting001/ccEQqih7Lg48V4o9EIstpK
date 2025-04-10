<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-02-25
 * Time: 11:35
 */

namespace App\Api\Model\Promotion;


use Illuminate\Support\Facades\DB;
use App\Api\Model\Promotion\PromotionWhiteListModel;

class PromotionModel
{

    protected $_db;
    private $_table_action_scope = 'server_promotion_v2_action_scope';
    private $_table_promotion = 'server_promotion_v2';
    private $_table_rel = 'server_promotion_v2_rule_rel';
    private $_table_white_list = 'server_promotion_v2_member_white_list';

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    //获取所有公司 所有channel 所有人都可使用都规则ID
    public function getNormalMatchRuleId($company_id,$member_id,$channel){
        $where = "(scope = 'company' and scope_value = ?) 
        or (scope = 'member' and scope_value = ?) 
        or (scope = 'channel' and scope_value = ?)
        or (scope_value = 'all')";
//        $this->_db->enableQueryLog();
        $list = $this->_db->table($this->_table_action_scope.' as scope')
            ->whereRaw($where,[$company_id,$member_id,$channel])
            ->leftJoin($this->_table_rel.' as rel', 'scope.pid', '=', 'rel.pid')
            ->select('scope.pid','rel.rule_id')->get()->all();
//        print_r($this->_db->getQueryLog());die;
        $list = $this->filterPromotionWhiteList($list, $company_id,$member_id,$channel);
        if(is_array($list)){
            return $list;
        } else {
            return [];
        }
    }

    public function queryRuleByPromotionId($promotion_id){

        $list = $this->_db->table($this->_table_promotion);
        if(is_array($promotion_id)){
            $list = $list->whereIn('id',$promotion_id);
        } else {
            $list = $list->where('id','=',$promotion_id);
        }
        $map = [
            ['status','=',1],
            ['start_time','<',time()],
            ['end_time','>',time()],
        ];
        $list = $list->where($map)->orderBy('sort','desc')->orderBy('id','desc')->get()->all();
        if(is_array($list)){
            return $list;
        } else {
            return [];
        }
    }

    public function LockPromotionStock($data){
        try {
            $ret = app('api_db')->table('server_promotion_stock')->insert($data);
        } catch (\Exception $e) {
            $ret = false;
        }
        return $ret;
    }

    public function UnLockPromotionStock($order_id){
        try {
            $ret = app('api_db')->table('server_promotion_stock')->where('order_id','=',$order_id)->delete();
        } catch (\Exception $e) {
            $ret = false;
        }
        return $ret;
    }

    /**
     * @param $orderId
     * @param $bn
     * @return array
     */
    public static function queryPromotionStockByOrderIdAndBn($orderId, $bn): array
    {
        $bn = is_array($bn) ? $bn : array($bn);

        $list = app('api_db')->table('server_promotion_stock')->where('order_id', $orderId)->whereIn('bn', $bn)->get()->all();

        return $list ?: [];
    }

    /**
     * @param $orderId
     * @param $bn
     * @param $num
     * @return mixed
     */
    public static function decPromotionStockByOrderIdAndBn($orderId, $bn, $num)
    {
        return app('api_db')->table('server_promotion_stock')->where('order_id', $orderId)->where('bn',
            $bn)->decrement('nums', $num);
    }

    public static function decPromotionByOrderIdAndBn($orderId, $bn, $num, $amount)
    {
        return app('api_db')->table('server_promotion_stock')->where('order_id', $orderId)->where('bn',
            $bn)->update([
            'nums' => DB::raw('nums - '. $num),
            'amount' => DB::raw('amount - '.$amount)
        ]);
    }

    /**
     * @param $afterSaleBn
     * @param $orderId
     * @param $bn
     * @param $num
     * @return mixed
     */
    public static function recordPromotionStock($afterSaleBn, $orderId, $bn, $num)
    {
        $data = [
            'source_id'=>$afterSaleBn,
            'order_id'=>$orderId,
            'bn'=>$bn,
            'nums'=>$num,
        ];
        return app('api_db')->table('server_promotion_return_stock_record')->insertGetId($data);
    }

    public static function recordPromotion($afterSaleBn, $orderId, $bn, $num, $amount)
    {
        $data = [
            'source_id'=>$afterSaleBn,
            'order_id'=>$orderId,
            'bn'=>$bn,
            'nums'=>$num,
            'amount' => $amount,
            'create_time' => time()
        ];
        return app('api_db')->table('server_promotion_return_stock_record')->insertGetId($data);
    }
    /**
     * 过滤促销活动白名单
     * @param $promotionRuleList
     * @param $companyId
     * @param $memberId
     * @param $channel
     *
     * @return array
     */
    private function filterPromotionWhiteList($promotionRuleList ,$companyId, $memberId, $channel)
    {
        $promotionWhiteListModel = new PromotionWhiteListModel();
        $condition = [];
        $condition['company_id'] = $companyId;
        $condition['member_id'] = $memberId;
        $promotionWhiteList = $promotionWhiteListModel->getList($condition, [], ['pid','member_id', 'company_id']);
        $promotionWhitePids = array_column($promotionWhiteList, 'pid');

        $list = [];
        foreach ($promotionRuleList as $item) {
            if (!in_array($item->pid, $promotionWhitePids)) {
                $list[] = $item;
            }
        }

        return $list;
    }

}
