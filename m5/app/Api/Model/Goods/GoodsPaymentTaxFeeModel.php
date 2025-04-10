<?php

namespace App\Api\Model\Goods;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoodsPaymentTaxFeeModel extends Model
{
    /**
     * @var Connection $_db
     */
    private $_db = null;

    public $table = "sdb_b2c_goods_payment_tax_fee";

    public $connection = 'neigou_store';

    public $timestamps = false;

    private $sdb_b2c_goods_payment_tax_fee = null;
    private $sdb_b2c_goods_payment_tax_fee_product = null;
    private $sdb_b2c_goods_payment_tax_fee_push = null;
    private $sdb_b2c_goods_payment_tax_fee_push_company = null;
    private $sdb_b2c_goods_payment_tax_fee_rate = null;

    /**
     * Cat constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_db = app('db')->connection('neigou_store');
        $this->sdb_b2c_goods_payment_tax_fee = "sdb_b2c_goods_payment_tax_fee";
        $this->sdb_b2c_goods_payment_tax_fee_product = "sdb_b2c_goods_payment_tax_fee_product";
        $this->sdb_b2c_goods_payment_tax_fee_push = "sdb_b2c_goods_payment_tax_fee_push";
        $this->sdb_b2c_goods_payment_tax_fee_push_company = "sdb_b2c_goods_payment_tax_fee_push_company";
        $this->sdb_b2c_goods_payment_tax_fee_rate = "sdb_b2c_goods_payment_tax_fee_rate";
    }

    /**
     * 为指定商品追加服务费税率属性【payment_tax_fee_rate】
     * @param $company_id
     * @param $product_list
     * @return array
     */
    public function getProductPaymentTaxFee($company_id, &$product_list): array
    {
        //已经有服务费比例信息的，不在进行二次添加
        $payment_tax_fee_rate = array_column($product_list, 'payment_tax_fee_rate');
        if ($payment_tax_fee_rate) {
            return $this->returnData();
        }

        $pushCompanyModel = $this->query()->from($this->sdb_b2c_goods_payment_tax_fee_push_company . ' as company');
        $companyRet = $pushCompanyModel->where('company.company_id', '=', $company_id)->select(['company.tax_fee_id'])->get();
        $company_tax_fee_ids = array();
        if ($companyRet) {
            foreach ($companyRet->toArray() as $company_tmp) {
                $company_tax_fee_ids[$company_tmp['tax_fee_id']] = $company_tmp['tax_fee_id'];
            }
        }
        //获取公司绑定的推送信息
        $pushModel = $this->query()->from($this->sdb_b2c_goods_payment_tax_fee_push . ' as push');
        $list = $pushModel->where(['push.status' => 0])->where(function ($query) use ($company_tax_fee_ids) {
            $query->where('push.visible', '=', 1);
            if (!empty($company_tax_fee_ids)) {
                $query->orWhere(function ($query) use ($company_tax_fee_ids) {
                    $query->where('push.visible', '=', 2)->whereIn('push.tax_fee_id', $company_tax_fee_ids);
                });
            };
        })->select(['push.tax_fee_id'])->get();
        if (!$list) {
            return $this->returnData();
        }
        $list = $list->toArray();
        $tax_fee_ids = array();
        foreach ($list as $list_tmp) {
            $tax_fee_ids[$list_tmp['tax_fee_id']] = $list_tmp['tax_fee_id'];
        }
        $product_bns = array_column($product_list, 'product_bn');

        //获取bn对应的分组信息
        $productRet = $this->query()->from($this->sdb_b2c_goods_payment_tax_fee_product)
            ->whereIn('product_bn', $product_bns)->whereIn('tax_fee_id', $tax_fee_ids)->select(['tax_fee_id', 'product_bn'])
            ->get();
        if (!$productRet) {
            return $this->returnData();
        }
        $productRet = $productRet->toArray();
        $taxFeeIds = $productTaxFee = array();
        //获取可用的手续费id，并给bn对应的手续费id
        foreach ($productRet as $productRet_item) {
            $taxFeeIds[] = $productRet_item['tax_fee_id'];
            $productTaxFee[$productRet_item['product_bn']][] = $productRet_item['tax_fee_id'];
        }

        $taxFeeIdsRet = $this->query()->from($this->sdb_b2c_goods_payment_tax_fee)
            ->whereIn('id', $taxFeeIds)->where('is_delete', '=', 0)
            ->select(['id'])->orderByRaw('weight desc,id desc')->get();
        if (!$taxFeeIdsRet) {
            return $this->returnData();
        }
        $taxFeeIds = array();
        //获取可用的手续费id，并给bn对应的手续费id
        foreach ($taxFeeIdsRet as $taxFeeIdsRet_item) {
            $taxFeeIds[] = $taxFeeIdsRet_item['id'];
        }

        //获取组合分组对应的税率
        $rate = $this->query()->from($this->sdb_b2c_goods_payment_tax_fee_rate)
            ->whereIn('tax_fee_id', $taxFeeIds)->select(['tax_fee_id', 'rate_type', 'rate'])
            ->get()->toArray();

        //计算比率按照手续费id分组 ['1' => ['cash_rate'=>0.87,'point_rate'=>0.45]]
        $rate_arr = array();
        foreach ($rate as $item) {
            $rate_type = $item['rate_type'] == 1 ? 'cash_rate' : 'point_rate';
            $rate_arr[$item['tax_fee_id']][$rate_type] = $item['rate'];
        }

        //将手续费比率按照手续费排序，并赋给对应的手续费id，组合出一个 ['1'=>['cash_rate'=>0.87,'point_rate'=>0.45]] 的数组
        $tax_fee_arr = array();
        foreach ($taxFeeIdsRet as $value) {
            if (!isset($rate_arr[$value['id']])) {
                continue;
            }
            $tax_fee_arr[$value['id']] = $rate_arr[$value['id']];
        }

        // 按照排序优先级，判断当前商品的手续费是否可用，并将可用的赋予当前商品
        foreach ($tax_fee_arr as $tax_id => $tax_tmp) {
            foreach ($product_list as &$product) {
                if (!empty($product['payment_tax_fee_rate'])) {
                    continue;
                }
                $product['payment_tax_fee_rate'] = array();
                if (isset($productTaxFee[$product['product_bn']])) {
                    $bn_tax_fee_ids = $productTaxFee[$product['product_bn']];
                    if (in_array($tax_id, $bn_tax_fee_ids)) {
                        $product['payment_tax_fee_rate'] = $tax_tmp;
                    }
                }
            }
        }

        return $this->returnData();
    }

