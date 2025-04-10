<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Controllers\WelfareCard;


use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\WelfareCard\WelfareCard;
use Illuminate\Http\Request;


class WelfareCardController extends BaseController
{
    /**
     * @var WelfareCard
     */
    protected $welfareCardService;

    /**
     * WelfareCardController constructor.
     */
    public function __construct()
    {
        $this->welfareCardService = new WelfareCard();
    }

    /**
     * 查询卡密
     *
     * @return array
     */
    public function getWelfareCardByPassword(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['password'])) {
            $this->setErrorMsg('卡密不能为空');
            return $this->outputFormat($param, 10001);
        }
        $this->setErrorMsg('success');
        return $this->outputFormat($this->welfareCardService->getByPassword($param['password']));
    }

    /**
     * 查询使用记录
     *
     * @return array
     */
    public function getUseRecord(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['password'])) {
            $this->setErrorMsg('卡密不能为空');
            return $this->outputFormat($param, 10001);
        }

        $card = $this->welfareCardService->getByPassword($param['password']);
        if (empty($card)) {
            $this->setErrorMsg('卡密不存在');
            return $this->outputFormat($param, 10001);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat($this->welfareCardService->getUseRecord($card['card_id']));
    }

    /**
     * 锁定福利卡
     *
     * @return array
     */
    public function lockCard(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['password'])) {
            $this->setErrorMsg('卡密不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->welfareCardService->lockCardByPassword($param['password']);
        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['data'], $response['code']);
    }

    /**
     * @return array
     */
    public function useCard(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['password'])) {
            $this->setErrorMsg('卡密不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['member_id'])) {
            $this->setErrorMsg('用户ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $response = $this->welfareCardService->useCard(
            $param['password'],
            $param['company_id'],
            $param['member_id'],
            $param['memo']
        );

        $this->setErrorMsg($response['message']);
        return $this->outputFormat($response['data'], $response['code']);
    }
}
