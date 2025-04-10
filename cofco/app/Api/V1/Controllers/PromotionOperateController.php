<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/31
 * Time: 11:21
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Promotion\Operate;
use Illuminate\Http\Request;

class PromotionOperateController extends BaseController
{
    /**
     * 获取传递的rule_id 用于特定查询
     * @return int
     */
    private function _get_rule_id(Request $request)
    {
        $data = $this->getContentArray($request);
        $rule_id = intval($data['rule_id']);
        if ($rule_id <= 0) {
            $this->setErrorMsg('rule_id require');
            return false;
        } else {
            return $rule_id;
        }

    }

    /**
     * 【使用】促销规则列表
     * eg:{"offset":2,"limit":10}
     * @return array
     */
    public function ruleList(Request $request)
    {
        $limit = $this->getContentArray($request);
        $promotion = new Operate();
        if (intval($limit['offset']) <= 0) {
            $limit['offset'] = 0;
        }
        if (intval($limit['limit']) <= 0) {
            $limit['limit'] = 0;
        }
        $list = $promotion->ruleList($limit);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($list);
    }

    /**
     * 【使用】获取一条规则的详细信息
     * eg:{"rule_id":27}
     * @return array
     */
    public function ruleInfo(Request $request)
    {
        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $operate = new Operate();
        $info = $operate->findOne($rule_id);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($info);
    }

    /**
     * 【使用】促销规则里的货品等信息
     * eg:{"rule_id":27,"type":"sku","limit":{"offset":4,"limit":4}}
     * @return array
     */
    public function ruleItems(Request $request)
    {
        $req = $this->getContentArray($request);

        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $promotion = new Operate();
        $type = $req['type'];
        if (empty($type)) {
            $this->setErrorMsg('param type require');
            return $this->outputFormat(null, 404);
        }
        $limit = $req['limit'];
        $data = $promotion->specialList('edit_item', $rule_id, $type, $limit);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($data);
    }

    /**
     * 【使用】检测Item是否存在
     * route Check/item
     * @return array
     */
    public function checkRuleItem(Request $request)
    {
        $req = $this->getContentArray($request);

        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $promotion = new Operate();
        $affect = $req['type'];
        if (empty($affect)) {
            $this->setErrorMsg('param type require');
            return $this->outputFormat(null, 404);
        }
        $item_id = $req['item_id'];
        $rzt = $promotion->checkItems($item_id, $rule_id, $affect);
        if ($rzt) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('不存在');
            return $this->outputFormat($rzt, 404);
        }
    }

    /**
     * 【使用】促销规则可用的公司
     * eg:{"rule_id":27}
     * @return array
     */
    public function ruleCompany(Request $request)
    {
        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $promotion = new Operate();
        $data = $promotion->findOne($rule_id);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat(json_decode($data->companys, true));
    }

    /**
     * 【使用】修改规则信息
     * route Set/rule
     * @return array
     */
    public function updatePromotion(Request $request)
    {
        $set = $this->getContentArray($request);
        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $operate = new Operate();
        $set = $set['set'];
        if (empty($set)) {
            $this->setErrorMsg('param set require');
            return $this->outputFormat(null, 404);
        }
        foreach ($set as $key => $value) {
            if (empty($value)) {
                unset($set[$key]);
            }
        }

        $rzt = $operate->execPromotion($rule_id, $set);
        if ($rzt) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($rzt);
        } else {
            $this->setErrorMsg('修改失败');
            return $this->outputFormat($rzt, 404);
        }
    }

    /**
     * 【使用】修改Item信息
     * route Set/item
     * @return array
     */
    public function execItems(Request $request)
    {
        $set = $this->getContentArray($request);
        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $operate = new Operate();
        $operate->execPromotion($rule_id, array());
        foreach ($set as $key => $item) {
            if (count($item['add']) > 0) {
                //执行增量插入
                foreach ($item['add'] as $add_key => $add_id) {
                    $data['pid'] = $rule_id;
                    $data['affect'] = $key;
                    $data['item_id'] = $add_id;
                    $out['add'][$key][] = $operate->insertItem($data);
                }
            }
            if (count($item['del']) > 0) {
                //执行删除操作
                foreach ($item['del'] as $del_key => $del_id) {
                    $data['pid'] = $rule_id;
                    $data['item_id'] = $del_id;
                    $data['affect'] = $key;
                    $out['del'][$key][] = $operate->delItem($data);
                }
            }
        }
        //执行 编辑未推送状态
        $save['push_status'] = 'offline';
        $operate->execPromotion($rule_id, $save);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($out);
    }

    /**
     * 【使用】创建规则信息
     * route Create/rule
     * @return array
     */
    public function createPromotion(Request $request)
    {
        $set = $this->getContentArray($request);
        $operate = new Operate();
        $set = $set['set'];
        foreach ($set as $key => $value) {
            if (empty($value)) {
                unset($set[$key]);
            }
        }
        if (count($set) <= 0) {
            $this->setErrorMsg('need prarams');
            return $this->outputFormat(null, 404);
        }
        $rzt = $operate->execPromotion(0, $set);
        if ($rzt > 0) {
            $this->setErrorMsg('请求成功');
            $out['rule_id'] = $rzt;
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg('创建失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 【使用】获取type描述
     * @return array
     */
    public function fieldDesc()
    {
        $array['affect_type'] = array(
            'price' => '满减',
            'discount' => '打折',
            'free_shipping' => '免邮',
            'present' => '赠品',
            'limit_buy' => '限时限购'
        );
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($array);
    }

    /**
     * sync到线上版本
     * @return array
     */
    public function pushRule(Request $request)
    {
        $rule_id = $this->_get_rule_id($request);
        if (!$rule_id) {
            return $this->outputFormat(null, 404);
        }
        $operate = new Operate();
        $rzt = $operate->pushRule($rule_id);
        $this->setErrorMsg('请求成功');
        return $this->outputFormat($rzt);
    }


}
