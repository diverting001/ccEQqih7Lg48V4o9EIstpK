<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-05-31
 * Time: 11:31
 */

namespace App\Api\V1\Service\Bill;

use App\Api\Model\Bill\App as AppMdl;


class App
{
    /**
     * 单据查询
     * @param $code
     * @return bool|mixed
     */
    public function GetListByCode($code)
    {
        if (empty($code)) {
            return false;
        }
        $list = AppMdl::GetListByCode($code);
        return $list;
    }

}
