<?php
//Redis queue

namespace Neigou;
@include_once dirname(__FILE__).'/Config.php';

Class RedisClient {
	private  $_host ;
	private  $_port ;
	private  $_password;
	public  $_redis_connection;
	public $_debug = true;
	private $_prefix;//消息队列前缀 方便监控使用
	private $_err_time;//消息回滚最大次数
	public $_wait=0;

	function __construct(){
		$this->_host = PSR_REDIS_MQ_HOST;
		$this->_port = PSR_REDIS_CMQ_PORT;
		$this->_password = PSR_REDIS_MQ_PWD;
		$this->_redis_connection = null;
		$this->_debug = false;
		$this->_prefix = 'rq:';
		$this->_err_time = 3;
		$this->connect();
	}

	function __destruct(){
		$this->close();
	}

	//链接redis
	private function connect(){
		if(is_null($this->_redis_connection)){
			try {
				$this->_redis_connection = new \Redis();
				$this->_redis_connection->pconnect($this->_host,$this->_port,3);//设置3s超时时间
				$this->_redis_connection->auth($this->_password);
			} catch (Exception $e) {
				$loger_data = array(
					'remark'    => 'redis 链接失败',
					'data'    => $e,
					'success' => false,
				);
				\Neigou\Logger::General('redis_queue_connect_fail',$loger_data);
				return 'connect to server fail';
			}
		}
		return true;
	}

	//断开redis的链接
	private function close(){
		if(is_object($this->_redis_connection)){
			$this->_redis_connection->close();
		}
		$this->_redis_connection = null;
	}


	//生产消息
	public function publish($queue_name, $msg) {
		if(empty($queue_name)|| empty($msg)) return 'param err';//检测必要参数
		if(!$this->_redis_connection) return 'connection lost';
		$in['click'] = 0;//用来计算重试次数
		$in['msg'] = $msg;
		$rzt = $this->add_msg($queue_name,$in);
		if($this->_debug){
			echo date('Y-m-d H:i:s');
			echo 'key:'.$this->_prefix.$queue_name."\n";
			echo 'val:'.json_encode($msg)."\n\n";
		}
		if(!$rzt){
//			$loger_data = array(
//				'remark'    => 'publish err queue-name:'.$queue_name,
//				'data'    => $msg,
//				'success' => false,
//			);
//			\Neigou\Logger::Debug('redis_queue_publish_err',$loger_data);
			return 'publish err';
		} else {
//			$loger_data = array(
//				'remark'    => 'publish success queue-name:'.$queue_name,
//				'data'    => $msg,
//				'success' => true,
//			);
//			\Neigou\Logger::General('redis_queue_publish_succ',$loger_data);
			return 'succ';
		}
	}


	//消费消息
	public function consume($queue_name, $callback){
		if(empty($queue_name)) return 'key err';//检测必要参数
		if(!$this->_redis_connection) return 'connection lost';

		//获取消息内容
		$msg = $this->get_msg($queue_name);
		$val = $msg;
		if($this->_debug){
			echo date('Y-m-d H:i:s');
			echo 'got-msg:'.$msg."\n";
		}

		//开始处理消息
		$msg = json_decode($msg,true);
		if(!$this->check_msg($queue_name,$msg,$val)){
			//终止操作
			//判断出现timeout的次数 如果大于100次 进程休息
			$this->_wait += 1;

			$loger_data = array(
				'remark'    => 'redis queue all done exit',
				'success' => true,
			);
			\Neigou\Logger::General('redis_queue_consume',$loger_data);
			exit();

		}
		//处理消息逻辑
		$encryptData = array('data'=>base64_encode(json_encode($msg)));
		$token= $this->get_token($encryptData);
		$encryptData['token'] = $token;
		$curl = new Curl();
		if($this->_debug){
			echo date('Y-m-d H:i:s');
			echo 'callback:'.$callback."\n";
			echo 'send-body: '.json_encode($encryptData)."\n";
		}
		$loger_data = array(
			'remark'    => 'do curl worker',
			'data'    => $encryptData,
			'target' => $callback,
			'success' => true,
		);
		\Neigou\Logger::General('redis_queue_consume',$loger_data);
		$process_status  = $curl->Post($callback,$encryptData);

		$loger_data = array(
			'remark'    => 'response from server',
			'data'    => $process_status,
			'success' => true,
		);
		\Neigou\Logger::General('redis_queue_consume',$loger_data);
		$process_status = json_decode($process_status,true);

		if($this->_debug){
			echo date('Y-m-d H:i:s');
			echo 'worker-status:'.$process_status['code']."\n\n";
		}

		//处理消息成功 删除消息
		if($process_status['code']==10000){
			$this->del_process($queue_name,$val);
		} else {
			//处理消息失败 插入回消息队列 记录失败的消息key
			$this->del_process($queue_name,$val);
			$msg['click'] +=1;
			$this->add_msg($queue_name,$msg);
			//TODO 记录失败日志
		}
	}

	function get_token($arr) {
		ksort($arr);
		$sign_ori_string = "";
		foreach($arr as $key=>$value) {
			if (!empty($value) && !is_array($value)) {
				if (!empty($sign_ori_string)) {
					$sign_ori_string .= "&$key=$value";
				} else {
					$sign_ori_string = "$key=$value";
				}
			}
		}
		$sign_ori_string .= ("&key=".ECSTORE_OPENAPI_KEY);
		return  strtoupper(md5($sign_ori_string));
	}


	//
	/**
	 * 超过一定次数消息加入到作废队列
	 * @param $key 队列key
	 * @param $msg json_decode的消息
	 * @param $val 原始消息 用来删除消息使用
	 * @return bool
	 */
	public function check_msg($key,$msg,$val){
		if(empty($val)){
			return false;
		}
		if($msg['click']>=$this->_err_time){
			//加入到失败队列 考虑可能出现多个worker的情况 所以不考虑从redis rpoplpush形式处理无法保障消息的一致性
			$this->add_msg($key.'_fail',$msg);
			$this->del_process($key,$val);//out process
			return false;
		} else {
			$this->_wait=0;//清除等待
			return true;
		}
	}

	//状态
	public function status(){
		$data['queue_num'] = $this->_redis_connection->keys($this->_prefix.'*');
		$data['process_num']= $this->_redis_connection->keys($this->_prefix.'*_process');
		return $data;
	}

	//在list插入【REDIS】
	public function add_msg($key,$val,$is_fail=false){
		if(strlen($this->_prefix.$key.'_s')>30){
//			$loger_data = array(
//				'remark'    => 'key too long',
//				'data'    => $key,
//				'target' => $val,
//				'success' => true,
//			);
//			\Neigou\Logger::General('redis_queue_add_msg',$loger_data);
			return false;//数据key太长了
		}
		if($is_fail){
			return $this->_redis_connection->lpush($this->_prefix.$key,json_encode($val));
		} else {
//			$loger_data = array(
//				'remark'    => 'do lpush',
//				'data'    => $this->_prefix.$key.'_s',
//				'target' => $val,
//				'success' => true,
//			);
//			\Neigou\Logger::General('redis_queue_add_msg',$loger_data);
			return $this->_redis_connection->lpush($this->_prefix.$key.'_s',json_encode($val));
		}

	}

	//获取消息并转入中转换【REDIS】
	public function get_msg($key){
//		$loger_data = array(
//			'remark'    => 'do rpoplpush',
//			'data'    => $this->_prefix.$key.'_s'.'-->'.$this->_prefix.$key.'_process',
//			'success' => true,
//		);
//		\Neigou\Logger::General('redis_queue_add_msg',$loger_data);
		return $this->_redis_connection->rpoplpush($this->_prefix.$key.'_s',$this->_prefix.$key.'_process');
	}

	//删除消息【REDIS】
	public function del_process($key,$val){
		$loger_data = array(
			'remark'    => 'del msg lrem ',
			'data'    => $this->_prefix.$key.'_process',
			'target' => $val,
			'success' => true,
		);
		//\Neigou\Logger::General('redis_queue_del',$loger_data);
//		echo $this->_prefix.$key.'_process';
		return $this->_redis_connection->lrem($this->_prefix.$key.'_process',$val,1);//根据消息内容 删除一条消息 count>0按照从头到尾删除
	}

	//RollBack
	public function roll_back($key){
//		$loger_data = array(
//			'remark'    => 'do rollback',
//			'data'    => $this->_prefix.$key.'_s'.'-->'.$this->_prefix.$key.'_process',
//			'success' => true,
//		);
//		\Neigou\Logger::General('redis_queue_rollback_msg',$loger_data);
		return $this->_redis_connection->rpoplpush($this->_prefix.$key.'_process',$this->_prefix.$key.'_s');
	}

}