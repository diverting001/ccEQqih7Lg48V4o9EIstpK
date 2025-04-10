<?php

namespace App\Api\V2\Service\Promotion;
use App\Api\V3\Service\ServiceTrait;
use App\Api\Model\Promotion\PromotionWhiteListModel;


class PromotionWhiteListService
{
    use ServiceTrait;

    /**
     * 创建白名单
     * @param $ruleId
     * @param $whiteMemberDataList
     *
     * @return array
     */
    public function createPromotionWhiteList($ruleId, $whiteMemberList) :array
    {
        $whiteMemberDataList = array();
        if (!empty($whiteMemberList)) {
            foreach ($whiteMemberList as $item) {
                if (!isset($item['member_id']) || !isset($item['company_id'])) {
                    return $this->Response(false, "company_id或member_id不存在");
                }
                $whiteMemberData = array();
                $whiteMemberData['pid'] = $ruleId;
                $whiteMemberData['create_time'] = time();
                $whiteMemberData['member_id'] = $item['member_id'];
                $whiteMemberData['company_id'] = $item['company_id'];
                $whiteMemberDataList[] = $whiteMemberData;
            }
            unset($item);
        }

        $promotionWhiteListModel = new PromotionWhiteListModel();
        app('db')->beginTransaction();
        $delStatus = $promotionWhiteListModel->deleteByPid($ruleId);
        if ($delStatus=== false) {
            app('db')->rollBack();
            return $this->Response(false, "删除失败");
        }

        if (!empty($whiteMemberDataList)) {
            $status = $promotionWhiteListModel->create($whiteMemberDataList);
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "新增失败");
            }
        }

        app('db')->commit();

        return $this->Response(true, "创建成功", []);
    }

    /**
     * 删除白名单
     * @param $ruleId
     * @param $whiteMemberDataList
     */
    public function deletePromotionWhiteList($ruleId, $whiteIdList)
    {
        $promotionWhiteListModel = new PromotionWhiteListModel();
        foreach ($whiteIdList as $id) {
            $promotionWhiteListModel->delete($id,$ruleId);
        }

        return $this->Response(true, "创建成功", []);
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function getList($params) :array
    {
        if (isset($params['filter'])) {
            if (isset($params['filter']['rule_id']) && !empty($params['filter']['rule_id'])) {
                $condition['pid'] = $params['filter']['rule_id'];
            }
        }

        if (!isset($params['page'])) {
            $page = 1;
        } else {
            $page = (int)$params['page'];
        }


        if (!isset($params['page_size'])) {
            $pageSize = 20;
        } else {
            $pageSize = (int)$params['page_size'];
        }

        $offset           = ($page - 1) * $pageSize;
        $option['offset'] = $offset;
        $option['limit']  = $pageSize;

        $promotionWhiteListModel = new PromotionWhiteListModel();

        $count = $promotionWhiteListModel->getCount($condition);
        if ($count == 0) {
            return $this->Response(true, "成功",
                ['list' => [], 'page' => 0, 'page_size' => 0, 'total_count' => 0, 'total_page' => 0]);
        }

        $promotionWhiteMemberList = $promotionWhiteListModel->getList($condition, $option, ['id', 'pid','member_id','company_id']);

        return $this->Response(true, "成功", [
            'list'        => $promotionWhiteMemberList,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total_count' => $count,
            'total_page'  => ceil($count / $pageSize)
        ]);
    }

}
