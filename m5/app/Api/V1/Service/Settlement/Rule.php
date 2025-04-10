<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Settlement;

use App\Api\Model\Settlement\Rule as SettlementRuleModel;

/**
 * 账户 Service
 *
 * @package     api
 * @category    Service
 * @author      xupeng
 */
class Rule
{
    /**
     * 创建规则
     *
     * @param   $name           string      名称
     * @param   $memo           string      备注
     * @return  mixed
     */
    public function createRule($name, $ruleType, $ruleItem, $scopeType, $scopeItem, $memo = '')
    {
        $settlementRuleModel = new SettlementRuleModel();

        $ruleInfo = $settlementRuleModel->getRuleInfoByName($name);

        if ( ! empty($ruleInfo)) {
            return $ruleInfo;
        }

        $data = array(
            'name'          => $name,
            'rule_type'     => $ruleType,
            'rule_item'     => $ruleItem,
            'scope_type'    => $scopeType,
            'scope_item'    => $scopeItem,
            'memo'          => $memo,
        );

        $srId = $settlementRuleModel->addRule($data);

        return $settlementRuleModel->getRuleInfo($srId);
    }

    // --------------------------------------------------------------------

    /**
     * 创建规则
     *
     * @param   $page       int     页码
     * @param   $pageSize   int     每页数
     * @param   $filter     array   过滤条件
     * @return  array
     */
    public function getRuleList($page = 1, $pageSize = 20, $filter = array())
    {
        $settlementRuleModel = new SettlementRuleModel();

        $count = $settlementRuleModel->getRuleCount($filter);

        $totalPage = ceil($count / $pageSize);

        $return = array(
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_count'   => $count,
            'total_page'    => $totalPage,
            'data'          => array(),
        );

        if ($count == 0)
        {
            return $return;
        }

        $offset = ($page - 1) * $pageSize;

        $ruleList = $settlementRuleModel->getRuleList($pageSize, $offset, $filter);

        $return['data'] = $ruleList;

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取规则信息
     *
     * @param   $srId       int     结算渠道ID
     * @return  array
     */
    public function getRuleInfo($srId)
    {
        $settlementRuleModel = new SettlementRuleModel();

        $return = $settlementRuleModel->getRuleInfo($srId);

        return $return;
    }

}
