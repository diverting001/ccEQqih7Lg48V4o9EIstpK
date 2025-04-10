<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-06-11
 * Time: 18:54
 */

namespace App\Api\V1\Service\Bill;


class Payment
{
    public function getConfigByAppId($app_id)
    {
        $list = \App\Api\Model\Bill\Payment::GetInfoByAppId($app_id);
        if (is_array($list)) {
            $out = [];
            foreach ($list as $key => $val) {
                $out[$val->app_id] = $val;
                $out[$val->app_id]->config_json = json_decode($val->config_json, true);
            }
            return $out;
        } else {
            return [];
        }
    }

    public function getAppListByCode($code)
    {
        $list = \App\Api\Model\Bill\Payment::getAppRelationByCode($code);
        if (is_array($list)) {
            $out = [];
            foreach ($list as $key => $val) {
                $out[$val->code][$key] = $val;
            }
            return $out;
        } else {
            return [];
        }
    }

    public function addAppList($data)
    {
        foreach ($data['data'] as $key => $val) {
            $insert = $val;
            $insert['code'] = $data['code'];
            $rzt = \App\Api\Model\Bill\Payment::addCodeRelation($insert);
            if (!$rzt) {
                return false;
            }
        }
        return true;
    }

}
