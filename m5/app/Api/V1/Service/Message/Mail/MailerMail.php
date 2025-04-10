<?php
namespace App\Api\V1\Service\Message\Mail;

use App\Api\V1\Service\Message\MessageHandler;
use App\Api\V3\Service\Account\Member;
use Illuminate\Support\Str;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailerMail extends MessageHandler
{
    public const PLATFORM_NAME = "邮件";

    /**
     * 发送消息接口
     * @param    $receiver
     * @param  object  $messageTemplate
     * @param $item
     * @return array
     */
    protected function send($receiver, $messageTemplate, $item)
    {
        return $this->emailSet($receiver, $messageTemplate, $item);
    }

    protected function batchSend($receiverList, $messageTemplate, $item)
    {
        $result = array();
        foreach ($receiverList as $receiver) {
            $result[$receiver] =  $this->emailSet($receiver, $messageTemplate, $item);
        }
        return $result;
    }

    protected function emailSet($receiver, $messageTemplate, $item)
    {
        // 解析模板数据
        $templateData = $this->templateMatch($messageTemplate, $item['template_param']);

        $mail = new PHPMailer();
        try {
            //Server settings
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['user'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['port'];
            //Recipients
            $mail->setFrom($this->config['from_address'], $this->config['from_name']);
            $mail->addAddress($receiver);

            $mail->isHTML(true);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Subject = $templateData->name;
            $mail->Body = $templateData->template_data;
            // 添加附件
            $pathList = array();
            if (isset($item['extra_param']['attachment']))
            {
                foreach ($item['extra_param']['attachment'] as $attachment)
                {
                    $path = $this->getAttachmentPath($attachment);
                    $pathList[] = $path;
                    $mail->addAttachment($path, $attachment['name'] . '.' . $attachment['format'], 'base64', 'application/octet-stream');
                }
            }

            $res = $mail->send();
            // 删除临时文件
            if (isset($item['extra_param']['attachment']))
            {
                foreach ($pathList as $path)
                {
                    @unlink($path);
                }
            }

            return $this->response(self::CODE_SUCCESS, $res);
        } catch (Exception $e) {
            // 删除临时文件
            if (isset($item['extra_param']['attachment']))
            {
                foreach ($pathList as $path)
                {
                    @unlink($path);
                }
            }
            return  $this->response(self::CODE_ERROR, $mail->ErrorInfo);
        }
    }

    public function templateMatch($messageTemplate, $templateParam)
    {
        if (!empty($messageTemplate->param)) {
            foreach ($messageTemplate->param as $param) {
                $messageTemplate->template_data = Str::replaceFirst(
                    '${'.$param.'}',
                    $templateParam[$param],
                    $messageTemplate->template_data
                );
                $messageTemplate->name = Str::replaceFirst(
                    '${'.$param.'}',
                    $templateParam[$param],
                    $messageTemplate->name
                );
            }
        }
        return $messageTemplate;
    }

    private function getAttachmentPath($attachment)
    {
        // 创建临时文件
        $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR .'attachment';
        if(!is_dir($tmpdir))
        {
            mkdir($tmpdir);
        }
        $tempFilePath = tempnam($tmpdir, 'attachment_');

        switch ($attachment['type'])
        {
            case 1 :
                // url方式
                $fileContents = file_get_contents($attachment['data']);
                file_put_contents($tempFilePath, $fileContents);
                break;
            case 2 :
                // 数组方式
                $this->formatGenerate($attachment['format'], $attachment['data'], $tempFilePath);
                break;
            default :
        }
        return $tempFilePath;
    }

    private function formatGenerate($format, $data, $path)
    {
        switch ($format)
        {
            case 'csv' :
                $this->csvGenerate($data, $path);
                break;
            default :
        }
        return true;
    }

    private function csvGenerate($data, $path)
    {
        $file = fopen($path, 'w');
        foreach ($data as $row)
        {
            foreach ($row as $k => &$v)
            {
                // 过滤逗号
                if (strpos($v, ',') === true)
                {
                    $v = str_replace(',', '，', $v);
                }
                // utf-8转gbk
                $v = iconv('utf-8', 'gbk', $v);
            }
            fputcsv($file, $row);
        }
        fclose($file);
        return true;
    }

    protected function errorMap()
    {
        // TODO: Implement errorMap() method.
    }

    public function checkChildParam($param)
    {
        if ($param['receiver']) {
            foreach ($param['receiver'] as $email) {
                if (!Member::isEmail($email)) {
                    return '邮箱格式错误';
                }
            }
        }
        // 附件参数检测
        if (isset($param['extra_param']['attachment']))
        {
            foreach ($param['extra_param']['attachment'] as $attachment)
            {
                if (empty($attachment['type']) || !in_array($attachment['type'], array(1,2)))
                {
                    return '附件type参数错误';
                }
                if (empty($attachment['name']))
                {
                    return '附件name参数错误';
                }
                if (empty($attachment['format']) || !in_array($attachment['format'], array('csv')))
                {
                    return '附件format参数错误';
                }
                if (empty($attachment['data'][0]))
                {
                    return '附件data参数错误';
                }
            }
        }
        return true;
    }
}
