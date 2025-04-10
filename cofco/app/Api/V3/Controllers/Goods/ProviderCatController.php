<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\ProviderCat;
use Illuminate\Http\Request;

class ProviderCatController extends BaseController
{
    /**
     * @param Request $request
     *
     * @return array
     */
    public function GetList(Request $request)
    {
        $pars = $this->getContentArray($request);

        $return_data    = (new ProviderCat())->getList($pars);

        return $this->outputFormat($return_data);
    }
}
