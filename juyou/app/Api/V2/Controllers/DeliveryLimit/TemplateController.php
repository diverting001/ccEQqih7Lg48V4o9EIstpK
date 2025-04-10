<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:08
 */

namespace App\Api\V2\Controllers\DeliveryLimit;

use App\Api\Common\Controllers\BaseController;
use App\Api\V2\Service\DeliveryLimit\Template;
use Illuminate\Http\Request;

/**
 * 快递模板
 * Class TemplateController
 * @package App\Api\V2\Controllers\Delivery
 */
class TemplateController extends BaseController
{
    public function Create(Request $request)
    {
        $params = $this->getContentArray($request);

        $tempName    = $params['template_name'] ?? '';
        $tempDesc    = $params['template_desc'] ?? '';
        $tempAdapter = $params['adapter_type'] ?? 'NEIGOU';

        $tempService = new Template();

        $res = $tempService->Create([
            'template_name' => $tempName,
            'template_desc' => $tempDesc,
            'adapter_type'  => $tempAdapter,
        ]);

        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }
    }

    public function Update(Request $request)
    {
        $params = $this->getContentArray($request);

        $tempBn      = $params['template_bn'] ?? '';
        $tempName    = $params['template_name'] ?? '';
        $tempDesc    = $params['template_desc'] ?? '';
        $tempAdapter = $params['adapter_type'] ?? 'NEIGOU';

        if (!$tempBn) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat(array(), 400);
        }

        $tempService = new Template();

        $res = $tempService->Update($tempBn, [
            'template_name' => $tempName,
            'template_desc' => $tempDesc,
            'adapter_type'  => $tempAdapter,
        ]);

        if ($res['status']) {
            return $this->outputFormat($res['data'], 0);
        } else {
            $this->setErrorMsg($res['msg']);
            return $this->outputFormat(array(), 400);
        }

    }
}
