<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 15:44
 */

namespace App\Api\V1\Controllers;

use App\Api\Common\Controllers\BaseController;

use Illuminate\Http\Request;

use App\Api\V1\Service\NeigouRule\NeigouRule as NeigouRuleServer;

/**
 * 限制规则服务
 * Class RuleController
 * @package App\Api\V1\Controllers
 */
class NeigouRuleController extends BaseController
{
    /**
     * 内购规则验证
     * @param Request $request
     * @return array
     */
    public function withRule(Request $request)
    {
        $requestData = $this->getContentArray($request);

        if (
            empty($requestData['rule_list']) ||
            empty($requestData['filter_data'])
        ) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat([], 400);
        }

        $ruleServer = new NeigouRuleServer();

        $res = $ruleServer->ruleWithRule($requestData);

        $this->setErrorMsg($res['msg']);

        if (!$res['status']) {
            return $this->outputFormat($res['data'], 400);
        }

        return $this->outputFormat($res['data']);

    }
}
