<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Api\Logic\ExceptionMsg\ExceptionMsg as ExceptionMsgLogic;

class ExceptionMsg extends Command
{
    protected $signature = 'ExceptionMsg {way} {business?}';
    protected $description = '发送订单异常消息';

    private $businesses = [
        'order' => [
            // 异常处理类
            'class' => \App\Api\Logic\ExceptionMsg\OrderException::class,
            'data_from' => [
                'class' => \App\Api\Logic\ExceptionMsg\OrderException::class,
                'method' => 'GetListForException',
                'data_id_field' => 'order_id',
            ],
            'nodes' => [
                'time_out_ship' => [
                    'desc' => '超时未发货',
                    'exception_type' => 3,
                    'in' => [
                        'method' => 'TimeOutShipCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutShipCheckOut',
                        'pars' => [],
                    ],
                ],
                'time_out_complete' => [
                    'desc' => '超时未完成',
                    'exception_type' => 4,
                    'in' => [
                        'method' => 'TimeOutCompleteCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutCompleteCheckOut',
                        'pars' => [],
                    ],
                ],
                'time_out_day_ship' => [
                    'desc' => '超时未发货(24小时)',
                    'exception_type' => 5,
                    'in' => [
                        'method' => 'TimeOutDayShipCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutShipCheckOut',
                        'pars' => [],
                    ],
                ],
                'time_out_five_complete' => [
                    'desc' => '超时未完成（5天）',
                    'exception_type' => 6,
                    'in' => [
                        'method' => 'TimeOutFiveCompleteCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutCompleteCheckOut',
                        'pars' => [],
                    ],
                ],
            ]
        ],
        'after_sale' => [
            'class' => \App\Api\Logic\ExceptionMsg\AfterSaleException::class,
            'data_from' => [
                'class' => \App\Api\Logic\ExceptionMsg\AfterSaleException::class,
                'method' => 'GetListForException',
                'data_id_field' => 'after_sale_bn',
            ],
            'nodes' => [
                'time_out_ship' => [
                    'desc' => '售后超时未寄回',
                    'exception_type' => 10,
                    'in' => [
                        'method' => 'TimeOutShipCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutShipCheckOut',
                        'pars' => [],
                    ],
                ],
                'time_out_putIn' => [
                    'desc' => '售后寄回超时未入库',
                    'exception_type' => 11,
                    'in' => [
                        'method' => 'TimeOutPutInCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutPutInCheckOut',
                        'pars' => [],
                    ],
                ],
                'TimeOutRefund' => [
                    'desc' => '超时未退款',
                    'exception_type' => 13,
                    'in' => [
                        'method' => 'TimeOutRefundCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutRefundCheckOut',
                        'pars' => [],
                    ],
                ],
                'TimeOutApply' => [
                    'desc' => '超时未通过申请',
                    'exception_type' => 14,
                    'in' => [
                        'method' => 'TimeOutApplyPassCheckIn',
                        'pars' => [],
                    ],
                    'out' => [
                        'method' => 'TimeOutApplyPassCheckOut',
                        'pars' => [],
                    ],
                ]
            ]
        ]
    ];

    public function handle()
    {
        $way = $this->argument('way');
        $business = $this->argument('business');
        if ($way === 'in' && !empty($business)) {
            $this->checkIn($business);
        } elseif ($way === 'out') {
            $this->checkOut();
        }
    }

