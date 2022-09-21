<?php
$nosession = true;
include("./includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;
$url=daddslashes($_GET['url']);
$authcode=daddslashes($_GET['authcode']);

@header('Content-Type: application/json; charset=UTF-8');

if($act=='clone')
{
	$key=daddslashes($_GET['key']);
	if(!$key)exit('{"code":-5,"msg":"确保各项不能为空"}');
	if($key!==md5($password_hash.md5(SYS_KEY).$conf['apikey']))exit('{"code":-4,"msg":"克隆密钥错误"}');
	$rs=$DB->query("SELECT * FROM pre_class ORDER BY cid ASC");
	$class=array();
	while($res = $rs->fetch()){
		$class[]=$res;
	}
	$rs=$DB->query("SELECT * FROM pre_tools ORDER BY tid ASC");
	$tools=array();
	while($res = $rs->fetch()){
		$tools[]=$res;
	}
	$rs=$DB->query("SELECT id,url,type FROM pre_shequ ORDER BY id ASC");
	$shequ=array();
	while($res = $rs->fetch()){
		$shequ[]=$res;
	}
	$rs=$DB->query("SELECT * FROM pre_price ORDER BY id ASC");
	$price=array();
	while($res = $rs->fetch()){
		$price[]=$res;
	}
	$result=array("code"=>1,"class"=>$class,"tools"=>$tools,"shequ"=>$shequ,"price"=>$price);
}
elseif($act=='tools')
{
	$key=daddslashes($_GET['key']);
	$limit=isset($_GET['limit'])?intval($_GET['limit']):50;
	if(!$key)exit('{"code":-5,"msg":"确保各项不能为空"}');
	if($key!=$conf['apikey'])exit('{"code":-4,"msg":"API对接密钥错误，请在后台设置密钥"}');
	$rs=$DB->query("SELECT * FROM pre_tools WHERE active=1 ORDER BY tid ASC LIMIT $limit");
	while($res = $rs->fetch()){
		$data[]=array('tid'=>$res['tid'],'cid'=>$res['cid'],'sort'=>$res['sort'],'name'=>$res['name'],'price'=>$res['price']);
	}
	exit(json_encode($data));
}
elseif($act=='orders')
{
	$tid=intval($_GET['tid']);
	$key=daddslashes($_GET['key']);
	$limit=isset($_GET['limit'])?intval($_GET['limit']):50;
	$format=isset($_GET['format'])?daddslashes($_GET['format']):'json';
	if(!$key)exit('{"code":-5,"msg":"确保各项不能为空"}');
	if($key!=$conf['apikey'])exit('{"code":-4,"msg":"API对接密钥错误，请在后台设置密钥"}');
	if($tid){
		$tool=$DB->getRow("SELECT tid,value FROM pre_tools WHERE tid='$tid' AND active=1 LIMIT 1");
		if(!$tool)exit('{"code":-5,"msg":"商品ID不存在"}');
		$sqls=" and tid='$tid'";
		$value=$tool['value']>0?$tool['value']:1;
	}
	$rs=$DB->query("SELECT * FROM pre_orders WHERE status=0{$sqls} ORDER BY id ASC LIMIT $limit");
	while($res = $rs->fetch()){
		$data[]=array('id'=>$res['id'],'tid'=>$res['tid'],'input'=>$res['input'],'input2'=>$res['input2'],'input3'=>$res['input3'],'input4'=>$res['input4'],'input5'=>$res['input5'],'value'=>$res['value'],'status'=>$res['status']);
		if($_GET['sign']==1)$DB->exec("UPDATE `pre_orders` SET status=1 WHERE `id`='{$res['id']}'");
	}
	if($format=='text'){
		$txt = '';
		foreach($data as $row){
			$txt .= $row['input'] . ($row['input2']?'----'.$row['input2']:null) . ($row['input3']?'----'.$row['input3']:null) . ($row['input4']?'----'.$row['input4']:null) . ($row['input5']?'----'.$row['input5']:null) . '----' . $row['value'] . "\r\n";
		}
		exit($txt);
	}else{
		exit(json_encode($data));
	}
}
elseif($act=='change')
{
	$id=intval($_GET['id']);
	$key=daddslashes($_GET['key']);
	$status=intval($_GET['zt']); //1:已完成,2:正在处理,3:异常,4:待处理
	if(!$id || !$key)exit('{"code":-5,"msg":"确保各项不能为空"}');
	if($key!=$conf['apikey'])exit('{"code":-4,"msg":"API对接密钥错误，请在后台设置密钥"}');
	$row=$DB->getRow("SELECT id FROM pre_orders WHERE id='$id' LIMIT 1");
	if($row) {
		$sql="UPDATE `pre_orders` SET `status`='$status' WHERE `id`='{$id}' LIMIT 1";
		if($DB->exec($sql)!==false){
			$result=array("code"=>1,"msg"=>"修改成功","id"=>$id);
		}else{
			$result=array("code"=>-2,"msg"=>"修改失败","id"=>$id);
		}
	}
	else
	{
		$result=array("code"=>-5,"msg"=>"订单ID不存在");
	}
}
elseif($act == 'classlist')
{
	$rs=$DB->query("SELECT * FROM pre_class WHERE active=1 ORDER BY sort ASC");
	$data = array();
	while($res = $rs->fetch(PDO::FETCH_ASSOC)){
		$data[]=$res;
	}
	$result=array("code"=>0,"msg"=>"succ","data"=>$data,"count"=>count($data));
	exit(json_encode($result));
}
elseif($act == 'goodslistbycid')
{
	if(isset($_POST['user']) && isset($_POST['pass'])){
		$user = trim(daddslashes($_POST['user']));
		$pass = trim(daddslashes($_POST['pass']));
		$userrow = $DB->getRow("SELECT * FROM `pre_site` WHERE `user` = '{$user}' LIMIT 1");
		if ($userrow && $userrow['user'] == $user && $userrow['pwd'] == $pass && $userrow['status'] == 1) {
			$islogin2 = 1;
			$price_obj = new \lib\Price($userrow['zid'],$userrow);
		} elseif ($userrow && $userrow['status'] == 0) {
			exit('{"code":-1,"message":"该账户已被封禁"}');
		} else {
			exit('{"code":-1,"message":"用户名或密码不正确"}');
		}
	}
	$cid=isset($_POST['cid'])?intval($_POST['cid']):0;
	$rs=$DB->query("SELECT * FROM pre_tools WHERE cid='$cid' AND active=1 ORDER BY sort ASC");
	$data = array();
	while($res = $rs->fetch(PDO::FETCH_ASSOC)){
		if(isset($price_obj)){
			$price_obj->setToolInfo($res['tid'],$res);
			$price=$price_obj->getToolPrice($res['tid']);
		}else $price=$res['price'];
		if($res['is_curl']==4){
			$isfaka = 1;
			$res['input'] = getFakaInput();
		}else{
			$isfaka = 0;
		}
		$data[]=array('tid'=>$res['tid'],'cid'=>$res['cid'],'sort'=>$res['sort'],'name'=>$res['name'],'value'=>$res['value'],'price'=>$price,'input'=>$res['input'],'inputs'=>$res['inputs'],'desc'=>$res['desc'],'alert'=>$res['alert'],'shopimg'=>$res['shopimg'],'validate'=>$res['validate'],'valiserv'=>$res['valiserv'],'repeat'=>$res['repeat'],'multi'=>$res['multi'],'close'=>$res['close'],'prices'=>$res['prices'],'min'=>$res['min'],'max'=>$res['max'],'sales'=>$res['sales'],'isfaka'=>$isfaka,'stock'=>$res['stock']);
	}
	$result=array("code"=>0,"msg"=>"succ","data"=>$data,"count"=>count($data));
	exit(json_encode($result));
}
elseif($act == 'goodslist')
{
	$result['code'] = 0;
	if(isset($_POST['user']) && isset($_POST['pass'])){
		$user = trim(daddslashes($_POST['user']));
		$pass = trim(daddslashes($_POST['pass']));
		$userrow = $DB->getRow("SELECT * FROM `pre_site` WHERE `user` = '{$user}' LIMIT 1");
		if ($userrow && $userrow['user'] == $user && $userrow['pwd'] == $pass && $userrow['status'] == 1) {
			$islogin2 = 1;
			$price_obj = new \lib\Price($userrow['zid'],$userrow);
		} elseif ($userrow && $userrow['status'] == 0) {
			exit('{"code":-1,"message":"该账户已被封禁"}');
		} else {
			exit('{"code":-1,"message":"用户名或密码不正确"}');
		}
	}
	$rs=$DB->query("SELECT * FROM `pre_tools` WHERE `active` = 1 ORDER BY `cid` ASC,`sort` ASC");
	while($res = $rs->fetch()){
		if($islogin2 == 1 && isset($price_obj)){
			$price_obj->setToolInfo($res['tid'],$res);
			$price = $price_obj->getToolPrice($res['tid']);
		}else{
			$price = $res['price'];
		}
		if($res['is_curl']==4){
			$count = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='{$res['tid']}' AND orderid=0");
			//if($count==0)$res['close']=1;
			$isfaka = 1;
		}else{
			$count = $res['stock'];
			$isfaka = 0;
		}
		$data[] = array('tid' => $res['tid'] , 'cid' => $res['cid'] , 'name' => $res['name'] , 'shopimg' => $res['shopimg'] , 'close' => $res['close'] , 'price' => $price , 'isfaka' => $isfaka , 'stock' => $count);
	}
	$result['data'] = $data;
	exit(json_encode($result));
}
elseif($act == 'goodsdetails')
{
	$result['code'] = 0;
	$tid = intval($_POST['tid']);
	if(!$tid)exit('{"code":-1,"message":"商品ID不能为空"}');
	if(isset($_POST['user']) && isset($_POST['pass'])){
		$user = trim(daddslashes($_POST['user']));
		$pass = trim(daddslashes($_POST['pass']));
		$userrow = $DB->getRow("SELECT * FROM `pre_site` WHERE `user` = '{$user}' LIMIT 1");
		if ($userrow && $userrow['user'] == $user && $userrow['pwd'] == $pass && $userrow['status'] == 1) {
			$islogin2 = 1;
			$price_obj = new \lib\Price($userrow['zid'],$userrow);
		} elseif ($userrow && $userrow['status'] == 0) {
			exit('{"code":-1,"message":"该账户已被封禁"}');
		} else {
			exit('{"code":-1,"message":"用户名或密码不正确"}');
		}
	}
	$tool = $DB->getRow("SELECT * FROM `pre_tools` WHERE `tid` = {$tid} LIMIT 1");
	if(!$tool)exit('{"code":-1,"message":"商品不存在"}');
	if($islogin2 == 1 && isset($price_obj)){
		$price_obj->setToolInfo($tid, $tool);
		$price = $price_obj->getToolPrice($tid);
	}else{
		$price = $tool['price'];
	}
	if($tool['is_curl']==4){
		$count = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='{$tool['tid']}' AND orderid=0");
		if($count==0)$tool['close']=1;
		$isfaka = 1;
		$tool['input'] = getFakaInput();
	}else{
		$count = $tool['stock'];
		$isfaka = 0;
		if(empty($tool['input']))$tool['input']='下单账号';
	}
	$data = array('tid'=>$tool['tid'],'cid'=>$tool['cid'],'sort'=>$tool['sort'],'name'=>$tool['name'],'value'=>$tool['value'],'price'=>$price,'prices'=>$tool['prices'],'input'=>$tool['input'],'inputs'=>$tool['inputs'],'desc'=>$tool['desc'],'alert'=>$tool['alert'],'shopimg'=>$tool['shopimg'],'repeat'=>$tool['repeat'],'multi'=>$tool['multi'],'min'=>$tool['min'],'max'=>$tool['max'],'close'=>$tool['close'],'isfaka'=>$isfaka,'stock'=>$count);
	$result['data'] = $data;
	exit(json_encode($result));
}
elseif($act == 'getleftcount')
{
	$tid=trim($_POST['tid']);
	if(!$tid)exit('{"code":-1,"message":"商品ID不能为空"}');
	if(strpos($tid,',')){
		$tids = explode(',',$tid);
		if(count($tids)>20)exit('{"code":-1,"message":"每次最多只能查询20个商品的库存"}');
	}
	if(isset($tids) && count($tids)>0){
		$data = [];
		foreach($tids as $tid){
			$tool = $DB->getRow("SELECT * FROM `pre_tools` WHERE `tid` = ".intval($tid)." LIMIT 1");
			if(!$tool)continue;
			if($tool['is_curl']==4){
				$count = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='$tid' AND orderid=0");
			}elseif($tool['stock']!==null){
				$count = $tool['stock'];
			}else{
				$count = null;
			}
			$data[] = ['tid'=>$tid,'stock'=>$count];
		}
		exit(json_encode(['code'=>0, 'data'=>$data]));
	}else{
		$tool = $DB->getRow("SELECT * FROM `pre_tools` WHERE `tid` = ".intval($tid)." LIMIT 1");
		if(!$tool)exit('{"code":-1,"message":"商品不存在"}');
		if($tool['is_curl']==4){
			$count = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='$tid' AND orderid=0");
		}elseif($tool['stock']!==null){
			$count = $tool['stock'];
		}else{
			exit('{"code":-2,"message":"该商品不限库存"}');
		}
		exit(json_encode(["code"=>0,"count"=>$count]));
	}
}
elseif($act == 'pay')
{
	$result['code'] = -1;
	$tid = intval($_POST['tid']);
	if(!$tid)exit('{"code":-1,"message":"商品ID不能为空"}');
	$user = trim(daddslashes($_POST['user']));
	$pass = trim(daddslashes($_POST['pass']));
	$input1 = isset($_POST['input1']) ? htmlspecialchars(trim(strip_tags(daddslashes($_POST['input1'])))) : exit('{"code":-1,"message":"首个参数值不能为空"}');
	$input2 = htmlspecialchars(trim(strip_tags(daddslashes($_POST['input2']))));
	$input3 = htmlspecialchars(trim(strip_tags(daddslashes($_POST['input3']))));
	$input4 = htmlspecialchars(trim(strip_tags(daddslashes($_POST['input4']))));
	$input5 = htmlspecialchars(trim(strip_tags(daddslashes($_POST['input5']))));
	$num = isset($_POST['num']) ? intval($_POST['num']) : 1;
	$tool = $DB->getRow("SELECT * FROM `pre_tools` WHERE `tid` = {$tid} LIMIT 1");
	if ($tool && $tool['active'] == 1) {
		if($tool['close']==1)exit('{"code":-1,"message":"当前商品维护中，停止下单！"}');
		$inputs=explode('|',$tool['inputs']);
		if($inputs[0] && empty($input2) || $inputs[1] && empty($input3) || $inputs[2] && empty($input4) || $inputs[3] && empty($input5)){
			exit('{"code":-1,"message":"请确保各项不能为空"}');
		}
		if(!$inputs[0] && !empty($input2) || !$inputs[1] && !empty($input3) || !$inputs[2] && !empty($input4) || !$inputs[3] && !empty($input5)){
			exit('{"code":-1,"message":"验证失败"}');
		}
		$userrow = $DB->getRow("SELECT * FROM `pre_site` WHERE `user` = '{$user}' LIMIT 1");
		if ($userrow && $userrow['user'] == $user && $userrow['pwd'] == $pass && $userrow['status'] == 1) {
			$result['code'] = 0;
			if(in_array($input1,explode("|",$conf['blacklist']))) exit('{"code":-1,"message":"你的下单账号已被拉黑，无法下单！"}');
			if($tool['is_curl']==4){
				$count = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='$tid' AND orderid=0");
				$nums=($tool['value']>1?$tool['value']:1)*$num;
				if($count==0)exit('{"code":-1,"message":"该商品库存卡密不足，请联系站长加卡！"}');
				if($nums>$count)exit('{"code":-1,"message":"你所购买的数量超过库存数量！"}');
			}
			elseif($tool['stock']!==null){
				if($tool['stock']==0)exit('{"code":-1,"message":"该商品库存不足，请联系站长增加库存！"}');
				if($num>$tool['stock'])exit('{"code":-1,"message":"你所购买的数量超过库存数量！"}');
			}
			elseif($tool['repeat']==0){
				$thtime=date("Y-m-d").' 00:00:00';
				$row=$DB->getRow("SELECT id,input,status,addtime FROM pre_orders WHERE tid=:tid AND input=:input ORDER BY id DESC LIMIT 1", [':tid'=>$tid, ':input'=>$inputvalue]);
				if($row['input'] && $row['status']==0)
					exit('{"code":-1,"message":"您今天添加的'.$tool['name'].'正在排队中，请勿重复提交！"}');
				elseif($row['addtime']>$thtime)
					exit('{"code":-1,"message":"您今天已添加过'.$tool['name'].'，请勿重复提交！"}');
			}
			if($tool['validate']==1 && is_numeric($input1)){
				if(validate_qzone($input1)==false) exit('{"code":-1,"message":"你的QQ空间设置了访问权限，无法下单！"}');
			}
			if($tool['multi'] == 0 || $num < 1) $num = 1;
			if($tool['multi']==1 && $tool['min']>0 && $num<$tool['min'])exit('{"code":-1,"message":"当前商品最小下单数量为'.$tool['min'].'"}');
			if($tool['multi']==1 && $tool['max']>0 && $num>$tool['max'])exit('{"code":-1,"message":"当前商品最大下单数量为'.$tool['max'].'"}');

			$islogin2 = 1;
			$price_obj = new \lib\Price($userrow['zid'],$userrow);
			$price_obj->setToolInfo($tid,$tool);
			$price = $price_obj->getToolPrice($tid);
			$price=$price_obj->getFinalPrice($price, $num);
			if(!$price)exit('{"code":-1,"message":"当前商品批发价格优惠设置不正确"}');

			$i=2;
			$neednum = $num;
			foreach($inputs as $inputname){
				if(strpos($inputname,'[multi]')!==false && isset(${'inputvalue'.$i}) && is_numeric(${'inputvalue'.$i})){
					$val = intval(${'inputvalue'.$i});
					if($val>0){
						$neednum = $neednum * $val;
					}
				}
				$i++;
			}

			$need = $price * $neednum;
			if($need == 0) exit('{"code":-2,"message":"不支持免费商品对接"}');
			if ($userrow['rmb'] < $need) exit('{"code":-2,"message":"余额不足，购买此商品还差' . ($need - $userrow['rmb']) . '元"}');

			$trade_no = date("YmdHis").rand(111,999).'RMB';
			$input = $input1 . ($input2 ? '|' . $input2 : null) . ($input3 ? '|' . $input3 : null) . ($input4 ? '|' . $input4 : null) . ($input5 ? '|' . $input5 : null);
			$sql="INSERT INTO `pre_pay` (`trade_no`,`type`,`tid`,`zid`,`input`,`num`,`name`,`money`,`ip`,`userid`,`addtime`,`blockdj`,`status`) VALUES (:trade_no, :type, :tid, :zid, :input, :num, :name, :money, :ip, :userid, NOW(), :blockdj, 0)";
			$data = [':trade_no'=>$trade_no, ':type'=>'rmb', ':tid'=>$tid, ':zid'=>$userrow['zid'], ':input'=>$input, ':num'=>$num, ':name'=>$tool['name'], ':money'=>$need, ':ip'=>$clientip, ':userid'=>$userrow['zid'], ':blockdj'=>$blockdj?$blockdj:0];
			if ($DB->exec($sql, $data)) {
				if ($DB->exec("UPDATE `pre_site` SET `rmb` = `rmb` - {$need} WHERE `zid` = '{$userrow['zid']}'") && $DB->exec("UPDATE `pre_pay` SET `status` = 1 WHERE `trade_no` = '{$trade_no}'")) {
					addPointRecord($userrow['zid'], $need, '消费', 'API购买 '.$tool['name']);
					$srow['tid'] = $tid;
					$srow['num'] = $num;
					$srow['input'] = $input;
					$srow['zid'] = $userrow['zid'];
					$srow['money'] = $need;
					$srow['trade_no'] = $trade_no;
					$srow['userid'] = $userrow['zid'];
					if($orderid = processOrder($srow)){
						$result['code'] = 0;
						$result['message'] = 'success';
						$result['orderid'] = $orderid;
						$djzt = $DB->getColumn("SELECT djzt FROM pre_orders WHERE id = '$orderid' LIMIT 1");
						if($djzt == 3){
							$rs=$DB->query("SELECT * FROM pre_faka WHERE tid='$tid' AND orderid='$orderid' ORDER BY kid ASC");
							$kmdata=array();
							while($res = $rs->fetch())
							{
								if(!empty($res['pw'])){
									$kmdata[]=array('card'=>$res['km'],'pass'=>$res['pw']);
								}else{
									$kmdata[]=array('card'=>$res['km']);
								}
							}
							$result['faka']=true;
							$result['kmdata']=$kmdata;
						}
					} else {
						$result['message'] = '下单失败 : ' . $DB->error();
					}
				} else {
					$result['message'] = '下单失败 : ' . $DB->error();
				}
			} else {
				$result['message'] = '下单失败 : ' . $DB->error();
			}
		} elseif ($userrow && $userrow['status'] == 0) {
			$result['message'] = '该账户已被封禁';
		} else {
			$result['message'] = '用户名或密码不正确';
		}
	} else {
		$result['message'] = '商品ID不存在';
	}
}
elseif($act == 'search') 
{
	$result['code'] = -1;
	$id = isset($_POST['id'])?intval($_POST['id']):intval($_GET['id']);
	$row = $DB->getRow("SELECT * FROM `pre_orders` WHERE `id` = {$id} LIMIT 1");
	if ($row){
		$tool = $DB->getRow("SELECT * FROM pre_tools WHERE tid='{$row['tid']}' LIMIT 1");
		if($tool['is_curl']==2){
			$shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE id='{$tool['shequ']}' LIMIT 1");
			$list = third_call($shequ['type'], $shequ, 'query_order', [$row['djorder'], $tool['goods_id'], [$row['input'], $row['input2'], $row['input3'], $row['input4'], $row['input5']]]);
			if($list && is_array($list)){
				if(($list['order_state']=='已完成'||$list['order_state']=='订单已完成'||$list['订单状态']=='已完成'||$list['订单状态']=='已发货'||$list['订单状态']=='交易成功'||$list['订单状态']=='已支付') && $row['status']==2){
					$DB->exec("UPDATE `pre_orders` SET `status`=1 WHERE id='{$id}'");
					$row['status'] = 1;
				}
				if((strpos($list['order_state'],'异常')!==false||strpos($list['order_state'],'退单')!==false||$list['订单状态']=='异常'||$list['订单状态']=='已退单') && $row['status']<3){
					$DB->exec("UPDATE `pre_orders` SET `status`=3 WHERE id='{$id}'");
					$row['status'] = 3;
				}
			}else{
				$list = false;
			}
		}
		if($row['result']){
			$list['订单结果'] = $row['result'];
		}
		$result['code'] = 0;
		$result['message'] = 'success';
		$result['type'] = $tool['is_curl'];
		$result['status'] = $row['status'];
		$result['data'] = $list;
	} else {
		$result['message'] = '订单不存在';
	}
}
elseif($act=='siteinfo')
{
	$count1=$DB->getColumn("SELECT count(*) from pre_orders");
	$count2=$DB->getColumn("SELECT count(*) from pre_orders where status>=1");
	$count3=$DB->getColumn("SELECT count(*) from pre_site");
	$result=array('sitename'=>$conf['sitename'],'kfqq'=>$conf['qq']?$conf['qq']:$conf['kfqq'],'anounce'=>$conf['anounce'],'modal'=>$conf['modal'],'bottom'=>$conf['bottom'],'alert'=>$conf['alert'],'gg_search'=>$conf['gg_search'],'gg_panel'=>$conf['gg_panel'],'version'=>VERSION,'build'=>$conf['build'],'orders'=>$count1,'orders1'=>$count2,'sites'=>$count3,'appalert'=>$conf['appalert']);
}
elseif($act=='token')
{
	$key = isset($_GET['key'])?$_GET['key']:exit('No key');
	$result=array('token'=>get_app_token($key),'time'=>time());
}
else
{
	$result=array("code"=>-5,"msg"=>"No Act!");
}

echo json_encode($result);
?>