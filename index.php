<?php
$is_defend=true;
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
    die('require PHP > 5.4 !');
}
if (isset($_SERVER) && $_SERVER['REQUEST_URI'] == '/favicon.ico')exit;

include("./includes/common.php");

if($conf['invite_tid'] && isset($_GET['i']) && $_GET['i']!=$_COOKIE['invitecode']){
	$invite_result = processInvite($_GET['i']);
	if($invite_result=='captcha'){
		@header('Content-Type: text/html; charset=UTF-8');
		include TEMPLATE_ROOT.'default/captcha.php';
		exit;
	}
}
@header('Content-Type: text/html; charset=UTF-8');
if($conf['fenzhan_page']==1 && !empty($conf['fenzhan_remain']) && !in_array($domain,explode(',',$conf['fenzhan_remain'])) && $is_fenzhan==false){
	include ROOT.'template/default/404.html';
	exit;
}
if($conf['forceloginhome']==1 && !$islogin2){
	exit("<script language='javascript'>window.location.href='./user/login.php?back=index';</script>");
}

$qq=isset($_GET['qq'])?htmlspecialchars(strip_tags(trim($_GET['qq']))):null;

$addsalt=md5(mt_rand(0,999).time());
$_SESSION['addsalt']=$addsalt;
$x = new \lib\hieroglyphy();
$addsalt_js = $x->hieroglyphyString($addsalt);

if($is_fenzhan==true && file_exists(ROOT.'assets/img/logo_'.$conf['zid'].'.png')){
	$logo = 'assets/img/logo_'.$conf['zid'].'.png';
}else{
	$logo = 'assets/img/logo.png';
}
if($conf['cdnpublic']==1){
	$cdnpublic = '//lib.baomitu.com/';
}elseif($conf['cdnpublic']==2){
	$cdnpublic = 'https://cdn.bootcdn.net/ajax/libs/';
}elseif($conf['cdnpublic']==4){
	$cdnpublic = '//s1.pstatp.com/cdn/expire-1-M/';
}else{
	$cdnpublic = '//cdn.staticfile.org/';
}
if(!empty($conf['staticurl'])){
	$cdnserver = '//'.$conf['staticurl'].'/';
}else{
	$cdnserver = null;
}

if(!empty($conf['gg_announce']))$conf['anounce']=$conf['gg_announce'].$conf['anounce'];

if($is_fenzhan == true && $siterow['power']==2){
	if($siterow['ktfz_price']>0)$conf['fenzhan_price']=$siterow['ktfz_price'];
	if($conf['fenzhan_cost2']<=0)$conf['fenzhan_cost2']=$conf['fenzhan_price2'];
	if($siterow['ktfz_price2']>0 && $siterow['ktfz_price2']>=$conf['fenzhan_cost2'])$conf['fenzhan_price2']=$siterow['ktfz_price2'];
}

list($background_image, $background_css) = \lib\Template::getBackground();

if($conf['sitename_hide']==1 && !empty($conf['title'])){
	$hometitle = $conf['title'];
}else{
	$hometitle = $conf['sitename'].(!empty($conf['title'])?' - '.$conf['title']:null);
}
$mod = isset($_GET['mod'])?$_GET['mod']:'index';
$loadfile = \lib\Template::load($mod);
include $loadfile;