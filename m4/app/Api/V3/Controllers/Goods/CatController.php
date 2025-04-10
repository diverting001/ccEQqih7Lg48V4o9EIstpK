<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Cat;
use Illuminate\Http\Request;

class CatController extends BaseController
{
    /**
     * @param Request $request
     *
     * @return array
     */
    public function GetList(Request $request)
    {
        $pars = $this->getContentArray($request);

        $return_data = (new Cat())->getList($pars);

        return $this->outputFormat($return_data);
    }
}
