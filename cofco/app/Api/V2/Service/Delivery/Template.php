<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-10-18
 * Time: 15:16
 */

namespace App\Api\V2\Service\Delivery;

use App\Api\Model\Delivery\Template as TemplateModel;

class Template
{
    public function Create($tempInfo)
    {
        do {
            $tempBn = str_random(10);;

            $tempBnIsset = TemplateModel::Find($tempBn);
        } while ($tempBnIsset);

        $tempInfo['template_bn'] = $tempBn;

        $id = TemplateModel::Create($tempInfo);

        if (!$id) {
            return $this->Response(false, "创建失败");
        }

        return $this->Response(true, "创建成功", array(
            "template_bn" => $tempBn
        ));
    }

    public function Update($tempBn, $tempInfo)
    {

        $status = TemplateModel::Update($tempBn, $tempInfo);
        if (!$status) {
            return $this->Response(false, "修改失败");
        }

        return $this->Response(true, "修改成功");
    }

    protected function Response($status = true, $msg = '成功', $data = [])
    {
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
        ];
    }
}
