<?php

namespace App\Api\Logic\AssetType;

abstract class Asset
{
    // status 状态
    protected $_status = array(
        0 => '注册',
        1 => '锁定',
        2 => '取消',
        3 => '使用',
        4 => '异常',
    );

    /*
     * @todo 获取资产状态
     */
    abstract public function getAssetStatus($assetInfo, $objDetail);
}
