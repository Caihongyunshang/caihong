<?php
/*支付接口订单监控文件
说明：用于请求支付接口订单列表，同步未通知到本站的订单，防止漏单。
监控频率建议5分钟一次
监控地址：/cron.php?key=监控密钥
注意：千万不要监控太快或使用多节点监控！！！否则会被支付接口自动屏蔽IP地址
*/

if(preg_match('/Baiduspider/', $_SERVER['HTTP_USER_AGENT']))exit;
if(!isset($_SERVER['HTTP_USER_AGENT']))$_SERVER['HTTP_USER_AGENT']='Mozilla/5.0 (Windows NT 10.0) Safari/537.36';
$nosession=true;
include("./includes/common.php");

if (function_exists("set_time_limit"))
{
	@set_time_limit(0);
}
if (function_exists("ignore_user_abort"))
{
	@ignore_user_abort(true);
}

@header('Content-Type: text/html; charset=UTF-8');

if(empty($conf['cronkey']))exit("请先设置好监控密钥");
if($conf['cronkey']!=$_GET['key'])exit("监控密钥不正确");

if($_GET['do']=='pricejk'){
	$cron_lasttime = getSetting('pricejk_lasttime', true);
	$pricejk_time = $conf['pricejk_time']?$conf['pricejk_time']-50:50;
	if(!isset($_GET['test']) && time()-strtotime($cron_lasttime)<$pricejk_time)exit('上次更新时间:'.$cron_lasttime);
	saveSetting('pricejk_lasttime',$date);
	$success = 0;
	$is_need = 0;
	if($conf['pricejk_yile']==1){
		$allowType = array_merge(explode(',',$CACHE->read('pricejk_type1')), explode(',',$CACHE->read('pricejk_type2')));
	}else{
		$allowType = explode(',',$CACHE->read('pricejk_type1'));
		$allowType[] = 'yile';
	}
	if(count($allowType) == 0)exit('没有支持价格监控的对接网站类型');
	$rs=$DB->query("SELECT * FROM pre_shequ ORDER BY id ASC");
	while($res = $rs->fetch())
	{
		if(!in_array($res['type'], $allowType)) continue;
		$tcount = $DB->getColumn("SELECT count(*) FROM pre_tools WHERE is_curl=2 AND shequ='{$res['id']}' AND cid IN ({$conf['pricejk_cid']}) AND active=1");
		if($tcount>0 && $res['username'] && $res['password'] && $res['type']){
			$is_need++;
			$results = third_call($res['type'], $res, 'pricejk', [$res['id'], &$success]);
			if($results === false) continue;
			if($results===true){
				saveSetting('pricejk_status','ok');
			}else{
				saveSetting('pricejk_status',$results);
				echo '对接站点ID'.$res['id'].'：'.$results.'<br/>';
			}
		}
	}
	if($is_need==0){
		exit('没有需要监控价格的商品');
	}else{
		exit('成功更新'.$success.'个商品的价格');
	}
}
elseif($_GET['do']=='daily'){ //每天执行一次
	//每日数据库维护
	$maintain = getSetting('maintain', true);
	if($maintain != date("Ymd")){
		saveSetting('maintain',date("Ymd"));
		$sq1 = $DB->exec("DELETE FROM `pre_pay` WHERE addtime<'".date("Y-m-d H:i:s",strtotime("-12 hours"))."' AND (status=0 OR money='0')");
		$sq2 = $DB->exec("DELETE FROM `pre_cart` WHERE addtime<'".date("Y-m-d H:i:s",strtotime("-30 days"))."'");
		$sq3 = $DB->exec("DELETE FROM `pre_cart` WHERE addtime<'".date("Y-m-d H:i:s",strtotime("-24 hours"))."' and status<2");
		$sq4 = $DB->exec("DELETE FROM `pre_giftlog` WHERE addtime<'".date("Y-m-d H:i:s",strtotime("-7 days"))."'");
		$sq5 = $DB->exec("DELETE FROM `pre_invitelog` WHERE date<'".date("Y-m-d H:i:s",strtotime("-7 days"))."'");
		$DB->exec("OPTIMIZE TABLE `pre_pay`");
		$DB->exec("OPTIMIZE TABLE `pre_cart`");
		$DB->exec("OPTIMIZE TABLE `pre_giftlog`");
		$DB->exec("OPTIMIZE TABLE `pre_invitelog`");
		$count = $sq1+$sq2+$sq3+$sq4+$sq5;
		exit('日常维护任务已成功执行，本次共清理'.$count.'条数据<br/>');
	}else{
		echo '日常维护任务今天已执行过<br/>';
	}
	//分站排行榜奖励发放
	if($conf['rank_reward']){
		$limit = intval($conf['rank_reward']);
		$cron_lasttime = getSetting('cron_rank_time', true);
		if($cron_lasttime != date("Ymd")){
			$re = $DB->query("SELECT a.zid,SUM(money) AS money FROM pre_orders AS a WHERE (TO_DAYS(NOW()) - TO_DAYS(addtime) = 1) AND zid>1 AND status!=4 GROUP BY zid HAVING money>0 ORDER BY money DESC LIMIT {$limit}");
			$allmoney = 0;
			$count = 0;
			while ($site = $re->fetch()) {
				$reward = round($site['money'] * $conf['rank_percentage'] / 100, 2);
				if($reward>0){
					$allmoney += $reward;
					$count++;
					changeUserMoney($site['zid'], $reward, true, '奖励', '网站昨日销量排行前'.$limit.'名奖励'.$reward.'元');
				}
			}
			saveSetting('cron_rank_time' , date("Ymd"));
			saveSetting('cron_rank_money' , $allmoney);
			echo '分站排行榜奖励发放完成，发放站点数量：'.$count.'&nbsp;总金额：'.$allmoney.'元<br/>';
		}else{
			echo '今日分站排行榜奖励发放任务已完成<br/>';
		}
	}
	//提成延迟到账
	if($conf['tixian_limit']==1 && $conf['tixian_days']>0){
		$cron_lasttime = getSetting('cron_rmb_time', true);
		if($cron_lasttime != date("Ymd")){
			$days = intval($conf['tixian_days']);
			$maxdays = $days+5;
			$rs=$DB->query("SELECT A.id,A.zid,A.point,A.status,B.rmb,B.rmbtc FROM pre_points A LEFT JOIN pre_site B ON A.zid=B.zid WHERE A.action='提成' AND A.status=0 AND TO_DAYS(NOW())-TO_DAYS(A.addtime)>={$days} AND TO_DAYS(NOW())-TO_DAYS(A.addtime)<={$maxdays}");
			$c=0;
			while($row = $rs->fetch()){
				if($row['rmb']-$row['rmbtc']>$row['point']){
					$DB->exec("UPDATE pre_site SET rmbtc=rmbtc+{$row['point']} WHERE zid='{$row['zid']}'");
				}else{
					$DB->exec("UPDATE pre_site SET rmbtc={$row['rmb']} WHERE zid='{$row['zid']}'");
				}
				$DB->exec("UPDATE pre_points SET status=1 WHERE id='{$row['id']}'");
				$c++;
			}
			saveSetting('cron_rmb_time' , date("Ymd"));
			echo '今日提成处理成功('.$c.')<br/>';
		}else{
			echo '今日提成处理已完成<br/>';
		}
	}
}
elseif($_GET['do']=='updatestatus'){ //订单状态监控
	if($conf['updatestatus']==0)exit('当前站点未开启订单状态监控');
	$updatestatus_interval = $conf['updatestatus_interval']?$conf['updatestatus_interval']:6;
	$times = intval($updatestatus_interval) * 3600; //检测间隔时间
	$limit = 10; //每次更新订单数量
	$rs=$DB->query("SELECT * FROM pre_orders WHERE status=2 AND djzt=1 AND (uptime IS NULL OR uptime<".(time()-$times).") AND addtime<'".date("Y-m-d H:i:s",time()-$times)."' ORDER BY id DESC LIMIT {$limit}");
	$checkcount=0;
	$successcount=0;
	while($row = $rs->fetch()){
		$tool=$DB->getRow("SELECT * FROM pre_tools WHERE tid='{$row['tid']}' LIMIT 1");
		if($tool['is_curl']==2){
			$shequ=$DB->getRow("SELECT * FROM pre_shequ WHERE id='{$tool['shequ']}' LIMIT 1");
			if($shequ['result']!=2 || empty($shequ['username']) || empty($shequ['password'])){
				$DB->exec("UPDATE `pre_orders` SET `uptime`=".time()." WHERE id='{$row['id']}'");
				continue;
			}
			$list = third_call($shequ['type'], $shequ, 'query_order', [$row['djorder'], $tool['goods_id'], [$row['input'], $row['input2'], $row['input3'], $row['input4'], $row['input5']]]);
			$checkcount++;
			if($list && is_array($list) && ($list['order_state']=='已完成'||$list['order_state']=='订单已完成'||$list['订单状态']=='已完成'||$list['订单状态']=='已发货'||$list['订单状态']=='交易成功'||$list['订单状态']=='已支付')){
				$DB->exec("UPDATE `pre_orders` SET `status`=1,`uptime`=".time()." WHERE id='{$row['id']}'");
				$successcount++;
			}elseif($list && is_array($list) && (strpos($list['order_state'],'异常')!==false||strpos($list['order_state'],'退单')!==false||$list['订单状态']=='异常'||$list['订单状态']=='已退单')){
				$DB->exec("UPDATE `pre_orders` SET `status`=3,`uptime`=".time()." WHERE id='{$row['id']}'");
			}else{
				$DB->exec("UPDATE `pre_orders` SET `uptime`=".time()." WHERE id='{$row['id']}'");
			}
		}
	}
	echo '成功检测'.$checkcount.'个订单，更新'.$successcount.'个订单状态';
	saveSetting('updatestatus_lasttime',$date);
}
elseif($conf['epay_pid'] && $conf['epay_key']){
	$id = isset($_GET['id'])?intval($_GET['id']):1;
	$cron_lasttime = getSetting('cron_lasttime', true);
	if(time()-strtotime($cron_lasttime)<30)exit('ok');
	$trade_no = date("YmdHis",strtotime($cron_lasttime)).'000';
	$limit = $DB->getColumn("SELECT count(*) FROM pre_pay WHERE trade_no>'$trade_no'");
	if($limit<1)exit('ok');
	if($limit>50)$limit=50;
	saveSetting('cron_lasttime',$date);
	$payapi=pay_api(true, $id);
	if(empty($payapi))exit('未配置易支付信息');
	$data = get_curl($payapi.'api.php?act=orders&limit='.$limit.'&pid='.$conf['epay_pid'].'&key='.$conf['epay_key']);
	$arr = json_decode($data, true);
	if($arr['code']==1){
		foreach($arr['data'] as $row){
			if($row['status']==1){
				$trade_no = $row['trade_no'];
				$out_trade_no = $row['out_trade_no'];
				$srow=$DB->getRow("SELECT * FROM pre_pay WHERE trade_no='{$out_trade_no}' LIMIT 1");
				if($srow && $srow['status']==0){
					$DB->exec("UPDATE `pre_pay` SET `status`='1',`endtime`='$date',`api_trade_no`='$trade_no' WHERE `trade_no`='{$out_trade_no}'");
					processOrder($srow);
					echo '已成功补单:'.$out_trade_no.'<br/>';
				}
			}
		}
		exit('ok');
	}else{
		exit($arr['msg']);
	}
}else{
	exit('未配置易支付信息');
}