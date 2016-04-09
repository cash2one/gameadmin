<?php 
/**
 * 元宝获得统计
 */

include_once '../../../protected/config/config.php';
include_once SYSDIR_ADMIN_INCLUDE . '/global.php';
include_once SYSDIR_ADMIN_DICT . '/arrItemsAll.php';
include_once SYSDIR_ADMIN_DICT . '/goldConfig.php';

//include_once '../../../protected/game_config/webgame/game_function.php';
global $lang, $arrItemsAll, $goldType, $rowGroup;

$openTimestamp = strtotime( ONLINEDATE );

//元宝操作类型
$op_type = $dictOperation;

// 错误信息
$errorMsg = $successMsg = array();

// 获取参数
$role 	= isset( $_REQUEST['role'] )? $_REQUEST['role'] : array();
$page	= isset( $_REQUEST['page'] )? $_REQUEST['page'] : 1;//当前页码
$page_size = isset( $_REQUEST['pagesize'] )? $_REQUEST['pagesize'] : 1;//当前页码;//一页显示记录数
$dec_send_gold = isset($_GET['dec_send_gold']) ? 1 : 0;//是否去掉内部赠送元宝

// 过滤参数
if( !empty( $role['account_name'] ) )// 账户名
{
	$role['account_name'] = $account_name = SS( $role['account_name'] );
	$smarty->assign('showName', $account_name);
}

if( !empty( $role['role_name'] ) )// 角色名
{
	$role['role_name'] = $role_name = SS( $role['role_name'] );
	$smarty->assign('showName', $role_name);
}

if( !empty( $_REQUEST['starttime'] ) )// 开始日期（从日历）
{
	$start_time 	= strtotime( SS( $_REQUEST['starttime'] ) );
}
else
{
	$start_time 	= strtotime( '-7 days 00:00:00' );	
}

if($start_time < $openTimestamp)
{
	$start_time = $openTimestamp;
}


if( !empty( $_REQUEST['endtime'] ) )// 结束日期（从日历）
{
	$end_time 		= strtotime( SS( $_REQUEST['endtime'] ) . ' 23:59:59' );
}
else
{ 
	$end_time 		= strtotime( 'today 23:59:59' );
}

if( !empty( $_REQUEST['lookingday'] ))// 当前查看的日期
{
	$lookingday = SS( $_REQUEST['lookingday'] );
}
else // 设置当前查看的日期为当日
{
	$lookingday = date( 'Y-m-d' );
}
if( !empty( $_REQUEST['preday'] ) )// 查看前一天
{
	$start_time = strtotime( $lookingday . ' 00:00:00 -1 day' );
	$end_time	= strtotime( $lookingday . ' 23:59:59 -1 day' );
	$lookingday = date('Y-m-d', $start_time);
}
elseif( !empty( $_REQUEST['nextday'] ) )// 查看后一天
{
	$start_time = strtotime( $lookingday . ' 00:00:00 +1 day' );
	$end_time	= strtotime( $lookingday . ' 23:59:59 +1 day' );
	$lookingday = date('Y-m-d', $start_time);
}
elseif( !empty( $_REQUEST['today'] ) )// 查看当天
{
	$today 		= date( 'Y-m-d' );
	$start_time = strtotime( $today . ' 00:00:00' );
	$end_time	= strtotime( $today . ' 23:59:59' );
	$lookingday = $today;
}
elseif( !empty( $_REQUEST['showAll'] ) )// 查看全部
{
	$today 		= date( 'Y-m-d' );
	$start_time = strtotime( $serverList[$_SESSION ['gameAdminServer']]['onlinedate'] . ' 00:00:00' );
	$end_time	= strtotime( $today . ' 23:59:59' );
	$lookingday = $today;
}


// 构造$andCondArray
$andCondArray = array();
$notValid = false;

if ( !empty( $account_name ) )
{
	$andCondArray[] = " account_name = '$account_name' ";	
}
if ( !empty( $role_name ) )
{	
	$andCondArray[] = " role_name = '$role_name' ";
}
if ( !empty( $start_time ) && !empty( $end_time ) && $start_time > $end_time )
{// 提供了开始和结束时间，但是开始时间大于结束时间
	$errorMsg[] = $lang->page->startTimeGtEndTime;
	$notValid = true;
}
if ( !empty( $start_time ) )
{
	$andCondArray[] = " mtime > $start_time ";
}
if ( !empty( $end_time ) )
{ 
	$andCondArray[] = " mtime < $end_time ";
}

