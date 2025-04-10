<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-24
 * Time: 11:27
 */

namespace App\Api\Logic\PointServer\Operation;

use App\Api\Logic\PointServer\Operation\FrozenPool as FrozenPoolServer;

class CreateFrozen extends AOperation
{
    const OPERATION_TYPE = 'create_frozen';

    /**
     * 账户余额冻结
     */
    public function Execute($operationData)
    {
        app('db')->beginTransaction();
        //生成冻结单数据
        $frozenPoolServer    = new FrozenPoolServer();
        $createFrozenPoolRes = $frozenPoolServer->Create($operationData);
        if (!$createFrozenPoolRes['status']) {
            app('db')->rollBack();
            return $createFrozenPoolRes;
        }
        app('db')->commit();
        return $this->Response(true, '冻结成功', $createFrozenPoolRes['data']);
    }
}
