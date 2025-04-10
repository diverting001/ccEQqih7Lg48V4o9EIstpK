<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class AccountTransfer
{
    /**
     * 添加入账信息
     */
    public static function Create($transferInfo)
    {
        $transferInfo = array(
            'bill_code'         => $transferInfo['bill_code'],
            'son_account_id'    => $transferInfo['son_account_id'],
            'to_son_account_id' => $transferInfo['to_son_account_id'],
            'point'             => $transferInfo['point'],
            'memo'              => $transferInfo['memo'],
            'created_at'        => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_account_transfer')->insert($transferInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function GetOneAddRecord($toSonAccountId)
    {
        return app('api_db')->table('server_new_point_account_transfer as tra')
            ->leftJoin('server_new_point_business_bill_rel as rel', 'rel.bill_code', '=', 'tra.bill_code')
            ->leftJoin(
                'server_new_point_business_flow as flow',
                'flow.business_flow_code',
                '=',
                'rel.business_flow_code'
            )
            ->select(
                'tra.son_account_id',
                'tra.to_son_account_id',
                'tra.point',
                'flow.business_type',
                'flow.business_bn'
            )
            ->where('tra.to_son_account_id', $toSonAccountId)
            ->orderBy('tra.created_at', 'asc')
            ->first();
    }

    public static function QueryByBillAndToAccount($billCodes, $toAccountIds)
    {
        foreach ($billCodes as &$billCode) {
            $billCode = strval($billCode);
        }
        return app('api_db')->table('server_new_point_account_transfer')
            ->whereIn('bill_code', $billCodes)
            ->whereIn('to_son_account_id', $toAccountIds)
            ->get();
    }

    /**
     * 根据条件获取转账记录
     * @param array    $conditions
     * @param string[] $columns
     *
     * @return array
     */
    public static function GetAccountTransfer($conditions = [], $columns = ['*'])
    {
        if (!$conditions) {
            return [];
        }

        if (!is_array($conditions['to_son_account_id'])) {
            $conditions['to_son_account_id'] = [$conditions['to_son_account_id']];
        }

        $data = app('api_db')->table('server_new_point_account_transfer')
                     ->select($columns)
                     ->whereIn('to_son_account_id', $conditions['to_son_account_id'])
                     ->orderBy('created_at', 'asc')
                     ->get();
        $data || $data = collect([]);

        return $data->toArray();
    }
}
