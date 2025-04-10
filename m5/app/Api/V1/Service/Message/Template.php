<?php
namespace App\Api\V1\Service\Message;

use App\Api\Model\Message\Channel;
use App\Api\Model\Message\MessageTemplate;
use App\Api\Model\Message\MessageTemplate as TemplateModel;
use App\Api\V1\Service\ServiceTrait;
use Illuminate\Support\Facades\DB;
use Swoole\Database\MysqliException;

/**
 * 消息模板配置
 */
class Template
{
    use ServiceTrait;
    /**
     * 创建消息模板
     * @param $param
     * @return \Closure
     */
    public function createTemplate($param)
    {
        $templateModel = new TemplateModel();
        if ($templateModel->templateExists('name', $param['name'],2)) {
            return $this->outputFormat($param, '标题已存在', MessageHandler::CODE_FIELD_FAIL);
        }

        $id = $templateModel->insertTemplate($param);
        $data = $id ? array_merge($param, ['id' => $id]) : null;
        if (!$data) {
            return $this->outputFormat($param, '请求失败', MessageHandler::CODE_ERROR);
        }
        return $this->outputFormat($data);
    }
    /**
     * 修改消息模板
     * @param $param
     * @return \Closure
     */
    public function editTemplate($id = 0,$param = [])
    {
        $templateModel = new TemplateModel();

        if (!$templateModel->templateExists('id', $id,2)) {
            return $this->outputFormat($param, '模板id不存在', MessageHandler::CODE_FIELD_FAIL);
        }

        try {
            app('db')->beginTransaction();
            $row = $templateModel->updateTemplate($id, $param);
            if (!$row) {
                throw new \Exception('更新消息模板失败');
            }
            if ($param['description']){
                $templateChannel['template_data'] = $param['description'];
                $templateModel->updateTemplateChannel($id,$templateChannel);
            }
            app('db')->commit();
        }catch (MysqliException $e){
            app('db')->rollBack();
            return $this->outputFormat($param, $e->getMessage(), MessageHandler::CODE_ERROR);
        }


        return $this->outputFormat($templateModel->firstTemplate($id));
    }

    /**
     * 消息模板列表
     * @param $param
     * @return \Closure
     */
    public function getTemplate($param)
    {
        $templateModel = new TemplateModel();
        $rows = $templateModel->getTemplate($param['name']);
        if (!$rows) {
            return $this->outputFormat($param, '请求失败', MessageHandler::CODE_ERROR);
        }
        return $this->outputFormat($rows);
    }

    /**
     * Notes:模版和渠道绑定
     * @param int $templateId
     * @param int $channelIds
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/15
     */
    public function bindTemplateChannel($templateId = 0,$channelIds = array()){
        if (empty($templateId) || empty($channelIds)){
            return $this->outputFormat([], '基本参数错误', MessageHandler::CODE_ERROR);
        }

        $templateModel = new TemplateModel();
        // ---- 检查模板和渠道 begin ----//
        // 判断模板是否存在
        $tempInfo = $templateModel->firstTemplate($templateId);
        if (empty($tempInfo) || $tempInfo->is_delete == 1){
            return $this->outputFormat([], '模版id: '.implode(',',$templateId).'不存在', MessageHandler::CODE_ERROR);
        }

        // 判断channel是否存在
        $channelModel = new Channel();
        $exWhere = [
            [
                'key' => 'channel.id',
                'express' => 'in',
                'value' => $channelIds
            ]
        ];
        $channeList = $channelModel->findList([],$exWhere);
        //dd(json_decode(json_encode($channeList),true));
        if ($channeList->isEmpty()){
            return $this->outputFormat([], '渠道不存在', MessageHandler::CODE_ERROR);
        }

        $findChannelIds = [];
        $errChannelNameList = [];
        foreach ($channeList as $channelV){
            $findChannelIds[] = $channelV->id;
            if ($channelV->type != $tempInfo->type){
                $errChannelNameList[] = $channelV->channel;
            }
        }
        if ($errChannelNameList){
            return $this->outputFormat([], '渠道和所属模板类型不一致: '.implode(',',$errChannelNameList), MessageHandler::CODE_ERROR);
        }

        $diffChannel = array_diff($channelIds,$findChannelIds);
        if ($diffChannel){
            return $this->outputFormat([], '下列渠道id不存在: '.implode(',',$diffChannel), MessageHandler::CODE_ERROR);
        }

        // 判断模板类型和渠道类型是否正确

        // ---- 检查模板和渠道 end ----//

        // ---- 绑定操作 begin ----//
        try {
            $addData = [];
            $messageModel = new MessageTemplate();
            $messageModel->deleteTemplateByTemplateAndChannelIds($templateId,$channelIds);
            foreach ($channeList as $v){
                $templateChannelInfo = $messageModel->getPlatformTemplate($templateId,$v->id);
                if ($templateChannelInfo){
                    continue;
                }

                $templateData = ($v->platform_id != 1) ? $tempInfo->description : '';
                $addData[] = [
                    'template_id' => $tempInfo->id,
                    'template_data' => $templateData,
                    'channel_id' => $v->id
                ];
            }

            if ($addData){
                $batchRes = $messageModel->batchInsert($addData);
                // ---- 绑定操作 end ----//
                if (empty($batchRes)){
                    throw new \Exception('新增渠道模板失败',MessageHandler::CODE_ERROR);
                }
            }
            return $this->outputFormat(['channel_ids' => $channelIds]);
        }catch (MysqliException $e){
            return $this->outputFormat([], $e->getMessage(), $e->getCode());
        }

    }

    /**
     * Notes: 获取模板列表
     * @param array $where 查询条件
     * @param int $offset
     * @param int $limit
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/19
     */
    public function getTemplateList($where = [],$offset = 0,$limit = 20)
    {
        $templateModel = new TemplateModel();
        $count = $templateModel->findTemplateCount($where);
        $list = $templateModel->findTemplateList($where,$offset,$limit);
        if (!$list) {
            return $this->outputFormat([], '请求失败', MessageHandler::CODE_ERROR);
        }
        $data = [
            'count' => $count,
            'list' => $list
        ];
        return $this->outputFormat($data);
    }

    /**
     * Notes: 获取模版信息byid
     * @param $id
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/19
     */
    public function getTemplateById($id,$channelId = 0)
    {
        $templateModel = new TemplateModel();
        $templateDetail = $templateModel->firstTemplate($id,$channelId);
        if (!$templateDetail) {
            return $this->outputFormat([], '请求失败', MessageHandler::CODE_ERROR);
        }
        return $this->outputFormat($templateDetail);
    }

    /**
     * Notes: 获取模板绑定的渠道信息
     * @param int $id
     * @return \Closure
     * Author: liuming
     * Date: 2021/1/29
     */
    public function getTemplateBindChannels($id = 0){
        $templateModel = new TemplateModel();
        $templateInfo = $templateModel->firstTemplate($id);
        if (empty($templateInfo)){
            return $this->outputFormat([], '模板不存在', MessageHandler::CODE_ERROR);
        }
        $channelModel = new Channel();
        $channelList = $channelModel->findChannelByTemplateId($id);
        if ($channelList->isEmpty()) {
            return $this->outputFormat([], '渠道不存在', MessageHandler::CODE_ERROR);
        }
        $templateInfo->channel_list = $channelList;
        return $this->outputFormat($templateInfo, '成功', 0);
    }
}
