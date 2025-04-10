<?php
namespace Neigou;

/**
 * 调用说明
 * @param string $url //上报地址
 * @param string $reqDomain //Set Curl Header Host
 * $JobStatusReportClient = new \Neigou\JobStatusReportClient($url,$reqDomain);
 * 
 * @param string $jobId;//服务端生成的Job标识,脚本从 ($job_id = $argv[2])获取;localwebshell (在处理方法追加上 $REPORT_JOB_ID = null 参数),从此参数获取
 * @param string $jobStatus;//Job可选状态['running','success','failed']
 * @param string //备注信息
 * @param array $progress //处理进度
		$progress = array(
			'total'=>1000,  //需要完成的任务总量,必填
			'finished'=>300, //当前完成任务量,必填
			'duration'=>500,  //已执行时间（累积方式）单位ms,必填
 
			（//执行时间可通过下面方式获取
				$JobStatusReportClient->start();
				sleep(3);
				$duration = $JobStatusReportClient->getExecDuration();
			）
			'user_message'=>'',//用户层面展示消息,可选
			'data_message'=>'',//预留用于程序层面处理,可选
			'report_time'=> //提交时间格式：2017-12-04 11:59:32,可选
		)
 * @return bool $ret 
 * 
 * $ret = $JobStatusReportClient->report($jobId,$jobStatus,$message,$progress);
 * 
 * @author
 */
class JobStatusReportClient {
	public $jobId;
	public $jobStatus;
	public $message;
	public $progress;
	
	public $url = '';//上报地址
	public $reqDomain = ''; //Set Curl Header
	private $curl;
	
	private $start;
	private $end;

	public	function __construct($url = '',$reqDomain = '') {
		if(strlen($url)) $this->url = $url;
		if(strlen($reqDomain)) $this->reqDomain = $reqDomain;
		
		$this->curl   = new \Neigou\Curl();
		$this->curl->SetHeader('Host',$this->reqDomain);//set Http header
	}
	
	public function report($jobId = null,$jobStatus = null,$message = null,$progress = array()){
		$this->jobId = $jobId;//服务端生成的Job标识,从执行脚本 ($job_id = $argv[2]) 获取
		$this->jobStatus = $jobStatus;//Job可选状态['running','success','failed']
		$this->message = $message;//备注信息
		/*
		作业进度格式
		$progress = array(
			'total'=>1000,  //需要完成的任务总量,必填
			'finished'=>300, //当前完成任务量,必填
			'duration'=>500,  //已执行时间单位ms,必填
			'user_message'=>'',//用户层面展示消息,可选
			'data_message'=>'',//预留用于程序层面处理,可选
			'report_time'=> //提交时间格式：2017-12-04 11:59:32,可选
		)
		*/
		if(empty($this->jobId)) return false;
		$status = array('running','success','failed');
		if(!in_array($this->jobStatus,$status)){
			return false;
		}
		
		$this->progress = array(
			'total'=>isset($progress['total']) ? intval($progress['total']) : 1,
			'finished'=>isset($progress['finished']) ? intval($progress['finished']) : 1,
			'duration'=>isset($progress['duration']) ? intval($progress['duration']) : 0,
			'user_message'=>isset($progress['user_message']) ? strval($progress['user_message']) : '',
			'data_message'=>isset($progress['data_message']) ? strval($progress['data_message']) : '',
			'report_time'=>isset($progress['report_time']) ? strval($progress['report_time']) : date('Y-m-d H:i:s'),
		);
		
		$data = array(
			'job_id'=>$this->jobId,//服务端生成的Job标识,从执行脚本 ($job_id = $argv[2]) 获取
			'job_status'=>$this->jobStatus,//Job可选状态['running','success','failed']
			'message'=>$this->message,//备注信息
			'progress' =>$this->progress,
		);
		$ret = $this->curl->Post($this->url,$data);//{"code":"1"} (1->上报成功，0->上报失败)
		
		$res = json_decode($ret,true);//正常可解析json
		return isset($res['code']) && ($res['code'] == 1) ? true : false;
	}
	
	public function msectime() {
		list($msec, $sec) = explode(' ', microtime());
		$msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
		return $msectime;
	}
	
	//启动毫秒记时器
	public function start(){
		$this->start = $this->msectime();
	}
	
	//重启毫秒记时器
	public function reset(){
		$this->start = $this->end = $this->msectime();
	}
	
	//获取执行时间
	public function getExecDuration()
	{
		$this->end = $this->msectime();
		if($this->start && $this->end && $this->end > $this->start){
			return $this->end - $this->start;
		}
		return 0;
	}
	
}
