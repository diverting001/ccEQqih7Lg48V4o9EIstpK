<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:08
 */

namespace App\Api\V2\Controllers\Delivery;

use App\Api\Common\Controllers\BaseController;
use App\Api\V2\Service\Delivery\Rule;
use Illuminate\Http\Request;

/**
 * 快递模板规则
 * Class RuleController
 * @package App\Api\V2\Controllers\Delivery
 */
class RuleController extends BaseController
{
    public function BatchCreate(Request $request)
    {
        $params = $this->getContentArray($request);

        $templageBn = $params['template_bn'] ?? '';
        $ruleList   = $params['rule_list'] ?? '';

        if (!$ruleList) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(array(), 400);
        }


        $ruleService = new Rule();

        $res = $ruleService->BatchCreate($templageBn, $ruleList);

        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function BatchDelete(Request $request)
    {
        $params = $this->getContentArray($request);

        $ruleIds   = $params['rule_ids'] ?? '';

        if (!$ruleIds) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(array(), 400);
        }


        $ruleService = new Rule();

        $res = $ruleService->BatchDelete($ruleIds);

        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function BatchQueryFreight(Request $request)
    {
        $params = $this->getContentArray($request);

        $deliveryList = $params['delivery_list'] ?? '';
        if (!$deliveryList) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(array(), 400);
        }

        $ruleService = new Rule();

        $res = $ruleService->BatchQueryFreight($deliveryList);

        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }
}