$cond = '';
$sort = '';
// 当提供了查询条件则执行查询
if( !$notValid && !empty( $andCondArray ) )
{  
	$cond 	= implode( ' AND ' ,$andCondArray ) ;
	$start 	= ( $page - 1 ) * $pageSize;
	if(1 == $dec_send_gold){
    	$sql_gold = "select SUM(gold) all_gold, type, COUNT(*) gold_op_count from (
    		select U20.*, U30.role_name role_name2 from (select * from ".T_LOG_GOLD." WHERE {$cond}) U20 left join (select distinct role_name from ".T_LOG_SEND_YUANBAO." where `type`=2) U30 on U20.role_name=U30.role_name
    	) U10 where role_name2 is null GROUP BY type {$sort} ORDER BY all_gold DESC";
	}else{
    	$sql_gold 	= "SELECT
    					SUM(gold) all_gold, type, COUNT(*) gold_op_count 
    			   FROM " . T_LOG_GOLD . "
    			   WHERE $cond AND gold>0
    			   GROUP BY type
    			   ORDER BY all_gold
    			   ";
    	$sql_liquan = "SELECT SUM(liquan) all_liquan, type, COUNT(*) liquan_op_count 
    			   FROM " . T_LOG_LIQUAN . "
    			   WHERE $cond AND liquan>0
    			   GROUP BY type
    			   ORDER BY all_liquan
    			   ";
	}

	$gold_statistics = GFetchRowSet( $sql_gold, null, 200 );
	$gold_decrease_statistics = array();
	
	//礼券
	$liquan_statistics = GFetchRowSet( $sql_liquan, null, 200 );
	$liquan_decrease_statistics = array();


	$gold_liquan_statistics = array();
	$totalDecreaseGold = $totalDecreaseLiquan = 0;
	
	if($gold_statistics) {
		foreach($gold_statistics as $k => $gs) {
			
			$gs['all_gold'] = $gs['all_gold'];
			$gold_statistics[$k]['all_gold'] = $gs['all_gold'];
			
			$totalDecreaseGold += $gs['all_gold'];
			
			$type = $gs['type']%10000;
			$gold_liquan_statistics[$type]['type'] = $type;
			$gold_liquan_statistics[$type]['all_gold'] = $gs['all_gold'];
			$gold_liquan_statistics[$type]['gold_op_count'] = $gs['gold_op_count'];
		}
	}
	if($liquan_statistics) {
		foreach($liquan_statistics as $k => $ls) {
			
			$ls['all_liquan'] = $ls['all_liquan'];
			$liquan_statistics[$k]['all_liquan'] = $ls['all_liquan'];
			
			$totalDecreaseLiquan += $ls['all_liquan'];
			
			$type = $ls['type']%10000;
			$gold_liquan_statistics[$type]['type'] = $type;
			$gold_liquan_statistics[$type]['all_liquan'] = $ls['all_liquan'];
			$gold_liquan_statistics[$type]['liquan_op_count'] = $ls['liquan_op_count'];
		}
	}
	
	foreach ($gold_liquan_statistics as &$record1)
	{
		if( $record1['all_gold'] == 0 )
		{
			$record1['gold_pecentage'] = 0;
		}
		else 
		{
			if( $totalDecreaseGold > 0 )
			{
				$record1['gold_pecentage'] = 100 * (round( @($record1['all_gold'] / $totalDecreaseGold), 4));
			}
			else
			{
				$record1['gold_pecentage'] = 0;
			}
			if( $totalDecreaseLiquan > 0 )
			{
				$record1['liquan_pecentage'] = 100 * (round( @($record1['all_liquan'] / $totalDecreaseLiquan), 4));
			}
			else
			{
				$record1['liquan_pecentage'] = 0;
			}
		}
	}
	$gold_decrease_statistics = sortArrayByKey($gold_liquan_statistics, 'all_gold', 'desc');
	
//	print_r($gold_decrease_statistics);
	// 获取满足条件的记录数
	$smarty->assign( 'gold_decrease_statistics', $gold_decrease_statistics );

}


// 设置smarty的变量
$minDate = ONLINEDATE;
$maxDate = Datatime :: getTodayString();
$smarty->assign( 'minDate', $minDate);
$smarty->assign( 'maxDate', $maxDate);
$smarty->assign( 'rowGroup', $rowGroup);
$smarty->assign( 'rowGroupJson', json_encode($rowGroup));
$smarty->assign( 'highlightPercentage', HIGHTLIGHT_PERCENTAGE);
$smarty->assign( 'startTime', 	$start_time );
$smarty->assign( 'endTime', 	$end_time);
$smarty->assign( 'lookingDay', $lookingday);
$smarty->assign( 'role', $role);
$smarty->assign( 'arrItemsAll' , $arrItemsAll);
$smarty->assign( 'op_type' , $op_type);
$smarty->assign( 'dec_send_gold' , $dec_send_gold);
$smarty->assign( 'errorMsg', 	implode('<br/>', $errorMsg ) );
$smarty->assign( 'successMsg', 	implode('<br/>', $successMsg ) );
$smarty->assign( 'lang', 	$lang );
$smarty->display( 'module/gold/gold_get_statistics.tpl' );
