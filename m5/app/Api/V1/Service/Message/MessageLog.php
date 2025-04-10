<?php

namespace App\Api\V1\Service\Message;

use App\Api\Model\Message\MessageLog as MessageLogModel;

class MessageLog
{
    /**
     * 创建日志
     * @param $param
     * @return int 批次id
     */
    public static function batchStart($param)
    {
        $batchLogModel = new MessageLogModel();

        $batchId = $batchLogModel->insertBatchLog($param['channel'], $param['aging']);
        collect($param['data'])->each(function ($item, $key) use ($batchLogModel, $batchId) {
            foreach ($item['receiver'] as $receiver) {
                $item['receiver'] = $receiver;
                $batchLogModel->insertBatchLogInfo($batchId, $item);
            }
        });
        return $batchId;
    }

    /**
     * 变更批次日志状态
     * @param int $batchId  批次id
     * @param string $status 2.发送中3.已完成4.发送失败
     */
    public static function batch($batchId, $status)
    {
        if (!in_array($status, [
            MessageLogModel::SEND_STATUS_ING,
            MessageLogModel::SEND_STATUS_SUCCESS,
            MessageLogModel::SEND_STATUS_SYNC_RESULT_ING
        ])) {
            return;
        }
        $messageLogModel = new MessageLogModel();

        $messageLogModel->updateBatchLog($batchId, $status);
    }

    /**
     * 变更详情日志状态
     * @param  int  $batchId  批次id
     * @param $receiver
     * @param $templateId
     * @param  string  $status  2.发送中3.已完成4.发送失败
     * @param  string  $result
     * @param  string  $error  4 状态码
     * @return void
     */
    public static function info($batchId, $receiver, $templateId, $status, $sendBatch = '', $result = '', $error = '')
    {
        if (!in_array($status, [
            MessageLogModel::SEND_STATUS_ING,
            MessageLogModel::SEND_STATUS_SUCCESS,
            MessageLogModel::SEND_STATUS_FAIL
        ])) {
            return;
        }
        $messageLogModel = new MessageLogModel();

        return $messageLogModel->updateInfoLog($batchId, $receiver, $templateId, $status, $sendBatch, $result, $error);
    }

    /**
     * 获取处理进度
     * @param $batchId
     * @return array
     */
    public function progress($batchId)
    {
        $logInfoModel = new MessageLogModel();
        $messageLog = $logInfoModel->findBatchById($batchId);

        $messageService = new MessageProducer();
        $platform = $messageService->findPlatformByChannel($messageLog->channel);
        $classObj = new $platform['class'](
            [
                'channel' => $messageLog->channel,
                'data' => []
            ]);
        if (!$classObj->syncResult) {
            $batchInfoList = $logInfoModel->getSendBatchInfo($batchId);
            $total = $success = 0;
            foreach ($batchInfoList as $batchInfo) {
                $result = json_decode($batchInfo->async_result, true);
                $asyncResult = $classObj->resultMapping($result);
                if ($asyncResult['result']) {
                    $total += $asyncResult['total'];
                    $success += $asyncResult['success'];
                }
            }
            return [
                'result' => $total > 0,
                'total' => $total,
                'success' => $success
            ];
        }

        return [
            'result'=>true,
            'status' => $logInfoModel->getBatchStatus($batchId),
            'total' => $logInfoModel->getBatchCount($batchId),
            'success' => $logInfoModel->getBatchCount($batchId, MessageLogModel::SEND_STATUS_SUCCESS),
            'failure' => $logInfoModel->getBatchInfo($batchId, MessageLogModel::SEND_STATUS_FAIL)
        ];
    }
}
