<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */
namespace App\Api\Model\Express;

/**
 * 物流 model
 *
 * @package     api
 * @category	Model
 * @author		xupeng
 */
class Express
{
    /**
     * @var Connection
     */
    private $_db;

    /**
     * constructor.
     */
    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_club');
    }

    /**
     * 获取物流详情
     *
     * @param   $company    string      物流公司
     * @param   $num        string      物流单号
     * @return  array
     */
    public function getExpressDetail($company, $num)
    {
        $return = array();

        if (empty($company) OR empty($num))
        {
            return $return;
        }

        $where = [
            'company'   => $company,
            'num'       => $num
        ];

        $return = $this->_db->table('club_express')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 添加物流信息
     *
     * @param   $expressCom     string      物流公司
     * @param   $expressNo      string      物流单号
     * @param   $isKuaidi100    boolean     是否为快递100
     * @param   $status         int         状态
     * @param   $data           mixed       数据
     * @return  boolean
     */
    public function addExpress($expressCom, $expressNo, $isKuaidi100 = true, $status = 0, $data = '', $expressMobile = '')
    {
        if (empty($expressCom) OR empty($expressNo))
        {
            return false;
        }

        $now = time();

        $insertData = array(
            'company'       => $expressCom,
            'num'           => $expressNo,
            'status'        => $status,
            'data'          => is_array($data) ? serialize($data) : $data,
            'addtime'       => $now,
            'updatetime'    => $now,
            'is_kuaidi100'  => $isKuaidi100 ? 1 : 0,
            'mobile'        => $expressMobile
        );

        if ( ! $this->_db->table('club_express')->insert($insertData))
        {
            return false;
        }

        return true;
    }
    // --------------------------------------------------------------------

    /**
     * 添加物流信息
     *
     * @param   $expressCom     string      物流公司
     * @param   $expressNo      string      物流单号
     * @param   $status         int         状态
     * @param   $data           mixed       数据
     * @return  boolean
     */
    public function updateExpress($expressCom, $expressNo, $status, $data)
    {
        if (empty($expressCom) OR empty($expressNo))
        {
            return false;
        }

        $where = array(
            'company'   => $expressCom,
            'num'       => $expressNo,
        );

        $updateData = array(
            'status'        => $status,
            'data'          => is_array($data) ? serialize($data) : $data,
            'updatetime'    => time(),
        );

        if ( ! $this->_db->table('club_express')->where($where)->update($updateData))
        {
            return false;
        }

        return true;
    }

    /**
     * 通过订单表id更新订单用户手机号
     *
     * @param   $expressId          int      express表id
     * @param   $expressMobile      string   用户手机号
     * @param   $status             string   物流状态
     * @return  boolean
     */
    public function updateExpressMobileStatus($expressId, $expressMobile, $status)
    {

        $where = array(
            'id'   => $expressId
        );

        $updateData = array(
            'mobile'        => $expressMobile,
            'updatetime'    => time(),
            'status'        => $status,
        );

        if ( ! $this->_db->table('club_express')->where($where)->update($updateData))
        {
            return false;
        }

        return true;
    }

    /**
     * @param $id
     * @param $data
     * @return bool
     */
    public function updateExpressById($id, $data)
    {
        if (empty($id) or empty($data)) {
            return false;
        }

        $where = array(
            'id' => $id,
        );

        $data['updatetime'] = time();

        if (!$this->_db->table('club_express')->where($where)->update($data)) {
            return false;
        }

        return true;
    }
    /**
     * @param $filed
     * @param $where
     * @param $limit
     * @param $order
     * @return array
     */
    public function getExpressList(
        $filed = '*',
        $where = [],
        $limit = 20,
        $order = 'id asc'
    )
    {
        if (empty($where)) {
            return array();
        }

        if ($filed != '*') {
            $filed = explode(',', $filed);
        }

        $return = $this->_db->table('club_express')->select($filed)->where($where)->limit($limit)->orderByRaw($order)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        return $return;
    }


}
