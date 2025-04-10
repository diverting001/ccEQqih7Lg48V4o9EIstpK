<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Service\WelfareCard;

use App\Api\Model\WelfareCard\WelfareCard as WelfareCardModel;
use App\Api\Model\WelfareCard\WelfareCardUseRecord as WelfareCardUseRecordModel;
use ThriftCenter\ThriftCenterClientAdapters;

class WelfareCard
{
    protected $welfareCardModel;
    protected $welfareCardUseRecordModel;

    public function __construct()
    {
        $this->welfareCardModel = new WelfareCardModel();
        $this->welfareCardUseRecordModel = new WelfareCardUseRecordModel();
    }

    /**
     * 按ID查询
     *
     * @param int $id
     * @return array
     */
    public function getById($id)
    {
        return $this->welfareCardModel->getWelfareCardById($id);
    }

    /**
     * @param $id
     * @return array
     */
    public function getByIdForUpdate($id)
    {
        return $this->welfareCardModel->getWelfareCardById($id);
    }

    /**
     * 按卡密查询
     *
     * @param string $password
     * @return array
     */
    public function getByPassword($password)
    {
        return $this->welfareCardModel->getWelfareCardByPassword($password);
    }

    /**
     * 查询使用记录
     *
     * @param int $cardId
     * @return array
     */
    public function getUseRecord($cardId)
    {
        return $this->welfareCardUseRecordModel->getRecordByCardId($cardId);
    }

    /**
     * 按ID更新
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateById($id, array $data)
    {
        return $this->pureUpdate($this->getById($id), $data);
    }

    /**
     * 按卡密更新
     *
     * @param string $password
     * @param array $data
     * @return array
     */
    public function updateByPassword($password, array $data)
    {
        return $this->pureUpdate($this->getByPassword($password), $data);
    }

    /**
     * 锁定卡
     *
     * @param string $password
     * @return array
     */
    public function lockCardByPassword($password)
    {
        return $this->updateByPassword($password, array('status' => WelfareCardModel::STATUS_LOCKED));
    }

    /**
     * 更新数据
     *
     * @param array $origin
     * @param array $params
     * @return array
     */
    protected function pureUpdate($origin, array $params)
    {
        if (empty($origin)) {
            return $this->response('卡密不存在');
        }

        $data = array();

        // 允许更新的字段
        $optional = array(
            'status'
        );
        foreach ($optional as $key) {
            if (isset($params[$key]) && $origin[$key] != $params[$key]) {
                $data[$key] = $params[$key];
            }
        }

        if (!empty($data) && !$this->welfareCardModel->updateById($origin['card_id'], $data)) {
            return $this->response('更新失败');
        }

        return $this->response('更新成功', $data, 0);
    }

    /**
     * 使用卡密
     *
     * @param $password
     * @param $companyId
     * @param $memberId
     * @param string $memo
     * @return array
     */
    public function useCard($password, $companyId, $memberId, $memo = '')
    {
        // 暂时调用ThriftCenter
        $handler = new ThriftCenterClientAdapters();
        $data = array(
            'member_id' => $memberId,
            'company_id' => $companyId,
            'card_password' => $password,
        );

        $results = $handler->WelfareCardServer('welfareCardController/useWelfareCard', json_encode($data));

        $results = json_decode($results, true);

        if (empty($results)) {
            return $this->response('使用失败');
        }

        if ($results['code'] == 0) {
            return $this->response('使用成功', array(), 0);
        }

        return $this->response($results['message'], array(), $results['code']);
    }

    /**
     * @param string $message
     * @param array $data
     * @param int $code
     * @return array
     */
    protected function response($message = '', array $data = array(), $code = 10002)
    {
        return array(
            'code' => $code,
            'message' => $message,
            'data' => $data
        );
    }
}
