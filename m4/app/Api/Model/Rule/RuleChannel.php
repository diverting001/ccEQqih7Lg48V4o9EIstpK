<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-09-19
 * Time: 15:52
 */

namespace App\Api\Model\Rule;


class RuleChannel
{
    public function getRuleChannel()
    {
        $channelList = app('api_db')->table('server_rule_channel')->get();
        $returnData = array();
        foreach ($channelList as $channel){
            $returnData[$channel->channel] = $channel;
        }
        return $returnData;
    }
}
