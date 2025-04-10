<?php

namespace App\Api\Model\Express;

use Illuminate\Database\Eloquent\Model;

class PickupChannel extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'server_express_pickup_channel';

    public $timestamps = false;


    public function getChannel($channel = '') : array
    {
        $res =  $this->where('channel', $channel)->first();
        return empty($res) ? [] : $res->toArray();
    }

    public function getDefaultChannel() {
        $res =  $this->where('is_default', 1)->first();
        return empty($res) ? [] : $res->toArray();
    }
}
