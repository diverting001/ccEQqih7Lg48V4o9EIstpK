<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/11/6
 * Time: 15:05
 */

namespace App\Api\V1\Controllers;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Credit\CreditLimit;
use Illuminate\Http\Request;

class CreditLimitController extends BaseController
{
    /**
     * 消费
     */
    public function Record(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        //获取账户ID
        $data['account_id'] = $this->_get_account_id($data, $mdl);
        if ($data['account_id'] <= 0) {
            $this->setErrorMsg('账户不存在');
            return $this->outputFormat(0, 1001);
        }
        $trans_id = $mdl->insert_record($data);
        if ($trans_id > 0) {
            $this->setErrorMsg('消费成功');
            return $this->outputFormat($trans_id);
        } else {
            $this->setErrorMsg('消费失败');
            return $this->outputFormat(0, 404);
        }
    }

    //取消消费 按订单号取消
    public function CancelRecord(Request $request){
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $trans_id = $mdl->cancelRecord($data['trans_id']);
        if ($trans_id > 0) {
            $this->setErrorMsg('取消成功');
            return $this->outputFormat($trans_id);
        } else {
            $this->setErrorMsg('取消失败');
            return $this->outputFormat(0, 404);
        }
    }

    /**
     * 还款
     */
    public function Reply(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $data['account_id'] = $this->_get_account_id($data, $mdl);
        if ($data['account_id'] <= 0) {
            $this->setErrorMsg('账户不存在');
            return $this->outputFormat(0, 1001);
        }
        $trans_id = $mdl->insert_record($data);
        if ($trans_id > 0) {
            $this->setErrorMsg('还款成功');
            return $this->outputFormat($trans_id);
        } else {
            $this->setErrorMsg('还款失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 当前余额
     */
    public function Balance(Request $request)
    {
        $data = $this->getContentArray($request);
        //获取账户的固定额度
        $mdl = new CreditLimit();
        $account_id = $this->_get_account_id($data, $mdl);
        $res = $mdl->getAccount(array('id' => $account_id));
        if ($res) {
            $out['credit_limit'] = $res->credit_limit;//固定额度
            //获取现在的可用额度
            $out['balance'] = $res->balance + $res->credit_limit;
            $out['now_use'] = $res->balance;
            $out['ac_id'] = $res->id;
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 消费检测
     */
    public function Check(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $account_info = $mdl->getAccount(array('id' => $data['account_id']));
        if (abs($account_info->balance - $data['rmb_amount']) > $account_info->credit_limit) {
            $this->setErrorMsg('超额');
            return $this->outputFormat($account_info, 404);
        } else {
            $this->setErrorMsg('成功');
            return $this->outputFormat($account_info);
        }
    }

    ///////////// 商户管理 /////////////////

    /**
     * 创建商户
     */
    public function CreateAccount(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $acc_id = $mdl->insert_account($data);
        if ($acc_id > 0) {
            if(!empty($data['limit_part'])){
                //添加limit_part
                $res = $mdl->setAccountLimitPart($acc_id,$data['limit_part']);
                if(!$res){
                    $this->setErrorMsg('创建limit_part失败');
                    return $this->outputFormat(null, 406);
                }
            }

            $this->setErrorMsg('创建成功');
            return $this->outputFormat($acc_id);
        } else {
            $this->setErrorMsg('创建失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 修改商户
     */
    public function EditAccount(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $res = $mdl->edit_account($data['where'], $data['set']);
        if ($res) {
            $this->setErrorMsg('修改成功');
            return $this->outputFormat(1);
        } else {
            $this->setErrorMsg('修改失败');
            return $this->outputFormat(0, 404);
        }

    }

    /**
     * 账单查询
     */
    public function AccountReport()
    {

    }

    /**
     * 商户列表
     */
    public function AccountList(Request $request)
    {
        $data = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $result = $mdl->getSearchPageList($data['where'], $data['page'], $data['limit']);
        if (!empty($result)) {
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($result);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 商户详情
     */
    public function AccountInfo(Request $request)
    {
        $account_id = $this->getContentArray($request);
        $mdl = new CreditLimit();
        $res = $mdl->getAccount(array('id' => $account_id));
        if ($res) {
            $out['credit_limit'] = $res->credit_limit;//固定额度
            //获取现在的可用额度
            $out['balance'] = $mdl->getNowBalance($account_id);
            //商户详情
            $out['info'] = $res;
            $this->setErrorMsg('请求成功');
            return $this->outputFormat($out);
        } else {
            $this->setErrorMsg('请求失败');
            return $this->outputFormat(null, 404);
        }
    }

    /**
     * 获取 账户ID
     * @param $data
     * @param $mdl
     * @return int
     */
    private function _get_account_id($data, $mdl)
    {
        $channel = $data['channel'];
        $company_id = $data['company_id'];
        $where['account_type'] = 'channel';
        $where['account_value'] = $channel;
        $res = $mdl->getAccount($where);
        if (!$res) {
            $where['account_type'] = 'company';
            $where['account_value'] = $company_id;
            $res = $mdl->getAccount($where);
        }
        if (!$res) {
            return 0;
        } else {
            //判断是否有对应的part
            $parts = $mdl->getAccountLimitPart($res->id);
            if(!in_array($data['part'],$parts)){
                return 0;
            } else {
                return $res->id;
            }
        }
    }

}
