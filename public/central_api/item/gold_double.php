<?php
/**
*	Description:仙石翻倍统计
*/

include dirname(dirname(__FILE__)).'/central_api_auth.php';
//include SYSDIR_ADMIN.'/class/central.log.php';
session_start ();
session_regenerate_id();

$beginTime = intval($_REQUEST['begin']);
$endTime = intval($_REQUEST['end']);

// 默认发送昨天的充值数据
if ($beginTime == 0) {
	$beginTime = strtotime('today -1 day');
}
if ($endTime == 0) {
	$endTime = strtotime('today');
}

//$centralLog = new CentralLogClass();
$ip = GetIP();
//$centralLog->start($ip, getCurPageURL(), 24, $beginTime, $endTime);
if ($endTime - $beginTime < 0) {
//	$centralLog->failed(CENTRAL_LOG_TIME_PARAM_ERROR);
	exit();
}

// 不允许一次拉去超过一年的数据
if ($endTime - $beginTime > 86400 * 365) {
//	$centralLog->failed(CENTRAL_LOG_TIME_TOO_LONG);
	exit();
}

// 搜索指定时间内的领取日志, [$beginTime,$endTime) 防止数据丢失和重复！！
try {
    $sql = "select  l.mdate,l.mtime,l.uuid,l.account_name,l.role_name ,l.liquan,g.gold,l.year,l.month,l.day from t_log_liquan l left join t_log_gold g on l.role_name=g.role_name  where l.mtime >={$beginTime} and l.mtime<={$endTime} and l.type=50259 and g.type=20259";
    $result = GFetchRowSet($sql);
    $result = serialize($result);
    echo $result;
//	$centralLog->end();
} catch (Exception $e) {
	// 数据库中不记录具体失败原因，失败日志放在log文件中
//	$centralLog->failed(CENTRAL_LOG_QUERY_SQL_ERROR, $e->getMessage());
} 

exit();
