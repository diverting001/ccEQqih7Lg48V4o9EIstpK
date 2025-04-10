<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\OriBrand;
use Illuminate\Http\Request;

class OriBrandController extends BaseController
{
    /**
     * @param Request $request
     *
     * @return array
     */
    public function SearchList(Request $request)
    {
        $pars = $this->getContentArray($request);

        $return_data    = (new OriBrand())->searchList($pars);

        return $this->outputFormat($return_data);
    }
}
