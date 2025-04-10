<?php
/**
 * @author shihuiqian<shihuiqian@neigou.com>
 */

namespace App\Api\V3\Controllers\Account;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Region\Region;
use App\Api\V3\Service\Account\CompanyAddress as CompanyAddressService;
use Illuminate\Http\Request;

class CompanyAddressController extends BaseController
{

    protected $companyAddressService;


    public function __construct()
    {
        $this->companyAddressService = new CompanyAddressService();
    }

    /**
     * 按ID查询收货地址
     *
     * @return array
     */
    public function queryByCompanyId(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->companyAddressService->getByCompanyId($param['company_id']);

        if ($data) {
            $data = $this->isValidAddresses($data->toArray());
            $this->setErrorMsg('success');
            return $this->outputFormat($data);
        }
        $this->setErrorMsg('收货地址不存在');
        return $this->outputFormat($param, 10003);
    }

    /**
     * 按ID查询收货地址
     *
     * @return array
     */
    public function queryByAddressId(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['address_id'])) {
            $this->setErrorMsg('收货地址ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $data = $this->companyAddressService->getByAddressId($param['address_id']);

        if ($data) {
            $data = $this->isValidAddresses([(array)$data])[0];
            $this->setErrorMsg('success');
            return $this->outputFormat($data);
        }
        $this->setErrorMsg('收货地址不存在');
        return $this->outputFormat($param, 10003);
    }

    /**
     * 创建收货地址
     *
     * @return array
     */
    public function create(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['region_id'])) {
            $this->setErrorMsg('地区ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['mainland'])) {
            $this->setErrorMsg('地区不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['address'])) {
            $this->setErrorMsg('详细地址不能为空');
            return $this->outputFormat($param, 10001);
        }

        $id = $this->companyAddressService->create($param);

        if ($id) {
            $this->setErrorMsg('success');
            return $this->outputFormat(array('address_id' => $id));
        }
        $this->setErrorMsg('创建失败');
        return $this->outputFormat($param, 10003);
    }


    /**
     * 更新收货地址
     *
     * @return array
     */
    public function updateByAddressId(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['address_id'])) {
            $this->setErrorMsg('收货地址ID不能为空');
            return $this->outputFormat($param, 10001);
        } elseif (empty($param['data'])) {
            $this->setErrorMsg('更新数据不能为空');
            return $this->outputFormat($param, 10001);
        }

        $result = $this->companyAddressService->updateById($param['address_id'], $param['data']);

        if ($result) {
            $this->setErrorMsg('success');
            return $this->outputFormat(array());
        }
        $this->setErrorMsg('更新失败');
        return $this->outputFormat($param, 10002);
    }

    /**
     * 删除收货地址
     *
     * @return array
     */
    public function deleteByAddressId(Request $request)
    {
        $param = $this->getContentArray($request);

        if (empty($param['address_id'])) {
            $this->setErrorMsg('收货地址ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $result = $this->companyAddressService->deleteById($param['address_id']);

        if ($result) {
            $this->setErrorMsg('success');
            return $this->outputFormat(array());
        }
        $this->setErrorMsg('删除失败');
        return $this->outputFormat($param, 10002);
    }

    /**
     * 验证收货地址是否可用
     *
     * @param array $addresses
     * @return array
     */
    protected function isValidAddresses($addresses)
    {
        if (empty($addresses)) {
            return $addresses;
        }
        $regionModel = new Region();
        $regionIds = array();
        // 先取出地址列表的region id
        foreach ($addresses as $key => &$address) {
            $address = (array)$address;
            $regionIds[] = $address['region_id'];
        }
        // 去重
        $regionIds = array_unique($regionIds);
        $existsRegions = $regionModel->getRegionInfo($regionIds);
        $existsRegionIds = array();
        foreach ($existsRegions as $region) {
            $existsRegionIds[] = $region['region_id'];
        }

        foreach ($addresses as $key => $address) {
            if (isset($address['is_valid'])) {
                continue;
            }
            // 判断地区是否存在
            $addresses[$key]['is_valid'] = isset($address['region_id']) && in_array($address['region_id'],
                    $existsRegionIds);
        }

        return $addresses;
    }

    //获取自提地址列表
    public function getZitiAddressList(Request $request){
        $param = $this->getContentArray($request);

        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $addressList = $this->companyAddressService->getZitiByCompanyId($param['company_id']);
        if (!$addressList->isEmpty()) {
            $this->setErrorMsg('success');
            return $this->outputFormat($addressList);
        }

        $this->setErrorMsg('自提地址不存在');
        return $this->outputFormat($param, 10003);
    }

    //获取自提地址信息
    public function getZitiAddressInfo(Request $request){
        $param = $this->getContentArray($request);

        if (empty($param['address_id'])) {
            $this->setErrorMsg('地址ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $addressList = $this->companyAddressService->getZitiByAddressId($param['address_id']);
        if ($addressList) {
            $this->setErrorMsg('success');
            return $this->outputFormat($addressList);
        }

        $this->setErrorMsg('自提地址不存在');
        return $this->outputFormat($param, 10003);
    }

    //添加自提地址
    public function addZitiAddressInfo(Request $request){
        $param = $this->getContentArray($request);
        if (empty($param['company_id'])) {
            $this->setErrorMsg('公司ID不能为空');
            return $this->outputFormat($param, 10001);
        }
        if (empty($param['province']) || empty($param['city']) || empty($param['county']) || empty($param['town'])) {
            $this->setErrorMsg('区域参数不能为空');
            return $this->outputFormat($param, 10001);
        }
        if (empty($param['name'])) {
            $this->setErrorMsg('名称不能为空');
            return $this->outputFormat($param, 10001);
        }
        if (empty($param['contacts'])) {
            $this->setErrorMsg('联系人不能为空');
            return $this->outputFormat($param, 10001);
        }
        if (empty($param['mobile'])) {
            $this->setErrorMsg('手机号不能为空');
            return $this->outputFormat($param, 10001);
        }
        if (empty($param['address'])) {
            $this->setErrorMsg('详细地址不能为空');
            return $this->outputFormat($param, 10001);
        }

        $id = $this->companyAddressService->createZiti($param);

        if ($id) {
            $this->setErrorMsg('success');
            return $this->outputFormat(array('address_id' => $id));
        }
        $this->setErrorMsg('创建失败');
        return $this->outputFormat($param, 10003);
    }

    //删除自提地址信息
    public function delZitiAddressInfo(Request $request){
        $param = $this->getContentArray($request);

        if (empty($param['address_id'])) {
            $this->setErrorMsg('收货地址ID不能为空');
            return $this->outputFormat($param, 10001);
        }

        $result = $this->companyAddressService->deleteZitiById($param['address_id']);

        if ($result) {
            $this->setErrorMsg('success');
            return $this->outputFormat(array());
        }
        $this->setErrorMsg('删除失败');
        return $this->outputFormat($param, 10002);
    }

    //修改自提地址信息
    public function editZitiAddressInfo(Request $request){
        $param = $this->getContentArray($request);

        if (empty($param['address_id'])) {
            $this->setErrorMsg('收货地址ID不能为空');
            return $this->outputFormat($param, 10001);
        }
        if (empty($param['data'])) {
            $this->setErrorMsg('更新数据不能为空');
            return $this->outputFormat($param, 10001);
        }

        $result = $this->companyAddressService->updateZitiById($param['address_id'], $param['data']);

        if ($result) {
            $this->setErrorMsg('success');
            return $this->outputFormat(array());
        }
        $this->setErrorMsg('更新失败');
        return $this->outputFormat($param, 10002);
    }
}
