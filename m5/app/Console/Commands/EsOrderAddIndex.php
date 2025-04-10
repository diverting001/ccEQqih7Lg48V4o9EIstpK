<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class EsOrderAddIndex extends Command{
    protected $force = '';
    protected $signature = 'EsOrderAddIndex';
    protected $description = 'ES订单创建索引结构';

    private $_index_name = 'search_orders';

    private $_index_alias = 'search_orders_v1';

    private $_index_type = 'order_data';

    public function handle(){
        set_time_limit(0);

        $this->CreateIndex();
    }

    // 创建索引
    public function CreateIndex(){
        $index_name = $this->_index_name;

        $fields = array(
            'order_id' => array(
                'type' => 'text',
                'fields' => array(
                    'raw' => array(
                        'type' => 'keyword'
                    )
                )
            ),
            'order_status' => array(
                'type' => 'integer'
            ),
            'system_code' => array(
                'type' => 'keyword'
            ),
            'pay_status' => array(
                'type' => 'integer'
            ),
            'ship_status' => array(
                'type' => 'integer'
            ),
            'confirm_status' => array(
                'type' => 'integer'
            ),
            'create_time' => array(
                'type' => 'integer'
            ),
            'update_time' => array(
                'type' => 'integer'
            ),
            'company_id' => array(
                'type' => 'integer'
            ),
            'member_id' => array(
                'type' => 'integer'
            ),
            'order_amount' => array(
                'type' => 'double'
            ),
            'pop_owner_id' => array(
                'type' => 'integer'
            ),
            'create_source' => array(
                'type' => 'keyword'
            ),
            'split' => array(
                'type' => 'integer'
            ),
            'parent_id' => array(
                'type' => 'keyword'
            ),
            'root_pid' => array(
                'type' => 'keyword'
            ),
            'order_category' => array(
                'type' => 'keyword'
            ),
            'extend_info_code' => array(
                'type' => 'keyword'
            ),
            'product_list' => array(
                'type' => 'nested',
                'properties' => array(
                    'bn' => array(
                        'type' => 'keyword'
                    ),
                    'name' => array(
                        'type' => 'text',
                        'analyzer' => 'tokenizer_analyzer',
                        'fields' => array(
                            'raw' => array(
                                'type' => 'keyword'
                            )
                        )
                    ),
                    'nums' => array(
                        'type' => 'integer'
                    ),
                    'price' => array(
                        'type' => 'double'
                    )
                )
            )
        );

        $mapping_set = array(
            'settings' => array(
                'index' => array(
                    'number_of_shards' => 3,
                    'number_of_replicas' => 2,
                    'max_result_window' => 500000
                ),
                'analysis' => array(
                    'analyzer' => array(
                        'search_analyzer' => array(
                            'type' => 'custom',
                            'search_analyzer' => 'ik_smart',
                            'tokenizer' => 'ik_smart',
                            'filter' => array(
                                'my_word_delimiter',
                                'my_lowercase',
                            )
                        ),
                        'tokenizer_analyzer' => array(
                            'type' => 'custom',
                            'search_analyzer' => 'ik_smart',
                            'tokenizer' => 'ik_max_word',
                            'filter' => array(
                                'my_word_delimiter',
                                'my_lowercase',
                                "ngram_filter",
                            )
                        ),
                    ),
                    'filter' => array(
                        'my_word_delimiter' => array(
                            'type' => 'word_delimiter',
                            'preserve_original' => true,
                        ),
                        'my_lowercase' => array(
                            'type' => 'lowercase',
                        ),
                        'edge_ngram_filter' => array(
                            'type' => 'edge_ngram',
                            'min_gram' => 1,
                            'max_gram' => 25,
                        ),
                        'ngram_filter' => array(
                            'type' => 'ngram',
                            'min_gram' => 1,
                            'max_gram' => 16,
                        ),
                    )
                )
            ),
            'aliases' => array(
                $this->_index_alias => new \stdClass()
            ),
            'mappings' => array(
                $this->_index_type => array(
                    'dynamic' => 'false', //忽略新字段
                    'properties' => $fields
                )
            )
        );

        $mapping_set = json_encode($mapping_set);

        $curl = new \Neigou\Curl();

        $url = config('neigou.ESSEARCH_HOST') . ':' . config('neigou.ESSEARCH_PORT') . '/' . $index_name . '/';

        $res = $curl->Put($url, $mapping_set);

        var_dump($res);
        exit();
    }
}
