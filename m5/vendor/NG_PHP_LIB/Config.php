<?php
@include_once '/data/service_router.php';
defined('PSR_MQ_SERVICE_ORDER_HOST') OR define('PSR_MQ_SERVICE_ORDER_HOST', '192.168.66.200');
defined('PSR_MQ_SERVICE_ORDER_PORT') OR define('PSR_MQ_SERVICE_ORDER_PORT', 5672);
defined('PSR_MQ_SERVICE_ORDER_USER') OR define('PSR_MQ_SERVICE_ORDER_USER', 'neigou');
defined('PSR_MQ_SERVICE_ORDER_PASSWORD') OR define('PSR_MQ_SERVICE_ORDER_PASSWORD', 'neigou');

defined('PSR_REDIS_MQ_HOST') OR define('PSR_REDIS_MQ_HOST','192.168.66.200');
defined('PSR_REDIS_CMQ_PORT') OR define('PSR_REDIS_CMQ_PORT',6379);
defined('PSR_REDIS_MQ_PWD') OR define('PSR_REDIS_MQ_PWD','lehopwd123');
define('ECSTORE_OPENAPI_KEY','1a445e15c32bb407ab725e517840f5b91');

defined('PSR_REDIS_WEB_HOST') OR define('PSR_REDIS_WEB_HOST','192.168.66.200');
defined('PSR_REDIS_WEB_PORT') OR define('PSR_REDIS_WEB_PORT','6379');
defined('PSR_REDIS_WEB_PWD') OR define('PSR_REDIS_WEB_PWD','lehopwd123');

defined('PSR_SERVICE_LOG_NAME') OR define('PSR_SERVICE_LOG_NAME', '');
//mq_retry
defined('MQ_RETRY_LEVEL_REAL') OR define('MQ_RETRY_LEVEL_REAL', 'real');
defined('MQ_RETRY_LEVEL_GENERAL') OR define('MQ_RETRY_LEVEL_GENERAL', 'general');
defined('MQ_RETRY_LEVEL_SLOW') OR define('MQ_RETRY_LEVEL_SLOW', 'slow');

defined('MQ_RETRY_EXCHANGE') OR define('MQ_RETRY_EXCHANGE', 'retry');
defined('MQ_RETRY_QUEUE') OR define('MQ_RETRY_QUEUE', 'retry_queue');
defined('MQ_RETRY_ROUTING_KEY') OR define('MQ_RETRY_ROUTING_KEY', 'mq.routing_key_retry');
defined('MQ_RETRY_FINAL_EXCHANGE') OR define('MQ_RETRY_FINAL_EXCHANGE', 'final_v2');
defined('MQ_RETRY_FINAL_ROUTING_KEY') OR define('MQ_RETRY_FINAL_ROUTING_KEY', 'mq.final_routing_key_retry');

/**
 * Created by PhpStorm.
 * User: maojz
 * Date: 17/5/23
 * Time: 16:47
 */
define('YAC_KEY_PREFIX', 'api_client_');
define('SERVICE_LIST_KEY', YAC_KEY_PREFIX.'service_list');
define('SERVICE_STATUS_KEY', YAC_KEY_PREFIX.'service_status');
define('CHECKE_NABLED', false);
define('SERVICE_OK', 'OK');
define('SERVICE_UNAVAILABLE', 'UNAVAILABLE');
define('SERVICE_DEFAULT_BRANCH', 'default');
define('SERVICE_COOKIE_BRANCH_NAME',  'autoci_branch');
define('SERVICE_CURRENT_VERSION', 'current');
define('API_CLIENT_TIIME_OUT',  10);
//define('REDIS_SERVER_HOST', '192.168.66.209');
//define('REDIS_SERVER_PORT', '6379');
//define('REDIS_AUTH','lehopwd123');
//define('REDIS_SERVER_PREFIX', 'apiservice-test-');

//@TODO 服务列表载入
$service_list_path ='/data/ServiceList.json';
$json_file = file_get_contents($service_list_path);
$service_list = json_decode($json_file, true);
define('SERVICE_LIST', serialize($service_list));

define('SERVICE_MQ_HOST', PSR_MQ_SERVICE_ORDER_HOST);
define('SERVICE_MQ_PORT',  PSR_MQ_SERVICE_ORDER_PORT);
define('SERVICE_MQ_USER', PSR_MQ_SERVICE_ORDER_USER);
define('SERVICE_MQ_PASSWORD',  PSR_MQ_SERVICE_ORDER_PASSWORD);

define('SERVICE_MQ_GOODS_HOST', PSR_MQ_SERVICE_GOODS_HOST);
define('SERVICE_MQ_GOODS_PORT',  PSR_MQ_SERVICE_GOODS_PORT);
define('SERVICE_MQ_GOODS_USER', PSR_MQ_SERVICE_GOODS_USER);
define('SERVICE_MQ_GOODS_PASSWORD',  PSR_MQ_SERVICE_GOODS_PASSWORD);
