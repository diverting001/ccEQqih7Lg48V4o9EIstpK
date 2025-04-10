<?php

namespace App\Api\V1\Service\GoodsVerify;

use App\Api\Logic\GoodsVerify\GoodsVerifyFactory;

/**
 * 商品校验
 */
class GoodsVerify
{
    /**
     * 匹配商品列表
     * @param $params
     * @return array
     */
    public function GetMatchGoodsList($params) :array
    {
        //去重并组装数据
        $uniqueParams = $uniqueParamMap = $return = array();
        foreach ($params as $v) {
            //去重
            $uniqueKey = sprintf('%s-%s', $v['match_type'], $v['match_param']);
            $uniqueParamMap[$uniqueKey][] = $v['match_id'];
            if (!isset($uniqueParams[$uniqueKey])) {
                $uniqueParams[$uniqueKey] = $v;
            }

            //组装返回数据
            $v['supplier_bn'] = '';
            $v['is_exist'] = 0;
            $v['match_data'] = '';
            $return[] = $v;
        }

        $factory = app(GoodsVerifyFactory::class);
        //匹配supplier_bn platform并组织数据
        $requestDatas = array();
        foreach ($uniqueParams as $param) {
            $param['supplier_bn'] = $this->matchSupplierBn($param['match_type'], $param['match_param']);
            if (empty($param['supplier_bn'])) {
                continue;
            }
            $platform = $factory->getSupplierBnPlatform($param['supplier_bn']);
            if (empty($platform)) {
                continue;
            }
            $requestDatas[$platform][] = $param;
        }

        if (empty($requestDatas)) {
            return $return;
        }

        //匹配
        $matchList = array();
        foreach ($requestDatas as $platform => $requestData) {
            try {
                /** @var GoodsVerifyFactory::createObj $obj */
                $obj = $factory->createObj($platform);
            } catch (\Exception $e) {
                continue;
            }

            /** @var \App\Api\Logic\GoodsVerify\Salyut::GetMatchGoodsList $obj */
            $res = $obj->GetMatchGoodsList($requestData);
            if (!$res['status']) {
                continue;
            }
            foreach ($res['data'] as $v) {
                $uniqueKey = sprintf('%s-%s', $v['match_type'], $v['match_param']);
                foreach ($uniqueParamMap[$uniqueKey] as $matchId) {
                    $matchList[$matchId] = $v;
                }
            }
        }

        //组装返回参数
        foreach ($return as $k => $v) {
            if(isset($matchList[$v['match_id']])) {
                $v['supplier_bn'] = $matchList[$v['match_id']]['supplier_bn'];
                $v['is_exist'] = $matchList[$v['match_id']]['is_exist'];
                $v['match_data'] = $matchList[$v['match_id']]['match_data'];
            }
            $return[$k] = $v;
        }

        return $return;
    }

    /**
     * 匹配suplier_bn
     * @param $matchType
     * @param $matchParam
     * @return string
     */
    public function matchSupplierBn($matchType, $matchParam) :string
    {
        $supplierBn = '';
        switch ($matchType) {
            case 'url':
                $supplierBn = $this->matchSupplierBnByUrl($matchParam);
                break;
        }
        return $supplierBn;
    }

    /**
     * 根据域名匹配supplier_bn
     * @param $url
     * @return string
     */
    private function matchSupplierBnByUrl($url) :string{
        if (!preg_match('/https?:\/\/(?:www\.)?(?:[a-z0-9-]+\.)?([a-z0-9-]+\.[a-z]{2,})(?:\/|$)/i', $url, $matches)) {
            return '';
        }
        $domainSupplierBns = [
            'jd.com' => 'JD',
        ];
        return $domainSupplierBns[$matches[1]] ?? '';
    }


}
