<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-24
 * Time: 11:48
 */

namespace App\Api\Logic\PointServer\Transaction;

abstract class ATransaction
{
    /**
     * @param $transactionData 交易数据
     * @return mixed
     */
    abstract public function Execute($transactionData);

    /**
     * @param $transactionData 交易数据查询
     * @return mixed
     */
    abstract public function Query($billList);


    protected function Response($status = true, $msg = '成功', $data = [] , $code = 0)
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
            'code' => $code
        ];
    }
}
