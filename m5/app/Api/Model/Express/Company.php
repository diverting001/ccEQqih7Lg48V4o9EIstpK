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
 * @category    Model
 * @author        xupeng
 */
class Company
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

    private static $_companyList = array(
        array('code' => 'EMS', 'kuaidi100_code' => 'ems', 'name' => '中国邮政EMS'),
        array('code' => 'STO', 'kuaidi100_code' => 'shentong', 'name' => '申通快递'),
        array('code' => 'YTO', 'kuaidi100_code' => 'yuantong', 'name' => '圆通速递'),
        array('code' => 'SF', 'kuaidi100_code' => 'shunfeng', 'name' => '顺丰速运'),
        array('code' => 'YUNDA', 'kuaidi100_code' => 'yunda', 'name' => '韵达快递'),
        array('code' => 'ZTO', 'kuaidi100_code' => 'zhongtong', 'name' => '中通速递'),
        array('code' => 'ZJS', 'kuaidi100_code' => 'zhaijisong', 'name' => '宅急送'),
        array('code' => 'TTKDEX', 'kuaidi100_code' => 'tiantian', 'name' => '天天快递'),
        array('code' => 'LBEX', 'kuaidi100_code' => 'longbanwuliu', 'name' => '龙邦快递'),
        array('code' => 'APEX', 'kuaidi100_code' => 'quanyikuaidi', 'name' => '全一快递'),
        array('code' => 'HTKY', 'kuaidi100_code' => 'huitongkuaidi', 'name' => '百世快递'),
        array('code' => 'CNMH', 'kuaidi100_code' => 'minghangkuaidi', 'name' => '民航快递'),
        array('code' => 'AIRFEX', 'kuaidi100_code' => 'yafengsudi', 'name' => '亚风速递'),
        array('code' => 'CNKJ', 'kuaidi100_code' => 'kuaijiesudi', 'name' => '快捷速递'),
        array('code' => 'DDS', 'kuaidi100_code' => 'dsukuaidi', 'name' => 'DDS快递'),
        array('code' => 'HOAU', 'kuaidi100_code' => 'huayuwuliu', 'name' => '华宇物流'),
        array('code' => 'CRE', 'kuaidi100_code' => 'zhongtiewuliu', 'name' => '中铁快运'),
        array('code' => 'FedEx', 'kuaidi100_code' => 'fedex', 'name' => 'FedEx'),
        array('code' => 'UPS', 'kuaidi100_code' => 'ups', 'name' => 'UPS'),
        array('code' => 'DHL', 'kuaidi100_code' => 'dhl', 'name' => 'DHL'),
        array('code' => 'DBL', 'kuaidi100_code' => 'debangwuliu', 'name' => '德邦物流'),
        array('code' => 'QF', 'kuaidi100_code' => 'quanfengkuaidi', 'name' => '全峰快递'),
        array('code' => 'UC', 'kuaidi100_code' => 'youshuwuliu', 'name' => '优速物流'),
        array('code' => 'GTO', 'kuaidi100_code' => 'guotongkuaidi', 'name' => '国通快递'),
        array('code' => 'JBJEMS', 'kuaidi100_code' => 'ems', 'name' => '金宝街EMS'),
        array('code' => 'EMS', 'kuaidi100_code' => 'ems', 'name' => 'EMS经济'),
        array('code' => 'STO', 'kuaidi100_code' => 'shentong', 'name' => '申通E物流'),
        array('code' => 'CJKD', 'kuaidi100_code' => 'chengjisudi', 'name' => '城际快递'),
        array('code' => 'SEKD', 'kuaidi100_code' => 'suer', 'name' => '速尔快递'),
        array('code' => 'YMXUL', 'kuaidi100_code' => 'yamaxunwuliu', 'name' => '亚马逊物流'),
        array('code' => 'RUFENGDA', 'kuaidi100_code' => 'rufengda', 'name' => '如风达'),
        array('code' => 'YHD', 'kuaidi100_code' => 'yihaodian', 'name' => '一号店'),
        array('code' => 'JD', 'kuaidi100_code' => 'jd', 'name' => '京东快递'),
        array('code' => 'AXD', 'kuaidi100_code' => 'exfresh', 'name' => '安鲜达'),
        array('code' => 'SBWL', 'kuaidi100_code' => 'nanjingshengbang', 'name' => '晟邦物流'),
        array('code' => 'JDWL', 'kuaidi100_code' => 'jd', 'name' => '京东物流'),
        array('code' => 'XB', 'kuaidi100_code' => 'xinbangwuliu', 'name' => '新邦物流'),
        array('code' => 'SNWL', 'kuaidi100_code' => 'suning', 'name' => '苏宁物流'),
        array('code' => 'DSWL', 'kuaidi100_code' => 'dsukuaidi', 'name' => 'D速物流'),
        array('code' => 'PJWL', 'kuaidi100_code' => 'pjbest', 'name' => '品骏物流'),
        array('code' => 'HMJKD', 'kuaidi100_code' => 'huangmajia', 'name' => '黄马甲快递'),
        array('code' => 'ANWL', 'kuaidi100_code' => 'annengwuliu', 'name' => '安能物流'),
        array('code' => 'YZBG', 'kuaidi100_code' => 'youzhengguonei', 'name' => '邮政包裹/平邮'),
        array('code' => 'DANNIAO', 'kuaidi100_code' => 'danniao', 'name' => '丹鸟'),
        array('code' => 'NDWL', 'kuaidi100_code' => 'ndwl', 'name' => '南方传媒物流'),
        array('code' => 'PRESENT', 'kuaidi100_code' => 'present', 'name' => '包裹多物流'),
    );

    // --------------------------------------------------------------------

    /**
     * 获取物流公司列表
     *
     * @return  array
     */
    public function getExpressCompanyList()
    {
        return self::$_companyList;
    }

    // --------------------------------------------------------------------

    /**
     * 获取物流公司信息
     *
     * @param   $kuaidi100Code   string   快递100 编码
     * @return  array
     */
    public function getCompanyInfoByKuaidi100Code($kuaidi100Code)
    {
        $return = array();

        foreach (self::$_companyList as $company) {
            if ($company['kuaidi100_code'] == $kuaidi100Code) {
                $return = $company;
                break;
            }
        }

        return $return;
    }
    // --------------------------------------------------------------------

    /**
     * 获取物流公司信息
     *
     * @param   $code   string   快递100 编码
     * @return  array
     */
    public function getCompanyInfoByCode($code)
    {
        $return = array();

        foreach (self::$_companyList as $company) {
            if ($company['code'] == $code) {
                $return = $company;
                break;
            }
        }

        return $return;
    }

}
