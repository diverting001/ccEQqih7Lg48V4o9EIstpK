<?php

namespace App\Api\V3\Controllers\Goods;

use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Cat;
use Illuminate\Http\Request;
use App\Api\Logic\Redis ;


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
    // 根据Id Id 获取tree形结构
    public function GetTreeList(Request $request) {

        $pars = $this->getContentArray($request);
        $catids = $pars['filter']['cat_id'] ;
        $return_data =   $this->getCatLists($catids) ;
        $data = array() ;
        if(empty($return_data)) {
            return $this->outputFormat($return_data);
        }
        $level2 = [] ;
        foreach ($return_data as $cat_id => $item) {
            list($p1,$p2) = explode(',' ,$item['cat_path']) ;
            if($p1) {
                $level2[] = $p1;
            }
            if($p2) {
                $level2[] = $p2 ;
            }
            $data[$cat_id] = $item ;
        }
        $level2_data =   $this->getCatLists($level2) ;
        if(!empty($level2_data)) {
           foreach ($level2_data as $cat_id=>$item) {
               $data[$cat_id] = $item ;
           }
        }
        $tree_data =   $this->getTree($data) ;
        return   $this->outputFormat($tree_data);
    }

    /**
     * @description
     * @param array  $arr 二维数组
     * @param string $pk 主键id
     * @param string $upid 表示父级id的字段
     * @param string $child 子目录的键
     * @return array
     */
    protected function  getTree($items,$upid='parent_id',$child='son'){
        $tree = array();
        foreach($items as $k=>$val){
            if(isset($items[$val[$upid]])){
                $items[$val[$upid]][$child][]=&$items[$k];
            }else{
                $tree[] = &$items[$k];
            }
      }
      return $tree;
}

    //redis 中获取数据
    protected function getCatLists($catids)
    {
        if(empty($catids)) {
            return false ;
        }

        $return_data = (new Cat())->getList(array('filter' => ['cat_id' => $catids]));
        $return_data = $return_data ? $return_data['list'] : false  ;

        if(empty($return_data)) {
            return false ;
        }
        $data = array() ;
        foreach ($return_data as $item) {
            if(is_string($item)) {
                $item = json_decode($item ,true) ;
            } else {
                $item = get_object_vars($item) ;
            }
            $cat_path = trim($item['cat_path'],',') ;
            $data[$item['cat_id']] = array(
                'cat_id' => $item['cat_id'] ,
                'cat_name' =>$item['cat_name'] ,
                'parent_id' => $item['parent_id'] ,
                'cat_path' => $cat_path ,
            ) ;
        }
        return $data ;
    }


    public function UpdateBarcodeSwitch(Request $request)
    {
        $pars = $this->getContentArray($request);

        if (!$pars['cat_id']) {
            return $this->outputFormat([], 400);
        }

        if (!in_array($pars['barcode_switch'], [1,0])) {
            return $this->outputFormat([], 400);
        }

        $barcodeSwitch = $pars['barcode_switch'];
        $catId = $pars['cat_id'];

        $return_data = (new Cat())->UpdateBarcodeSwitch($catId, $barcodeSwitch);

        return $this->outputFormat($return_data);
    }


}
