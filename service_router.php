<?php

include "service_config.php";

# 新增
define('PSR_WEB_NEIGOU_MVP', 'https://mvp-q.juyoufuli.com');
define('PSR_WEB_DOMAIN', 'q.juyoufuli.com');
define('PSR_WEB_NEIGOU_DOMAIN', 'q.juyoufuli.com');
define('PSR_WEB_NEIGOU_MIS', 'https://mis-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_MIS_DOMAIN', 'mis-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_LIFE', 'https://life-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_LIFE_DOMAIN', 'life-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_SHOP', 'https://shop-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_HD_DOMAIN', 'hd-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_SALYUT', 'https://salyut-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_SALYUT_DOMAIN', 'salyut-q.juyoufuli.com');
define('PSR_CART_V2_DOMAIN', 'vue-q.juyoufuli.com');
define('PSR_CART_V2_MOBILE_DOMAIN', 'vue_mobile-q.juyoufuli.com');
define('PSR_CART_V3_DOMAIN', 'vue_v3-q.juyoufuli.com');
define('PSR_CART_V3_MOBILE_DOMAIN', 'vue_mobile_v3-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_SERVICE', 'http://service-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_SERVICE_DOMAIN', 'service-q.juyoufuli.com');
define('PSR_IMPORT_FILE_DATA_PATH', '/nfs_share/file_server/neigou_clubs/files/');
define('PSR_EXPORT_FILE_DATA_PATH', '/nfs_share/file_server/neigou_clubs/files/');
define('PSR_IMAGE_FILE_SAVE_PATH', '/nfs_share/file_server/neigou_clubs/Uploads/');
define('PSR_NG_RPC_SHARE_FILES', '/nfs_share/file_server/neigou_clubs/files/');

define('PSR_GCAS_NEIGOU_PARTNER_ID', 'neigou');
define('PSR_GCAS_NEIGOU_KEY', '84ea2f95e65e290681f848e8de3b5aca');
define('PSR_GCAS_DIANDI_PARTNER_ID', 'ddguanhuai');
define('PSR_GCAS_DIANDI_KEY', '288293253d82aea32cdd7e7f57b98864');
define('PSR_GCAS_PARTNER_ID', 'juyoufuli');
define('PSR_GCAS_KEY', 'aznuu9s9uf9kanswxwjgsgkycc513pvp');

define('PSR_AGH_STATIC_URL','//fecdn-q.juyoufuli.com/juyoufuli-b-fe3-static/static/assets/js/agho-aa3abd2a-usx8y1yqvxd-1671772124054.js');
define('PSR_NEIGOU_COOKIE_DOMAIN','juyoufuli.com');

define('PSR_NGRPC_STATUS_REPORT_URL','http://rpcreport-q.juyoufuli.com/JobStatusReportHttp.php');

#用来定义所有服务基地址
#Puppet Service Router = PSR

#Aliyun 1.0


define('PSR_WEB_NEIGOU_CLUB_DOMAIN', 'club-q.juyoufuli.com');
define('PSR_WEB_DIANDI_CLUB_DOMAIN', 'club-q.juyoufuli.com');

#PROXY web.neigout02
//define('PSR_NEIGOU_HTTP_PROXY', 'http://10.27.77.60:3128');
define('PSR_NEIGOU_HTTP_PROXY', '');

# name: PSR_WEB_<DOMAIN>_<SUB_DOMAIN>
define('PSR_WEB_NEIGOU', 'https://q.juyoufuli.com');
define('PSR_WEB_NEIGOU_MALL', 'https://mall-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_MALL_DOMAIN', 'mall-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_SHOP_DOMAIN', 'shop-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_HD', 'https://hd-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_CLUB', 'https://club-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_OPENAPI_SAFE', 'https://openapi-q.juyoufuli.com');
define('PSR_WEB_NEIGOU_OPENAPI', 'https://openapi-q.juyoufuli.com');

