<?php
/**
 * Created by PhpStorm.
 * User: liuming
 * Date: 2018/11/15
 * Time: 8:21 PM
 */

namespace App\Console\Commands;

use App\Api\Logic\Invoice\ElectronicInvoice;
use App\Api\Model\Invoice\EInvoice AS EInvoiceModel;
use Illuminate\Console\Command;
use Neigou\Logger;

class EInvoice extends Command
{
    protected $signature = 'EInvoice';
    protected $description = '发票下载信息拉取';

    public function handle(){
        $einvoiceModel = new EInvoiceModel();
        $modelRes = $einvoiceModel->getEInoiceAll(array('status' => 0));
        $einvoiceLogic = new ElectronicInvoice();
        foreach ($modelRes as $v){
            $vArr = get_object_vars($v);
            $_res = $einvoiceLogic->DownloadInvoice($vArr);

            if (!$_res['status']){ //如果发票处理失败, 记录日志
                Logger::General('einvoice_faild',array('swno' => $v->swno,'msg' => $_res['msg']));
            }
        }
    }
}
