<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/9/22
 * Time: 14:27
 */

namespace app\Api\Model\Goods;


use Neigou\RedisNeigou;

class Promotion
{
    private $_version = 'v3';

    /**
     * 获取规则详细信息
     * @param int $pid
     */
    public function getRuleInfo($pid = 0)
    {
        $time = time();
        $where = [
            ['start_time', '<', $time],
            ['end_time', '>', $time],
            ['id', intval($pid)],
            ['status', 'enable']
        ];
        $info = app('api_db')->table('server_promotion')->where($where)->first();
        return $info;
    }


    /*这里是V2新版本*/

    /**
     * 获取商品参加的活动 【END】
     * @param array $good
     * @return array
     */
    public function getRule($good = array())
    {
        $cache_key = 'service:promotion:' . $this->_version . ':' . $good['bn'];
        $redis = new RedisNeigou();
        $rzt = $redis->_redis_connection->get($cache_key);
        if ($rzt === false) {
            $bn_rules = $this->getRuleByBn($good['bn']);
            $brand_rules = $this->getRuleByBrand($good['brand_id']);
            $shop_rules = $this->getRuleByShopId($good['shop_id']);
            $cat_rules = $this->getRuleByCatId($good['cat_path']);
            $rules = array_merge($bn_rules, $brand_rules, $shop_rules, $cat_rules);
            //按照sort进行排序活动
            foreach ($rules as $key => $rule) {
                $sort[$key] = $rule->sort;
            }
            array_multisort($sort, SORT_DESC, $rules);
            foreach ($rules as $key => $rule) {
                $rzt[$rule->id] = $rule;
            }
            unset($sort);
            $rzt = json_encode($rzt);
            $redis->_redis_connection->set($cache_key, $rzt, 600);
        }
        return json_decode($rzt, true);
    }

    /**
     * 商品Bn参加的活动
     * TODO promotion表中的作用域没啥用了
     * @param string $bn
     * @return array
     */
    public function getRuleByBn($bn = '')
    {
        if (empty($bn)) {
            return array();
        }
        $sql = "SELECT * FROM server_promotion_item WHERE affect = 'sku' AND item_id = ?";
        $c_list = app('api_db')->select($sql,array($bn));
        $all = $this->getRuleAllType('all_goods');
        foreach ($c_list as $key => $val) {
            $info = $this->getRuleInfo($val->pid);
            if ($info->id > 0) {
                $all[$val->pid] = $info;
            }

        }
        return $all;
    }

    /**
     * 获取所有商品 BN BRNAD SHOP 参加的活动
     * @return array
     */
    private function getRuleAllType($type = '')
    {
        $time = time();
        $sql = "SELECT * FROM server_promotion WHERE " . $type . " = true AND start_time<{$time} AND end_time>{$time}";
        $list = app('api_db')->select($sql);
        $rzt = array();
        foreach ($list as $key => $val) {
            $rzt[$val->id] = $val;
        }
        return $rzt;
    }

    /**
     * 商品品牌参加的活动
     * @param int $brand_id
     * @return array
     */
    public function getRuleByBrand($brand_id = 0)
    {
        if ($brand_id == 0) {
            return array();
        }
        $sql = "SELECT * FROM server_promotion_item WHERE affect = 'brand' AND item_id = ?";
        $c_list = app('api_db')->select($sql,array($brand_id));
        $all = $this->getRuleAllType('all_brand');
        foreach ($c_list as $key => $val) {
            $info = $this->getRuleInfo($val->pid);
            if ($info->id > 0) {
                $all[$val->pid] = $info;
            }
        }
        return $all;
    }

    /**
     * 店铺参加的活动
     * @param int $shop_id
     * @return array
     */
    public function getRuleByShopId($shop_id = 0)
    {
        if ($shop_id == 0) {
            return array();
        }
        $sql = "SELECT * FROM server_promotion_item WHERE affect = 'shop' AND item_id = ?";
        $c_list = app('api_db')->select($sql,array($shop_id));
        $all = $this->getRuleAllType('all_shop');
        foreach ($c_list as $key => $val) {
            $info = $this->getRuleInfo($val->pid);
            if ($info->id > 0) {
                $all[$val->pid] = $info;
            }
        }
        return $all;
    }

    /**
     * 分类参加的活动
     * @param string $cat_id
     * @return array
     */
    public function getRuleByCatId($cat_id = '')
    {
        if (empty($cat_id)) {
            return array();
        }

        $itemIdList = explode(',',$cat_id);
        $c_list = app('api_db')->table('server_promotion_item')->select()->where(array('affect' => 'cat'))->whereIn('item_id',$itemIdList)->get()->toArray();
        //$sql = "SELECT * FROM server_promotion_item WHERE affect = 'cat' AND item_id in(" . $cat_id . ")";
        //$c_list = app('api_db')->select($sql);
        $all = array();
        foreach ($c_list as $key => $val) {
            $info = $this->getRuleInfo($val->pid);
            if ($info->id > 0) {
                $all[$val->pid] = $info;
            }
        }
        return $all;
    }