define('PSR_WEB_NEIGOU_OCS_DOMAIN', 'ocs.neigou.com');
define('PSR_WEB_DIANDI_CLUB', 'https://club-q.juyoufuli.com');
define('PSR_WEB_DIANDI_AGH_B', 'https://apps-q.juyoufuli.com');
define('PSR_WEB_DIANDI_AGH_API', 'https://api-q.juyoufuli.com');
define('PSR_WEB_DIANDI_AGH_APPS', 'https://apps-q.juyoufuli.com');
define('PSR_WEB_DIANDI_AGH_APPS_DOMAIN', 'apps-q.juyoufuli.com');
define('PSR_WEB_DIANDI_YUANGONG_PORT', 'https://m.club-q.juyoufuli.com');
define('PSR_WEB_DIANDI_YUANGONG_DOMAIN', 'm.club-q.juyoufuli.com');
define('PSR_PC_DIANDI_YUANGONG_PORT', 'https://club-q.juyoufuli.com');
define('PSR_PC_DIANDI_YUANGONG_DOMAIN', 'club-q.juyoufuli.com');
define('PSR_WEB_DIANDI_COOKIE_DOMAIN', 'juyoufuli.com');
define('PSR_WEB_DIANDI_AGH_API_PROJECT','/api-app');

#CDN
define('PSR_CDN_WEB_NEIGOU_WWW', 'https://cdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_WWW_DOMAIN', 'cdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_MALL', 'https://mall-q.juyoufuli.com');
define('PSR_CDN_WEB_DIANDI_CLUB', 'https://clubcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_CLUB', 'https://clubcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_SALYUT_DOMAIN', 'salyutcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_DIANDI_CLUB_DOMAIN', 'clubcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_CLUB_DOMAIN', 'clubcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_CLUBCDN_DOMAIN', 'clubcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_MALL_DOMAIN', 'mallcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_LIFE_DOMAIN', 'lifecdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_SHOP_DOMAIN', 'shopcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_HD_DOMAIN', 'hdcdn-q.juyoufuli.com');
define('PSR_CDN_WEB_NEIGOU_FE', 'fecdn-q.juyoufuli.com');

#REDIS FOR WEB Session
define('PSR_REDIS_WEB_HOST', '192.168.21.25');
define('PSR_REDIS_WEB_PORT', 6379);
define('PSR_REDIS_WEB_PWD', 'anlyZWRpcwo=');

define('PSR_REDIS_SESSION_HOST', '192.168.21.25');
define('PSR_REDIS_SESSION_PORT', 6379);
define('PSR_REDIS_SESSION_PWD', 'anlyZWRpcwo=');

define('PSR_REDIS_TMP_CACHE_HOST', '192.168.21.28');
define('PSR_REDIS_TMP_CACHE_PORT', 6379);
define('PSR_REDIS_TMP_CACHE_PWD', 'anlyZWRpcwo=');

#REDIS FOR STORE PRICE
define('PSR_REDIS_THIRD_WEB_HOST', '192.168.21.32');
define('PSR_REDIS_THIRD_WEB_PORT', 6379);
define('PSR_REDIS_THIRD_WEB_PWD', 'anlyZWRpcwo=');

#REDIS CLUSTER (FOR GOODS_PRICE) 商品价格
define('PSR_REDIS_GOODS_PRICE_HOST', '192.168.21.32');
define('PSR_REDIS_GOODS_PRICE_PORT', 6379);
define('PSR_REDIS_GOODS_PRICE_PWD', 'anlyZWRpcwo=');

#REDIS FOR  CACHE (KVSTORE CACHE)
define('PSR_REDIS_CACHE_HOST', '192.168.21.28');
define('PSR_REDIS_CACHE_PORT', 6379);
define('PSR_REDIS_CACHE_PWD', 'anlyZWRpcwo=');

#REDIS FOR  MQ (MQ type(list))
define('PSR_REDIS_MQ_HOST', '192.168.21.32');
define('PSR_REDIS_CMQ_PORT', 6379);
define('PSR_REDIS_MQ_PWD', 'anlyZWRpcwo=');