    public function getTaxByIds($taxFeeIds)
    {
        return $this->query()
            ->whereIn('id', $taxFeeIds)->where('is_delete', '=', 0)
            ->select(['id'])->orderByRaw('weight desc,id desc')->get()->toArray();
    }

    public function getTaxInfoByIds($where, $field = ['id'])
    {
        if (empty($where) || empty($field)) {
            return false;
        }
        return $this->query()
            ->where($where)->where('is_delete', '=', 0)
            ->select($field)->first();
    }

    public function addInfo($data)
    {
        if (empty($data)) {
            return false;
        }
        return $this->insertGetId($data);
    }

    public function editInfo($where, $data)
    {
        if (empty($where) || empty($data)) {
            return false;
        }
        return $this->where($where)->update($data);
    }

    public function getListCount($where)
    {
        $model = $this->query();
        if ($where) {
            $model->where($where);
        }
        return $model->where(array('is_delete' => 0))->count();
    }

    public function getList($where, $field = ['id'], $page = 1, $limit = 10)
    {
        $model = $this->query();
        if ($where) {
            $model->where($where);
        }
        $offset = ($page - 1) * $limit;
        return $model->where(array('is_delete' => 0))
            ->with(['taxFeePush' => function ($query) {
                $query->select(['tax_fee_id', DB::raw('visible as show_type')]);
            }, 'taxFeeRate' => function ($query) {
                $query->select(['tax_fee_id', 'rate_type', 'rate']);
            }])
            ->select($field)
            ->offset($offset)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get()->toArray();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function taxFeePush()
    {
        return $this->hasOne(GoodsPaymentTaxFeePushModel::class, 'tax_fee_id', 'id');
    }

    public function taxFeeRate()
    {
        return $this->hasMany(GoodsPaymentTaxFeeRateModel::class, 'tax_fee_id', 'id');
    }

    /**
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function returnData($code = 0, $msg = '', $data = array())
    {
        return array(
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        );
    }
}
