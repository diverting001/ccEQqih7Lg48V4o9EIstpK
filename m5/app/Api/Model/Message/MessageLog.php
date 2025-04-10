<?php

namespace App\Api\Model\Message;

use Carbon\Carbon;

class MessageLog
{
    const SEND_STATUS_READY = 1; //待发送
    const SEND_STATUS_ING = 2; //发送中
    const SEND_STATUS_SUCCESS = 3; //发送完成
    const SEND_STATUS_FAIL = 4; //发送失败
    const SEND_STATUS_SYNC_RESULT_ING = 5; //等待同步结果

    public function insertBatchLog($channel, $aging = 1)
    {
        $data = [
            'channel' => $channel,
            'aging' => $aging ?? 1,
            'status' => self::SEND_STATUS_READY,
            'created_at' => Carbon::now()->toDateTimeString(),
        ];

        return app('api_db')->table('server_message_batch_log')->insertGetId($data);
    }

    public function insertBatchLogInfo($batchId, $param)
    {
        $data = [
            'batch_id' => $batchId,
            'receiver' => $param['receiver'],
            'template_id' => $param['template_id'],
            'template_param' => serialize($param['template_param']),
            'extra_param'   =>  isset($param['extra_param']) ? serialize($param['extra_param']) : '',
            'status' => 1,
            'created_at' => Carbon::now()->toDateTimeString(),
        ];

        return app('api_db')->table('server_message_batch_log_info')->insert($data);
    }

    public function updateBatchLog($id, $status)
    {
        //1.待发送2.发送中3.已完成
        $data ['status'] = $status;
        $nowData = Carbon::now()->toDateTimeString();
        if (in_array($status, [self::SEND_STATUS_SUCCESS, self::SEND_STATUS_SYNC_RESULT_ING])) {
            $data ['send_at'] = $nowData;
        }
        $data ['update_at'] = $nowData;
        return app('api_db')->table('server_message_batch_log')->where('id', $id)->update($data);
    }

    public function updateInfoLog($batchId, $receiver, $templateId, $status, $sendBatch = '', $result = '', $resultCode = '')
    {
        $data ['status'] = $status;
        $nowData = Carbon::now()->toDateTimeString();
        if ($status == self::SEND_STATUS_SUCCESS || !empty($result)) {
            $data ['send_at'] = $nowData;
            $data['result'] = $result;
        }
        if ($status == self::SEND_STATUS_FAIL) {
            $data ['error_code'] = $resultCode;
            $data['result'] = $result;
        }
        $data ['update_at'] = $nowData;
        if (!empty($sendBatch)) {
            $data['send_batch'] = $sendBatch;
        }
        return app('api_db')->table('server_message_batch_log_info')
            ->where('batch_id', $batchId)
            ->where('receiver', $receiver)
            ->where('template_id', $templateId)
            ->update($data);
    }

    public function updateInfoLogByBatch($batchId, $sendBatch,$status, $asyncResult)
    {
        $nowData = Carbon::now()->toDateTimeString();

        return app('api_db')->table('server_message_batch_log_info')
            ->where('batch_id', $batchId)
            ->where('send_batch', $sendBatch)
            ->update(['status' => $status, 'async_result' => $asyncResult, 'update_at' => $nowData]);
    }

    public function getBatchCount($batchId, $status = null)
    {
        $query = app('api_db')->table('server_message_batch_log_info')->where('batch_id', $batchId);
        if ($status) {
            $query->where('status', $status);
        }
        return $query->count('id');
    }

    public function getBatchStatus($id)
    {
        $query = app('api_db')->table('server_message_batch_log')->where('id', $id);

        return $query->first(['status'])->status;
    }

    public function getBatchInfo($batchId, $status = null)
    {
        $query = app('api_db')->table('server_message_batch_log_info')->where('batch_id', $batchId);
        if ($status) {
            $query->where('status', $status);
        }
        return $query->get(['receiver','template_id','template_param','error_code','result'])->all();
    }

    public function getBatchByStatus($status)
    {
        $db = app('api_db');
        $query = $db->table('server_message_batch_log');
        if ($status) {
            $query->where('status', $status);
        }
        return $query->get()->all();
    }

    public function findBatchById($id)
    {
        $db = app('api_db');
        return $db->table('server_message_batch_log')
            ->where('id', $id)
            ->first();
    }

    public function getSendBatchInfo($batchId)
    {
        $query = app('api_db')->table('server_message_batch_log_info')
            ->where('batch_id', $batchId)
            ->groupBy('send_batch');

        return $query->get()->all();
    }
}