    /**
     * 锁定库存
     * TODO Redis 进行计算
     * @param array $data_param
     * @return bool
     */
    public function LockStock($data_param = array())
    {
        $data['bn'] = $data_param['bn'];
        $data['nums'] = $data_param['nums'];
        $data['member_id'] = $data_param['member_id'];
        $data['order_id'] = $data_param['order_id'];
        $data['create_time'] = time();
        //获取Bn对应的规则是否有限购措施
        $rules = $this->getRuleByBn($data['bn']);
        if (!empty($rules)) {
            //获取一个限购策略
            foreach ($rules as $key => $rule_tmp) {
                if ($rule_tmp->affect_type == 'limit_buy' && $this->CheckCompany($rule_tmp->id, $data_param['company_id'])) {
                    $rule = $rules[$key];
                }
            }
            if (!empty($rule)) {
                app('api_db')->beginTransaction();
                //查询现在已经售出的量和限购策略对比
                $sold = $this->GetSoldByBn($data['bn'], $rule->id);
                $data['rule_id'] = $rule->id;
                //查询当前用户购买的的数量 和限购最对比
                $has_buy = $this->GetSoldByUid($data['bn'], $rule->id, $data['member_id']);
                if ($sold + $data['nums'] <= $rule->max_sale) {
                    if ($has_buy + $data['nums'] <= $rule->max_buy) {
                        $log_data['sold'] = $sold;
                        $log_data['has_buy'] = $has_buy;
                        $log_data['nums'] = $data['nums'];
                        $log_data['rule'] = $rule;
                        $log_data['insert_data'] = $data;
                        $log_data['remark'] = 'succ';
                        $log_data['bn'] = $data['bn'];


                        \Neigou\Logger::General('server.promotion.lockStock', $log_data);
                        $res = app('api_db')->table('server_promotion_stock')->insert($data);
                    } else {
                        $log_data['sold'] = $sold;
                        $log_data['has_buy'] = $has_buy;
                        $log_data['nums'] = $data['nums'];
                        $log_data['rule'] = $rule;
                        $log_data['insert_data'] = $data;
                        $log_data['remark'] = 'fail over limit by';
                        $log_data['reason'] = 'limit user buy';
                        $log_data['bn'] = $data['bn'];


                        \Neigou\Logger::General('server.promotion.lockStock', $log_data);
                        $res = false;
                    }
                } else {
                    $log_data['sold'] = $sold;
                    $log_data['has_buy'] = $has_buy;
                    $log_data['nums'] = $data['nums'];
                    $log_data['rule'] = $rule;
                    $log_data['insert_data'] = $data;
                    $log_data['remark'] = 'fail out of stock';
                    $log_data['reason'] = 'out of stock';
                    $log_data['bn'] = $data['bn'];


                    \Neigou\Logger::General('server.promotion.lockStock', $log_data);
                    $res = false;
                }

                if ($res) {
                    app('api_db')->commit();
                    return true;
                } else {
                    app('api_db')->rollback();
                    return false;
                }
            }
            return true;

        }
        return true;
    }

    /**
     * 解锁库存
     * @param $order_id
     * @return $this
     */
    public function UnLockStock($order_id = 0)
    {
        $res = app('api_db')->table('server_promotion_stock')->where('order_id', $order_id)->delete();
        return ($res > 0) ? true : false;
    }

    /**
     * 根据Bn获取已经卖出量
     * @param string $bn
     * @param int $rid
     * @return int
     */
    public function GetSoldByBn($bn = '', $rid = 0)
    {
        $sum = app('api_db')->table('server_promotion_stock')->where([['bn', $bn], ['rule_id', $rid]])->sum('nums');
        if ($sum > 0) {
            return $sum;
        } else {
            return 0;
        }
    }

    /**
     * 获取当前用户购买的量
     * @param string $bn
     * @param int $rid
     * @param int $member_id
     * @return int
     */
    public function GetSoldByUid($bn = '', $rid = 0, $member_id = 0)
    {
        $sum = app('api_db')->table('server_promotion_stock')->where([
            ['bn', $bn],
            ['rule_id', $rid],
            ['member_id', $member_id]
        ])->sum('nums');
        if ($sum > 0) {
            return $sum;
        } else {
            return 0;
        }
    }

    public function CheckCompany($rule_id = 0, $company_id = 0)
    {
        //检测当前活动是全部公司还是 特定公司参与 或者 特定公司不参与
        if ($rule_id == 0 || $company_id == 0) {
            return false;
        }
        //获取规则信息
        $info = $this->getRuleInfo($rule_id);
        switch ($info->company_limit) {
            case 'all':
                return true;
            case 'in':
                //查询是否在表中
                $count = app('api_db')->table('server_promotion_company')->where([
                    ['pid', $rule_id],
                    ['company_id', $company_id]
                ])->count();
                if ($count > 0) {
                    return true;
                } else {
                    return false;
                }
            case 'not_in':
                //查询是否在表中
                $count = app('api_db')->table('server_promotion_company')->where([
                    ['pid', $rule_id],
                    ['company_id', $company_id]
                ])->count();
                if ($count > 0) {
                    return false;
                } else {
                    return true;
                }
        }
    }


}
