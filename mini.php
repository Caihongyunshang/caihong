<?php
$is_defend=true;
include("./includes/common.php");
$qq=isset($_GET['qq'])?htmlspecialchars(strip_tags(trim($_GET['qq']))):null;

if($conf['cdnpublic']==1){
	$cdnpublic = '//lib.baomitu.com/';
}elseif($conf['cdnpublic']==2){
	$cdnpublic = '//cdn.bootcss.com/';
}elseif($conf['cdnpublic']==4){
	$cdnpublic = '//s1.pstatp.com/cdn/expire-1-M/';
}else{
	$cdnpublic = '//cdn.staticfile.org/';
}

$addsalt=md5(mt_rand(0,999).time());
$_SESSION['addsalt']=$addsalt;
$x = new \lib\hieroglyphy();
$addsalt_js = $x->hieroglyphyString($addsalt);

$rs=$DB->query("SELECT * FROM pre_class WHERE active=1 ORDER BY sort ASC");
$select='<option value="0">请选择分类</option>';
$shua_class[0]='默认分类';
while($res = $rs->fetch()){
	$shua_class[$res['cid']]=$res['name'];
	$select.='<option value="'.$res['cid'].'">'.$res['name'].'</option>';
}

$select2='<option value="0">请选择商品</option>';

@header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?php echo $conf['sitename']?> - <?php echo $conf['title']?></title>
  <meta name="keywords" content="<?php echo $conf['keywords']?>">
  <meta name="description" content="<?php echo $conf['description']?>">
  <link href="<?php echo $cdnpublic?>twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
  <!--[if lt IE 9]>
    <script src="<?php echo $cdnpublic?>html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="<?php echo $cdnpublic?>respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
<style>
img.logo{width:14px;height:14px;margin:0 5px 0 3px;}
.onclick{cursor: pointer;touch-action: manipulation;}
</style>
</head>
<body>
<div class="col-xs-12 col-sm-10 col-md-8 col-lg-6 center-block" style="float: none;" id="page">
<div class="panel panel-info">
<div class="list-group-item reed" style="background:#64b2ca;"><h3 class="panel-title"><font color="#fff"><i class="fa fa-volume-up"></i>&nbsp;&nbsp;<b>网站公告</b></font></h3></div>
<?php echo $conf['anounce']?>
</div>

<div class="panel panel-info">
<div class="list-group-item reed" style="background:#64b2ca;"><h3 class="panel-title"><font color="#fff"><i class="fa fa-shopping-cart"></i>&nbsp;&nbsp;<b>自助下单</b></font></h3></div>
	<ul class="nav nav-tabs">
		<li class="active"><a href="#onlinebuy" data-toggle="tab">在线下单</a></li><li><a href="#query" data-toggle="tab">订单查询</a></li>
	</ul>
	<div class="list-group-item">
		<div id="myTabContent" class="tab-content">
		<div class="tab-pane fade in active" id="onlinebuy">
			<div class="form-group" id="display_selectclass">
				<div class="input-group"><div class="input-group-addon">选择分类</div>
				<select name="tid" id="cid" class="form-control"><?php echo $select?></select>
				<div class="input-group-addon"><span class="glyphicon glyphicon-search onclick" title="搜索商品" id="showSearchBar"></span></div>
			</div></div>
			<div class="form-group" id="display_searchBar" style="display:none;">
				<div class="input-group"><div class="input-group-addon"><span class="glyphicon glyphicon-remove onclick" title="关闭" id="closeSearchBar"></span></div>
				<input type="text" id="searchkw" class="form-control" placeholder="搜索商品"/>
				<div class="input-group-addon"><span class="glyphicon glyphicon-search onclick" title="搜索" id="doSearch"></span></div>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">选择商品</div>
				<select name="tid" id="tid" class="form-control" onchange="getPoint();"><?php echo $select2?></select>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">商品价格</div>
				<input type="text" name="need" id="need" class="form-control" disabled/>
			</div></div>
			<div class="form-group" id="display_left" style="display:none;">
				<div class="input-group"><div class="input-group-addon">库存数量</div>
				<input type="text" name="leftcount" id="leftcount" class="form-control" disabled/>
			</div></div>
			<div class="form-group" id="display_num" style="display:none;">
				<div class="input-group"><div class="input-group-addon">数量</div>
				<div class="input-group-addon"><input id="num_min" type="button" value="-"/></div>
				<div class="input-group-addon"><input id="num" name="num" class="form-control" type="number" min="1" value="1"/></div>
				<div class="input-group-addon"><input id="num_add" type="button" value="+"/></div>
			</div></div>
			<div id="inputsname"></div>
			<div id="alert_frame" class="alert alert-warning" style="display:none;"></div>
			<input type="submit" id="submit_buy" class="btn btn-primary btn-block" value="立即购买">
		</div>
		<div class="tab-pane fade in" id="query">
			<div class="alert alert-info" <?php if(empty($conf['gg_search'])){?>style="display:none;"<?php }?>><?php echo $conf['gg_search']?></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">查询内容</div>
				<input type="text" name="qq" id="qq3" value="<?php echo $qq?>" class="form-control" placeholder="请输入下单账号（留空则根据浏览器缓存查询）" required/>
			</div></div>
			<input type="submit" id="submit_query" class="btn btn-primary btn-block" value="立即查询">
			<div id="result2" class="form-group" style="display:none;">
				<table class="table table-striped">
				<thead><tr><th>下单账号</th><th>商品名称</th><th>数量</th><th>购买时间</th><th>状态</th><th>操作</th></tr></thead>
				<tbody id="list">
				</tbody>
				</table>
			</div>
		</div>
		</div>
	</div>
</div>
</div>
</div>

<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="<?php echo $cdnpublic?>jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/2.3/layer.js"></script>

<script type="text/javascript">
var isModal=false;
var homepage=false;
var hashsalt=<?php echo $addsalt_js?>;
window.parent.postMessage($("#page").height(),'*');
</script>
<script src="./assets/js/main.js?ver=<?php echo VERSION ?>"></script>
</body>
</html>