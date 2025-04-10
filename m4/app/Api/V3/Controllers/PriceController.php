<?php

namespace App\Api\V3\Controllers;
use App\Api\Common\Controllers\BaseController;
use App\Api\V3\Service\Pricing\GetPrice;
use Illuminate\Http\Request;

class PriceController extends BaseController
{
    /*
     * @todo 获取商品场景价格
     */
    public function GetList(Request $request){
        $content_data = $this->getContentArray($request);
        $filter = $content_data['filter'];
        $environment = $content_data['environment'];
        $product_list    = array(); //货品列表
        foreach ($filter['product_bn_list'] as $v){
            $product_list[] = [
                'product_bn'    => $v,
                'branch_id' => isset($filter['branch_id']) && !empty($filter['branch_id'])?$filter['branch_id']:0,
            ];
        }
        //场景列表
        $stages = array(
            'project_code'  => empty($environment['project_code'])?'':$environment['project_code'],
            'company'  => empty($environment['company_id'])?0:$environment['company_id'],
            'channel'  => empty($environment['channel'])?'':$environment['channel'],
            'tag'  => empty($environment['tag'])?'':$environment['tag'],
        );
        $price_service = new GetPrice();
        //获取货品价格
        $price_list = $price_service->GetPrice($product_list,$stages);
        return $this->outputFormat($price_list);
    }
}
