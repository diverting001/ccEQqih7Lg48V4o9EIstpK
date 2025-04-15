<?php
/**
 * Created by PhpStorm.
 * User: liyunlong
 * Date: 2015/7/7
 * Time: 20:47
 */
namespace ExpressServer;
class express
{
    private $key = 'jwAYgGep2615';
    private $callback = 'http://test.club.neigou.com/ngutil/NGThrift/ExpressClient/callback.php';
    private $link;
    private $msg = 'msg';
    private $table = 'club_express';

    public function __construct($type = '', $data = '')
    {
        $this->link = new mysql();
    }

    /**从快递100获得查询信息
     * @param $company
     * @param $num
     * @return bool|string
     */
    public function getOrderFrom100($company, $num)
    {
        if (empty($company)||empty($num)) {
            return false;
        }
        $data['company'] = $company;
        $data['number'] = $num;
//        $data = json_decode($data, true);
//        $data = array('company' => 'quanfengkuaidi', 'number' => '300141389409', 'from' => '', 'to' => '');
        return $this->curlGetOrder($data);
    }

    /**
     * @param $param
     * @param string $schema
     * @return string
     */
    private function curlGetOrder($param, $schema = 'json')
    {
        $param['parameters'] = array('callbackurl' => $this->callback);
        $param['key'] = $this->key;
        $url = 'http://www.kuaidi100.com/poll';
        $data = 'schema=' . urlencode($schema) . '&param=' . urlencode(json_encode($param));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        return $result;
    }

    public function updateOrderFromKuaidi($json_order)
    {
        $param = json_decode($json_order, true);
        $data = array(
            'status' => $param['lastResult']['state'],
            'data' => serialize($param['lastResult']['data']),
            'updatetime' => time()
        );
        $where = " num='{$param['lastResult']['nu']}' and company='{$param['lastResult']['com']}'";
        $res = $this->link->update($data, $this->table, $where);
        if ($res > 0) {
            return '{"result":"true",	"returnCode":"200","message":"成功"}';
        } else {
            return '{"result":"false",	"returnCode":"500","message":"失败"}';
        }
    }


    public function updateOrder($data, $where)
    {
        $data['updatetime'] = time();
        $res = $this->link->update($data, $this->table, $where);
        if ($res > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**添加快递
     * @param $data
     * @return bool|int
     */
    public function addOrder($data)
    {
        if ($data) {
            $data['addtime'] = time();
            return $this->link->insert($data, $this->table);
        } else {
            return false;
        }
    }

    public function getOrderInfo($company,$num)
    {
        $sql = "select * from " . $this->table . " where company='{$company}' and num='{$num}'";
        $res=$this->link->findOne($sql);
        if ($res) {
            return $res;
        } else {
            return false;
        }
    }
    /**
     * 对已经签收的快递发送消息
     */
    public function sendMSG()
    {
        $sql = 'select * from ' . $this->table . ' where status=3';
        $res = $this->link->findAll($sql);
        if ($res) {
            foreach ($res as $key => $val) {
                if ($val['mobile']) {
                    // 发送短信
                } else {

                }
            }
        }
    }
}
