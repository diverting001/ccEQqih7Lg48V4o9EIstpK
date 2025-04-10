<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-24
 * Time: 11:48
 */

namespace App\Api\Logic\PointServer\Operation;

use App\Api\Model\PointServer\Account as AccountModel;

abstract class AOperation
{
    /**
     * @param $transactionData 交易数据
     * @return mixed
     */
    abstract public function Execute($operationData);

    protected function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg' => $msg,
            'data' => $data,
        ];
    }
}
