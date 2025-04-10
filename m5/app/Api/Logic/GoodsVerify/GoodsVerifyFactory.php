<?php

namespace App\Api\Logic\GoodsVerify;

class GoodsVerifyFactory
{

    private $supplierBnPlatformMap = [
        'JD' => 'Salyut',
    ];

    /**
     * supplier_bn 所属平台
     * @param $supplierBn
     * @return string
     */
    public function getSupplierBnPlatform($supplierBn) :string
    {
         return $this->supplierBnPlatformMap[$supplierBn] ?? '';
    }

    /**
     * 实例化平台校验类
     * @param string $platform
     * @return \Laravel\Lumen\Application|mixed
     * @throws \Exception
     */
    public function createObj(string $platform)
    {
        switch ($platform) {
            case 'Salyut':
                return app(Salyut::class);
            default:
                throw new \Exception("Unknown platform: $platform");
        }
    }
}
