<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-24
 * Time: 11:27
 */

namespace App\Api\Logic\PointServer\Operation;

use App\Api\Logic\PointServer\Operation\FrozenPool as FrozenPoolServer;

class ReleaseFrozen extends AOperation
{
    const OPERATION_TYPE = 'release_frozen';

    /**
     * 账户余额冻结
     */
    public function Execute($operationData)
    {
        app('db')->beginTransaction();
        //生成冻结单数据
        $frozenPoolServer = new FrozenPoolServer();
        $releaseRes = $frozenPoolServer->Release($operationData);
        if (!$releaseRes['status']) {
            app('db')->rollBack();
            return $releaseRes;
        }
        app('db')->commit();
        return $this->Response(true, '释放冻结成功', $releaseRes['data']);
    }
}
