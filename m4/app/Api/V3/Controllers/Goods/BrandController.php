<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Brand;
use Illuminate\Http\Request;

class BrandController extends BaseController
{
    /**
     * @param Request $request
     *
     * @return array
     */
    public function SearchList(Request $request)
    {
        $pars = $this->getContentArray($request);

        $return_data    = (new Brand())->searchList($pars);

        return $this->outputFormat($return_data);
    }
}