    public function checkIn($business)
    {
        // 数据判断、处理类
        $exception_logic = new $this->businesses[$business]['class'];
        // 数据来源类
        $data_model = new $this->businesses[$business]['data_from']['class'];
        $page = 1;
        $page_size = 1000;
        // 获取待判断数据
        $data_method = $this->businesses[$business]['data_from']['method'];
        $conf = ExceptionMsgLogic::getConf($business);
        while (1) {
            echo '************************page:' . $page . '***********************', PHP_EOL;
            $list = $data_model->$data_method($page, $page_size);
//            print_r($list);
//            die;
            $page++;
            if (empty($list)) {
                echo 'over';
                break;
            }
//            print_r(app('api_db')->getQueryLog());
//            print_r($list);            die;
//            print_r($conf);            die;
            foreach ($list as $item) {
                $data_id = $item[$this->businesses[$business]['data_from']['data_id_field']];
                echo $business . '：' . $data_id . '：', PHP_EOL;
                $is_allow = true;
                foreach ($conf['allow'] as $check_field => $allow_values) {
                    if (!in_array($item[$check_field], $allow_values)) {
                        $is_allow = false;
                        echo '数据不在' . $check_field . '允许名单,跳过', PHP_EOL;
                    }
                }
                if ($is_allow === false) {
                    continue;
                }
                $is_block = false;
                foreach ($conf['block'] as $check_field => $allow_values) {
                    if (in_array($item[$check_field], $allow_values)) {
                        $is_block = true;
                        echo '数据在' . $check_field . '禁止名单,跳过', PHP_EOL;
                    }
                }
                if ($is_block === true) {
                    continue;
                }
                // 以数据匹配各节点是否成立，并处理
                foreach ($this->businesses[$business]['nodes'] as $node) {
                    $where = [
                        ['business', $business],
                        ['data_id', $data_id],
                        ['type', $node['exception_type']],
                    ];
                    $check_method = $node['in']['method'];
                    if (!empty(ExceptionMsgLogic::getInfo($where))) {
                        echo '=====(' . $node['desc'] . ')' . $check_method . ':已存在，跳过', PHP_EOL;
                        continue;
                    }
                    echo '=====(' . $node['desc'] . ')' . $check_method . ':';
                    if ($exception_logic::$check_method($item) === true) {
                        $exception_data = [
                            'business' => $business,
                            'data_id' => $data_id,
                            'wms_code' => $item['wms_code'],
//                        'wms_msg' => $node['desc'],
                            'type' => $node['exception_type'],
                            'data_create_time' => $item['create_time'],
                            'pop_owner_id' => $item['pop_owner_id'],
                            'company_id' => $item['company_id'],
                        ];
                        ExceptionMsgLogic::addException($exception_data);
                        echo '符合条件，新增', PHP_EOL;
                    } else {
                        echo '不符合条件', PHP_EOL;
                    }
                }
            }
        }
    }

    public function CheckOut()
    {
        $max_id = 0;
        $business_exception_logic_mapping = [];
        $exception_type_node_mapping = [];
        $type_arr = [];
        foreach ($this->businesses as $business => $val) {
            foreach ($val['nodes'] as $node) {
                $exception_type_node_mapping[$node['exception_type']] = $node;
                $type_arr[] = $node['exception_type'];
            }
        }
        while (1) {
            $msg_list = ExceptionMsgLogic::getList([
                ['status', '0'],
                ['id', '>', $max_id],
                [function ($query) use ($type_arr) {
                    $query->whereIn('type', $type_arr);
                }]
            ]);
            if (empty($msg_list)) {
                echo 'over';
                break;
            }
            foreach ($msg_list as $msg) {
                $max_id = $msg['id'];
                // 数据判断、处理类
                if (!isset($business_exception_logic_mapping[$msg['business']])) {
                    if (empty($this->businesses[$msg['business']]['class']) || !class_exists($this->businesses[$msg['business']]['class'])) {
                        echo '业务未注册或类不存在:' . $msg['business'], PHP_EOL;
                        continue;
                    }
                    $business_exception_logic_mapping[$msg['business']] = new $this->businesses[$msg['business']]['class'];
                }
                $exception_logic = $business_exception_logic_mapping[$msg['business']];
                if (!isset($exception_type_node_mapping[$msg['type']])) {
                    echo '节点未注册:' . $msg['type'], PHP_EOL;
                    continue;
                }
                $node = $exception_type_node_mapping[$msg['type']];
                $method = $node['out']['method'];
                if (!method_exists($exception_logic, $method)) {
                    echo '检出方法不存在:' . $msg['type'], PHP_EOL;
                    continue;
                }
                echo $msg['business'] . ':' . $msg['data_id'], ':(' . $node['desc'] . ')' . $method . ':';
                if ($exception_logic->$method($msg) === true) {
                    echo '符合移除条件，更新status=1', PHP_EOL;
                    ExceptionMsgLogic::update(
                        [['id', $msg['id']]],
                        ['status' => 1, 'operator' => 'system', 'operator_time' => time()]
                    );
                } else {
                    echo '不符合移除条件', PHP_EOL;
                }
            }
        }
    }
}
