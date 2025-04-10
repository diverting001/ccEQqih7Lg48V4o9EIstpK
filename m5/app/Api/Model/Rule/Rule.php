<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 16:01
 */

namespace App\Api\Model\Rule;


class Rule
{
    /**
     * @param $channel
     * @param $filter
     * @return int
     */
    public function getRuleCount($channel, $filter)
    {
        $dbBuilder = app('api_db')->table('server_rules')->where('channel', $channel);
        if ($filter) {
            $dbBuilder = $this->explainFilter($dbBuilder, $filter);
        }
        $count = $dbBuilder->count();
        return $count;
    }

    public function getRuleList($channel, $filter, $page = 1, $pageSize = 20)
    {
        $dbBuilder = app('api_db')->table('server_rules')->where('channel', $channel);
        if ($filter) {
            $dbBuilder = $this->explainFilter($dbBuilder, $filter);
        }
        $list = $dbBuilder
            ->select('rule_bn', 'name', 'desc', 'channel', 'external_bn as channel_rule_bn')
            ->orderBy('id', 'desc')
            ->forPage($page, $pageSize)
            ->get();
        return $list;
    }

    public function queryRuleList($filter, $page = 1, $pageSize = 100)
    {
        $dbBuilder = app('api_db')->table('server_rules');
        if ($filter) {
            $dbBuilder = $this->explainFilter($dbBuilder, $filter);
        }
        $list = $dbBuilder
            ->select('rule_bn', 'name', 'desc', 'channel', 'external_bn as channel_rule_bn')
            ->orderBy('id', 'desc')
            ->forPage($page, $pageSize)
            ->get();
        return $list;
    }

    public function getRuleInfo($filter)
    {
        $info = app('api_db')->table('server_rules')->where($filter)->first();
        return $info;
    }

    /**
     * @param $dbBuilder
     * @param $filter
     * @return mixed
     */
    private function explainFilter($dbBuilder, $filter)
    {
        foreach ($filter as $key => $item) {
            switch ($key) {
                case 'searchName':
                    $dbBuilder = $dbBuilder->where('name', 'like', '%' . $item . '%');
                    break;
                case 'rule_bns' :
                    foreach ($item as &$v) {
                        $v = strval($v);
                    }
                    $dbBuilder = $dbBuilder->whereIn('rule_bn', $item);
                    break;
                case 'channel_rule_ids':
                    foreach ($item as &$v) {
                        $v = strval($v);
                    }
                    $dbBuilder = $dbBuilder->whereIn('external_bn', $item);
                    break;
                case 'channel':
                    $dbBuilder = $dbBuilder->where('channel', '=', $item);
                    break;
            }
        }
        return $dbBuilder;
    }

    public function updateRule($ruleId, $data)
    {
        try {
            $data['update_time'] = time();

            $status = app('api_db')->table('server_rules')->where(['id' => $ruleId])->update($data);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public function createRule($data)
    {
        try {
            $status = app('api_db')->table('server_rules')->insert($data);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

}
