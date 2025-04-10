<?php

namespace App\Api\Model\Message;

class MessageBlacklist
{
    public function blacklistExists($receiver)
    {
        if (is_string($receiver)) {
            $receiver = [$receiver];
        }
        return app('api_db')->table('server_message_blacklist')->whereIn('receiver', $receiver)->exists();
    }


}
