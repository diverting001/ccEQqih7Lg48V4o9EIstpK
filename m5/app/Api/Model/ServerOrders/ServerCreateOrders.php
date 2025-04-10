<?php
/**
 * Created by PhpStorm.
 * User: guke
 * Date: 2018/4/26
 * Time: 14:27
 */

namespace app\Api\Model\ServerOrders;
use App\Api\Model\BaseModel ;
class ServerCreateOrders  extends   BaseModel
{
    protected $connection = 'mysql' ;

    protected $table = 'server_calculate';
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';


}