define('PSR_MQ_SERVICE_GOODS_HOST', '192.168.21.47');
define('PSR_MQ_SERVICE_GOODS_PORT', 5673);
define('PSR_MQ_SERVICE_GOODS_USER', 'neigou');
define('PSR_MQ_SERVICE_GOODS_PASSWORD', 'neigou');

define('PSR_BEANSTALKD_RPC_HOST', 'beanstalkd-q.juyoufuli.com');
define('PSR_BEANSTALKD_RPC_PORT', 11300);

define('PSR_RPC_WORKQUEUE_SCRIPT_DIR', '/data/NG_RPC_Scripts/WorkQueueScripts');

define('PSR_DB_MASTER_HOST', '192.168.22.5');
define('PSR_DB_MASTER_PORT', 3306);

define('PSR_DB_MASTER_USER_DEV', 'neigou_store'); #READ ONLY
define('PSR_DB_MASTER_PASSWORD_DEV', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_MASTER_USER_WIZARD', 'neigou_store');
define('PSR_DB_MASTER_PASSWORD_WIZARD', 'bYTqmcz5MLQ0zdMe'); #FOR WIZARD

# name: PSR_DB_<DB_NAME>_<HOST|PORT|USER|PASSWORD>
# 		PSR_DB_<DB_NAME>_<USER|PASSWORD>_<WEB|THRIFT...>_<SYSNAME>

# ECSTORE
define('PSR_DB_ECSTORE_NAME', 'neigou_store');
define('PSR_DB_ECSTORE_HOST', '192.168.22.5');
define('PSR_DB_ECSTORE_PORT', 3306);
define('PSR_DB_ECSTORE_USER', 'neigou_store');
define('PSR_DB_ECSTORE_PASSWORD', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_ECSTORE_SLAVE_NAME', 'neigou_store');
define('PSR_DB_ECSTORE_SLAVE_HOST', '192.168.22.5');
define('PSR_DB_ECSTORE_SLAVE_PORT', 3306);
define('PSR_DB_ECSTORE_SLAVE_USER', 'neigou_store');
define('PSR_DB_ECSTORE_SLAVE_PASSWORD', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_ECSTORE_USER_WEB_MALL', 'neigou_store');
define('PSR_DB_ECSTORE_PASSWORD_WEB_MALL', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_ECSTORE_USER_WEB_CLUB', 'neigou_store');
define('PSR_DB_ECSTORE_PASSWORD_WEB_CLUB', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_ECSTORE_USER_WEB_HD', 'neigou_store');
define('PSR_DB_ECSTORE_PASSWORD_WEB_HD', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_ECSTORE_USER_THRIFT', 'neigou_store');
define('PSR_DB_ECSTORE_PASSWORD_THRIFT', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_ECSTORE_USER_NGRPC', 'neigou_store');
define('PSR_DB_ECSTORE_PASSWORD_NGRPC', 'bYTqmcz5MLQ0zdMe');

#CLUB
define('PSR_DB_CLUB_NAME', 'neigou_club');
define('PSR_DB_CLUB_HOST', '192.168.22.5');
define('PSR_DB_CLUB_PORT', 3306);

define('PSR_DB_CLUB_USER', 'neigou_club');
define('PSR_DB_CLUB_PASSWORD', 'BZGD3bTbpzYYGZZx');

define('PSR_DB_CLUB_USER_WEB_ECSTORE', 'neigou_club');
define('PSR_DB_CLUB_PASSWORD_WEB_ECSTORE', 'BZGD3bTbpzYYGZZx');

define('PSR_DB_CLUB_USER_WEB_MALL', 'neigou_club');
define('PSR_DB_CLUB_PASSWORD_WEB_MALL', 'BZGD3bTbpzYYGZZx');

define('PSR_DB_CLUB_USER_THRIFT', 'neigou_club');
define('PSR_DB_CLUB_PASSWORD_THRIFT', 'BZGD3bTbpzYYGZZx');

define('PSR_DB_CLUB_USER_NGRPC', 'neigou_club');
define('PSR_DB_CLUB_PASSWORD_NGRPC', 'BZGD3bTbpzYYGZZx');

#HD
define('PSR_DB_HD_NAME', 'neigou_hd');
define('PSR_DB_HD_HOST', '192.168.22.5');
define('PSR_DB_HD_PORT', 3306);

define('PSR_DB_HD_USER', 'neigou_hd');
define('PSR_DB_HD_PASSWORD', 'MxabSK7aZplrT21R');

define('PSR_DB_HD_USER_WEB_CLUB', 'neigou_hd');
define('PSR_DB_HD_PASSWORD_WEB_CLUB', 'MxabSK7aZplrT21R');

define('PSR_DB_HD_USER_WEB_ECSTORE', 'neigou_hd');
define('PSR_DB_HD_PASSWORD_WEB_ECSTORE', 'MxabSK7aZplrT21R');

#SALYUT
define('PSR_DB_SALYUT_NAME', 'neigou_salyut');
define('PSR_DB_SALYUT_HOST', '192.168.20.14');
define('PSR_DB_SALYUT_PORT', 3306);
define('PSR_DB_SALYUT_USER', 'neigou_salyut');
define('PSR_DB_SALYUT_PASSWORD', 'ZHGD655Ts0XgvZPM');

#FINANCE
define('PSR_DB_FINANCE_NAME', 'neigou_finance');
define('PSR_DB_FINANCE_HOST', '192.168.22.5');
define('PSR_DB_FINANCE_PORT', 3306);
define('PSR_DB_FINANCE_USER', 'neigou_store');
define('PSR_DB_FINANCE_PASSWORD', 'bYTqmcz5MLQ0zdMe');


#MVP
define('PSR_DB_MVP_NAME', 'neigou_mvp');
define('PSR_DB_MVP_HOST', '192.168.22.5');
define('PSR_DB_MVP_PORT', 3306);
define('PSR_DB_MVP_USER', 'neigou_mvp');
define('PSR_DB_MVP_PASSWORD', 'MAYO3zKQtJzQK80T');

#OPENAPI
define('PSR_DB_OPENAPI_NAME', 'neigou_openapi');
define('PSR_DB_OPENAPI_HOST', '192.168.22.5');
define('PSR_DB_OPENAPI_PORT', 3306);
define('PSR_DB_OPENAPI_USER', 'neigou_openapi');
define('PSR_DB_OPENAPI_PASSWORD', 'QV1Y97opz9aaU6Gq');

define('PSR_DB_OPENAPI_USER_NGRPC', 'neigou_openapi');
define('PSR_DB_OPENAPI_PASSWORD_NGRPC', 'QV1Y97opz9aaU6Gq');

#IXIANGJU
define('PSR_DB_IXIANGJU_NAME', 'neigou_building');
define('PSR_DB_IXIANGJU_HOST', '192.168.22.5');
define('PSR_DB_IXIANGJU_PORT', 3306);
define('PSR_DB_IXIANGJU_USER', 'neigou_store');
define('PSR_DB_IXIANGJU_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#TENCENT
define('PSR_DB_TENCENT_NAME', 'neigou_tencent');
define('PSR_DB_TENCENT_HOST', '192.168.22.5');
define('PSR_DB_TENCENT_PORT', 3306);
define('PSR_DB_TENCENT_USER', 'neigou_store');
define('PSR_DB_TENCENT_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#OFOBBS
define('PSR_DB_OFOBBS_NAME', 'neigou_ofobbs');
define('PSR_DB_OFOBBS_HOST', '192.168.22.5');
define('PSR_DB_OFOBBS_PORT', 3306);
define('PSR_DB_OFOBBS_USER', 'neigou_store');
define('PSR_DB_OFOBBS_PASSWORD', 'bYTqmcz5MLQ0zdMe');

define('PSR_DB_OFOBBS_UCENTER_NAME', 'neigou_ucenter');
define('PSR_DB_OFOBBS_UCENTER_HOST', '192.168.22.5');
define('PSR_DB_OFOBBS_UCENTER_PORT', 3306);
define('PSR_DB_OFOBBS_UCENTER_USER', 'neigou_store');
define('PSR_DB_OFOBBS_UCENTER_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#CPS
define('PSR_DB_CPS_NAME', 'neigou_cps');
define('PSR_DB_CPS_HOST', '192.168.22.5');
define('PSR_DB_CPS_PORT', 3306);
define('PSR_DB_CPS_USER', 'neigou_store');
define('PSR_DB_CPS_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#fuli
define('PSR_DB_WELFARE_NAME', 'welfare');
define('PSR_DB_WELFARE_HOST', '192.168.22.5');
define('PSR_DB_WELFARE_PORT', 3306);

define('PSR_DB_WELFARE_USER', 'neigou_store');
define('PSR_DB_WELFARE_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#wizard
define('PSR_DB_WIZARD_NAME', 'neigou_wizard');
define('PSR_DB_WIZARD_HOST', '192.168.22.5');
define('PSR_DB_WIZARD_PORT', 3306);

define('PSR_DB_WIZARD_USER', 'neigou_store');
define('PSR_DB_WIZARD_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#internalmisc db
define('PSR_DB_WIZARD_STAT_NAME', 'neigou_statistics');
define('PSR_DB_WIZARD_STAT_HOST', '192.168.22.5');
define('PSR_DB_WIZARD_STAT_PORT', 3306);

define('PSR_DB_WIZARD_STAT_USER', 'neigou_store');
define('PSR_DB_WIZARD_STAT_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#ocs
define('PSR_DB_OCS_NAME', 'ocs');
define('PSR_DB_OCS_HOST', '192.168.20.14');
define('PSR_DB_OCS_PORT', 3306);
define('PSR_DB_OCS_USER', 'neigou_ocs');
define('PSR_DB_OCS_PASSWORD', '3EKFEjVvfaN2GSO1');

#monitor
define('PSR_DB_MONITOR_NAME', 'zabbix');
define('PSR_DB_MONITOR_HOST', '192.168.22.5');
define('PSR_DB_MONITOR_PORT', 3306);
define('PSR_DB_MONITOR_USER', 'neigou_store');
define('PSR_DB_MONITOR_PASSWORD', 'bYTqmcz5MLQ0zdMe');

#service-stock
define('PSR_DB_STOCK_NAME', 'neigou_service');
define('PSR_DB_STOCK_HOST', '192.168.20.8');
define('PSR_DB_STOCK_PORT', 3306);
define('PSR_DB_STOCK_USER', 'neigou_service');
define('PSR_DB_STOCK_PASSWORD', 'Ds8nm2OzFfgPQPCb');

#service-stock 从库
define('PSR_DB_STOCK_SLAVE_NAME', 'neigou_service');
define('PSR_DB_STOCK_SLAVE_HOST', '192.168.20.8');
define('PSR_DB_STOCK_SLAVE_PORT', 3306);
define('PSR_DB_STOCK_SLAVE_USER', 'neigou_service');
define('PSR_DB_STOCK_SLAVE_PASSWORD', 'Ds8nm2OzFfgPQPCb');

#RPC
define('PSR_DB_RPC_NAME', 'neigou_rpc');
define('PSR_DB_RPC_HOST', '192.168.22.5');
define('PSR_DB_RPC_USER', 'neigou_store');
define('PSR_DB_RPC_PASSWORD', 'bYTqmcz5MLQ0zdMe');
define('PSR_DB_HD_USER_NGRPC', 'neigou_store');
define('PSR_DB_HD_PASSWORD_NGRPC', 'bYTqmcz5MLQ0zdMe');

# name : PSR_THRIFT_<SYSNAME>_<HOST|PORT>
define('PSR_THRIFT_SMS_HOST', 'rpc-q.juyoufuli.com');
define('PSR_THRIFT_SMS_PORT', 9090);
define('PSR_THRIFT_EXPRESS_HOST', 'rpc-q.juyoufuli.com');
define('PSR_THRIFT_EXPRESS_PORT', 9091);
define('PSR_THRIFT_MAIL_HOST', 'rpc-q.juyoufuli.com');
define('PSR_THRIFT_MAIL_PORT', 9092);
define('PSR_THRIFT_VOUCHER_HOST', 'rpc-q.juyoufuli.com');
define('PSR_THRIFT_VOUCHER_PORT', 9093);
define('PSR_THRIFT_CENTER_HOST', 'rpc-q.juyoufuli.com');
define('PSR_THRIFT_CENTER_PORT', 9094);
define('PSR_THRIFT_NGRPC_HOST', 'rpc-q.juyoufuli.com');
define('PSR_THRIFT_NGRPC_PORT', 9095);

define('PSR_OPENAPI_OAUTH2_CLIENT_ID', '25b03799b0c4e39394b90a2fdfbd5c69');
define('PSR_OPENAPI_OAUTH2_CLIENT_SECRET', 'c29d44fae6d0be8960d8098c18d13aa42baf8abe6d693aa445020ff435a246f0');

define('PSR_NFS_SHARE_PATH', '/nfs_share/file_server');
define('PSR_THIRDPARTY_WEIXIN_CERT_PATH', '/nfs_share/share/cert/wxcert');
define('PSR_QIYE_WEIXIN_CERT_PATH', '/nfs_share/share/cert/qiyewxcert');
define('PSR_PC_WEIXIN_CERT_PATH', '/nfs_share/share/cert/pcwxcert');
define('PSR_ECSTORE_CERT_PATH', '/nfs_share/share/cert/neigou_store');

#ElasticSearch Search Cluster
define('PSR_ELK_DOMAIN', 'http://elasticsearch-q.juyoufuli.com');
define('PSR_ESSEARCH_HOST', 'http://elasticsearch-q.juyoufuli.com');
define('PSR_ESSEARCH_PORT', 9200);
define('PSR_ESSEARCH_NEW_PORT', 9200);

# 爬虫spider
define('PSR_SPIDER_SERVER_URL', 'http://internal-spider:8095/');

#列表接口缓存开关
define('PSR_API_GOODS_CACHE', false);

# CAS
define('PSR_GCAS_GETWAY', 'https://cas-q.juyoufuli.com');
define('PSR_GCAS_GATEWAY_DD', 'https://cas-q.juyoufuli.com');
define('PSR_CAS_GETWAY', 'https://cas-q.juyoufuli.com');
define('PSR_CAS_GETWAY_NEW', 'https://cas-q.juyoufuli.com');
#Rabbitmq
define('PSR_MQ_SERVICE_ORDER_HOST', 'rabbitmq-q.juyoufuli.com');
define('PSR_MQ_SERVICE_ORDER_PORT', 5672);
define('PSR_MQ_SERVICE_ORDER_USER', 'neigou');
define('PSR_MQ_SERVICE_ORDER_PASSWORD', 'neigou');


// 对象存储参数
define('PSR_IMAGE_DRIVER', 'Oss');


//关闭根据product_id或product_进行搜索
define('PSR_CLOSE_COMPANY_PRDUCT_KEY_SEARCH',true);

//聚优福利
define('PSR_JUYOUFULI_APPID','806568');
define('PSR_JUYOUFULI_APPSECRET_KEY','efa559a1-tba0-4f08-bd37-50894c21a6ae');
define('PSR_JUYOUFULI_SERVICE_URL','http://open.juyoufuli.com/solution');

define('PSR_MEMBER_CARD_CENTER_SHOW','true'); //个人中心显示卡券中心 true显示, false不显示
define('PSR_JUYOUFULI_DEFAULT_RULE_BN','201910101554523045'); //聚优福利, 默认添加一个规则id

define('PSR_JUYOU_MOVIE_DOMIN' , 'https://api.juyoufuli.com');
define('PSR_UBT_HOST' , '');
define('PSR_CKFINDER_FILE_SAVE_PATH' , '/nfs_share/file_server/neigou_clubs/Uploads/life/announce_pic/ckfinder');

define('PSR_FULI_DOMAIN', 'https://api.juyoufuli.com');
define('PSR_FULI_SALT', 'a8df4b6efeb72c1ef321aa901a1f17fb');

define('PSR_SAAS_M_DOMAIN', 'saas-m.juyoufuli.com');
define('PSR_API_GATEWAY_DOMAIN', 'api.juyoufuli.com');

define('PSR_CONFIG_SALYUT_TMCS_APP_KEY', 'zdhsoaa4duuao959z8yt');
define('PSR_CONFIG_SALYUT_TMCS_APP_SECRET', 'd38cf4554a9bf9fb492e3ae32d716b72');



define('PSR_ESTORE_FULUFUXI_DOMAIN', 'https://www.fulufuxi.com');

define('PSR_ELK_HOST', 'http://log-q.juyoufuli.com:9200');

define('PSR_JSAPI_WECHAT_SECRET', '69ea8ae8fcfd64940b90148d3531afa4');
define('PSR_JSAPI_WECHAT_APPID', 'wx91653f32e365ccab');
define('PSR_JSAPI_WXWORK_CORPID', 'wwf66c64f4f3790965');
define('PSR_JSAPI_WXWORK_CORPSECRET', 'Wtuk2-6IvOUqviXhV8x1KLY7v27J4jWM8xj_iE5xiOA');
define('PSR_JSAPI_DD_APPKEY', 'dingaj1sy46qvgffw09i');
define('PSR_JSAPI_DD_APPSECRET', '8NT6AV_3qhMPjGO1TZJkOtkX9VC-tU1yOIcIhc5fQjCQx2FFVAU2eQEhftIc4xuo');
define('PSR_JSAPI_DD_AGENTID', '1602746307');
define('PSR_JSAPI_DD_CORPID', 'ding07879954eaf45f80bc961a6cb783455b');

// 京东店铺ID-聚优
define('PSR_PRICE_PROTECTION_POP_OWNER_IDS', '103,25229,266');


## 是否启用备用库存
define('NEIGOU_INDEX_IS_ENABLE_SPARE_STOCK',true);//index项目
define('NEIGOU_STORE_IS_ENABLE_SPARE_STOCK',true);//store项目
define('NEIGOU_CLUBS_IS_ENABLE_SPARE_STOCK',true);//life和club项目

#增加钉钉开放接口地址
define('PSR_ALI_DING_TALK_APIURL', 'https://oapi.dingtalk.com');

#反向地理编码接口使用哪一个三方平台,目前指定百度
define('PSR_REVERSE_GEOCODING_PLATFORM' , 'baidu');
define('PSR_ADDRESS_SUGGESTION_PLATFORM' , 'baidu');

#乐福卡对接
define('PSR_LEFU_APPID','686092');
define('PSR_LEFU_APPSECRET_KEY','f62b7055-182e-435f-8077-584fb680e2f9');

#业财数据库
define('PSR_DB_YC_NAME', 'neigou_yc');
define('PSR_DB_YC_HOST', '192.168.20.8');
define('PSR_DB_YC_PORT', 3306);
define('PSR_DB_YC_USER', 'neigou_yc');
define('PSR_DB_YC_PASSWORD', 'ZkvEcQY*$z&');

## 依图麦极客对应的配置项
define('PSR_CONFIG_SALYUT_YTM_APP_KEY','FX24wph0ALi42IHqau');
define('PSR_CONFIG_SALYUT_YTM_APP_SECRET','FPF8kl1xzrgt22uPesdr5AiuJH3lE7yM');
define('PSR_SALYUT_YTM_REQUEST_URL','https://portal.in2magic.cn');
define('PSR_SALYUT_YTM_RREQUEST_NOTIFY_URL','https://octopus.in2magic.cn');
define('PSR_SALYUT_YTM_APP_VERSION',1);
define('PSR_SALYUT_YTM_CALLBACK_URL','/anole/thirdparty/xgp/order/dragon');