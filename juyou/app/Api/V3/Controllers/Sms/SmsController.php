<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V3\Controllers\Sms;

use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Sms\Sms as SmsService;
use Illuminate\Http\Request;

/**
 * 物流 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class SmsController extends BaseController
{
    /**
     * @var Sms
     */
    private $_smsService;

    /**
     * CompanyController constructor.
     */
    public function __construct()
    {
        $this->_smsService = new SmsService();
    }

    /**
     * 短信发送
     *
     * @return array
     */
    public function SendSms(Request $request)
    {
        $params = $this->getContentArray($request);

        // 验证请求参数
        if (empty($params['mobile']) OR empty($params['content']) OR empty($params['type']) OR empty($params['com'])) {
            $this->setErrorMsg('请求参数错误');
            return $this->outputFormat(null, 400);
        }

        $errMsg = '';
        if (!$this->_smsService->send($params['mobile'], $params['content'], $params['com'], $params['type'],
            $errMsg)) {
            $errMsg OR $errMsg = '短信发送失败';
            $this->setErrorMsg($errMsg);
            return $this->outputFormat(null, 401);
        }

        $this->setErrorMsg('短信发送成功');
        return $this->outputFormat(null);
    }

}
