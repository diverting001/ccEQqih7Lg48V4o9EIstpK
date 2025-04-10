<?php



namespace App\Api\Model\Express;


class CompanyMapping
{

    /**
     * @param $corp_code
     * @param $channel
     * @return array
     */
    public static function getCompanyMapping($code, $channel, $code_type = '')
    {
        $return = array();

        if (empty($code) || empty($channel))
        {
            return $return;
        }

        if ($code_type == 'channel') {

            $where = [
                'logi_code'   => $code,
                'channel'       => $channel
            ];
        } else {
            $where = [
                'corp_code'   => $code,
                'channel'       => $channel
            ];
        }

        $return = app('api_db')->table('server_express_company_channel_relation')->where($where)->first();


        $return = $return ? get_object_vars($return) : array();

        //保存空物流
        if (empty($return)) {
            self::addCompanyMapping($where);
        }

        return $return;
    }

    /**
     * @param $corp_codes
     * @param $channels
     * @return array
     */
    public static function getCompanyMappingList($codes, $channels, $code_type = '')
    {

        $return = array();

        $codes = is_array($codes) ? $codes : explode(',', $codes);

        $channels = is_array($channels) ? $channels : explode(',', $channels);

        if (empty($codes) || empty($channels))
        {
            return $return;
        }

        if ($code_type == 'channel') {

            $where = [
                [function($query) use ($codes) {
                    $query->whereIn('logi_code', $codes);
                }],
                [function($query) use ($channels) {
                    $query->whereIn('channel', $channels);
                }]
            ];
        } else {
            $where = [
                [function($query) use ($codes) {
                    $query->whereIn('corp_code', $codes);
                }],
                [function($query) use ($channels) {
                    $query->whereIn('channel', $channels);
                }]
            ];
        }

        $return = app('api_db')->table('server_express_company_channel_relation')->where($where)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        //所有应该匹配出来的数据
        $mappingList = array();
        foreach ($codes as $code) {
            foreach ($channels as $channel) {
                $mapping = array(
                    'channel'=>$channel,
                );
                if ($code_type == 'channel') {
                    $mapping['logi_code'] = $code;
                } else {
                    $mapping['corp_code'] = $code;
                }
                $mappingList[$channel.'-'.$code] = $mapping;
            }
        }

        //实际上匹配出来的数据
        foreach ($return as $v) {
            $key = $v['channel'].'-'.($code_type == 'channel' ? $v['logi_code'] : $v['corp_code']);
            unset($mappingList[$key]);
        }

        //如果有未匹配上的则进行写入
        if (!empty($mappingList)) {
            self::addCompanyMapping($mappingList);
        }

        return $return;
    }

    /**
     * @param $data
     * @return false
     */
    public static function addCompanyMapping($data) {
        if (empty($data)) {
            return false;
        }
        return app('api_db')->table('server_express_company_channel_relation')->insert($data);
    }
    /**
     * @param $channel
     * @param $filter
     * @return array
     */
    public static function getChannelCompanyList($channel, $filter = array())
    {
        $return = array();

        $db = app('api_db')->table('server_express_company_channel_relation');
        $where = array(
            'channel' => $channel,
        );
        if ( ! empty($filter['corp_code'])) {
            if (is_array($filter['corp_code'])) {
                $db->whereIn('corp_code', $filter['corp_code']);
            } else {
                $where['corp_code'] = $filter['corp_code'];
            }
        }

        if ( ! empty($filter['logi_code'])) {
            if (is_array($filter['logi_code'])) {
                $db->whereIn('logi_code', $filter['logi_code']);
            } else {
                $where['logi_code'] = $filter['logi_code'];
            }
        }

        if ( ! empty($filter['logi_name'])) {
            if (is_array($filter['logi_name'])) {
                $db->whereIn('logi_name', $filter['logi_name']);
            } else {
                $where['logi_name'] = $filter['logi_name'];
            }
        }

        $result = $db->where($where)->get()->toArray();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

}
