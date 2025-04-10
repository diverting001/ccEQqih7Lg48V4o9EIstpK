<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 18:40
 */

namespace App\Api\Logic\Delivery\Formula;


use App\Api\Logic\Delivery\FormulaTarget;

use App\Api\Model\Delivery\RuleFormula as RuleFormulaModel;
use App\Api\V1\Service\Delivery\Delivery as DeliveryServer;

class NeigouAdaptee extends FormulaTarget
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }


    public function createRuleFormula($ruleId, $formula)
    {
        $formulaId = RuleFormulaModel::Create([
            'rule_id' => $ruleId,
            'type'    => $this->type,
            'value'   => ''
        ]);
        if (!$formulaId) {
            return $this->Response(false, '运费创建失败【1】');
        }
        return $this->Response();
    }

    public function deleteRuleFormula($formulaId)
    {
        $status = RuleFormulaModel::Delete($formulaId);
        if (!$status) {
            return $this->Response(false, '运费删除失败【1】');
        }
        return $this->Response();
    }

    public function runFormula($formulaId, $elementData)
    {
        $regionInfo = $elementData['region_info'];

        $storeRes = $this->getStoreRegionId([
            'province' => $regionInfo['province'],
            'city'     => $regionInfo['city'],
            'county'   => $regionInfo['county'],
            'town'     => $regionInfo['town']
        ]);

        $areaId = array_last(array_filter($storeRes));

        $formulaInfo = RuleFormulaModel::Find($formulaId);

        $data = json_decode($formulaInfo->value, true);
        if (!is_array($data)) {
            return $this->Response(false, '运费规则错误');
        }

        $data['weight']                   = $elementData['weight'];
        $data['subtotal']                 = $elementData['subtotal'];
        $data['shipping_area']['area_id'] = $areaId ? $areaId : 1;

        $deliveryServer = new DeliveryServer();

        $freight = $deliveryServer->getNeigouFreightV2($data);

        \Neigou\Logger::Debug('NeigouAdaptee.runFormula',
            [
                'action'   => 'getfreight.error',
                "request"  => $data,
                "response" => $freight
            ]
        );

        return $this->Response(true, '成功', [
            'freight' => number_format($freight, 2, ".", "")
        ]);
    }

    private function getStoreRegionId($data)
    {
        $data['time'] = time();

        $sendData = [
            'data' => base64_encode(json_encode($data))
        ];

        $sendData['token'] = \App\Api\Common\Common::GetEcStoreSign($sendData);

        $curl      = new \Neigou\Curl();
        $resultStr = $curl->Post(config('neigou.STORE_DOMIN') . '/openapi/region/getRegionIdByFourLevel', $sendData);
        $result    = trim($resultStr, "\xEF\xBB\xBF");
        $result    = json_decode($result, true);
        return $result['Data'];
    }
}
