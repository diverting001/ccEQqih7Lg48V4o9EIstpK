<?php

namespace App\Api\Logic;

class CashBlacklist
{
    public static function getCompanyBlacklistRule(int $companyId, array $productBns)
    {
        if (empty($companyId) || empty($productBns)) {
            return false;
        }

        $_curl = new \Neigou\Curl();
        $_curl->time_out = 7;

        $params = array(
            'company_id' => $companyId,
            'product_bns' => $productBns
        );
        $post_data = array(
            'data' => base64_encode(json_encode($params)),
        );
        $post_data['token'] = \App\Api\Common\Common::GetEcStoreSign($post_data);
        $result = $_curl->Post(config('neigou.STORE_DOMIN') . '/openapi/cashBlacklist/getCompanyBlacklistRule', $post_data);
        $result = json_decode($result, true);

        if (empty($result['Result']) || $result['Result'] == 'false') {
            \Neigou\Logger::Debug('getCompanyBlacklistRule', array('response' => $result, 'params' => $post_data));
            return false;
        }

        return $result['Data'];
    }
}
