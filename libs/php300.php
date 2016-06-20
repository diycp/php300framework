<?php
/*
PHP300V1.8云类库版
官方网站：http://php300.cn
在线论坛：http://bbs.php300.cn
登录地址：http://yun.php300.cn 在线提交您的函数
交流QQ群：231201376
*/
define('FUNCTION_VERSION','1.8');	//PHP300云类库版本

define('FUNCTION_UPDATATIME','2016/06/09');	//最后更新时间

define('FUNCTION_NAMES','php300云类库');

define('FUNCTION_NAME','_class.php');

define('FILE_PATH',str_replace(DIRECTORY_SEPARATOR.'libs','',dirname(__FILE__).DIRECTORY_SEPARATOR));
//系统根目录
define('FUNCTION_PATH',FILE_PATH."model".DIRECTORY_SEPARATOR);	//模型目录

define('FRAMEWROK_VER','1.1.5');	//框架版本

$modellist = scandir(FUNCTION_PATH);
$classlist = scandir(FUNCTION_PATH.'class/');
$conlist = scandir(FILE_PATH.'config');
error_reporting(E_ALL ^ E_NOTICE);	//屏蔽NOTICE级别错误
spl_autoload_register("firstload");	//预注册类
foreach($conlist as $val){	//加载配置文件
	if($val!="." and $val!=".."){
		$trim = explode('.',$val);
		if($trim[1]=='php'){
			include_once(FILE_PATH.'config/'.$val);
		}
	}
}

if(!$CON['DEBUG']){	//屏蔽错误
	error_reporting(0);
	ini_set("display_errors", "Off");
}

switch($CON['UPDATE_WAY']){	//同步更新云函数
	case '1':
	if(!file_exists(FILE_PATH.'LOCK')){
		update_class($CON);
		$file_link = fopen(FILE_PATH.'LOCK','w');
		fwrite($file_link,time());
		fclose($file_link);
	}
		break;
	case '2':
		update_class($CON);
		break;
}

if(file_exists(FILE_PATH.'cache/cache_class.php')){
	include_once(FILE_PATH.'cache/cache_class.php');	//引入云函数缓存
}

if($CON['PHP300_AUTO']){
	if(class_exists('php300_class')){
		$GLOBALS['php300'] = new php300_class();
		$php300 = $GLOBALS['php300'];
	}else{
		class php300_class{ };
	}
}

$system_class_arr = array('mysql','system','cookies');	//系统类列表
include_once(FILE_PATH.'plug/Smarty/Smarty.class.php');	//模板系统
$TMP = new Smarty;
$TMP->caching = $CON['TMP_CACHE'];
$TMP->cache_lifetime = $CON['TMP_CACHE_TIME'];
$GLOBALS['TMP'] = $TMP;
$GLOBALS['autoloads'] = array();
$modelnum = count($modellist);

foreach($classlist as $classname){	//装载系统内置类库
	if($classname!="." and $classname!=".."){
		$classname = FUNCTION_PATH.'class/'.$classname;
		if(is_file($classname)){
			$replace_arr = array(FUNCTION_PATH,FUNCTION_NAME,'class/');
			$class = str_replace($replace_arr,'',$classname);
			array_push($GLOBALS['autoloads'],$class);
		}
	}
}


if($modelnum > 0){
	foreach($modellist as $modelname){	//用户自定义类库
		if($modelname!="." and $modelname!=".."){
			$modelname = FUNCTION_PATH.$modelname;
			if(is_file($modelname)){
				$replace_arr = array(FUNCTION_PATH,FUNCTION_NAME);
				$model = str_replace($replace_arr,'',$modelname);
				array_push($GLOBALS['autoloads'],$model);
			}
		}	
	}
}

if($CON['PHP300_AUTO']){
	foreach($GLOBALS['autoloads'] as $val){	//自动实例化
		if($val!=''){
			$classname = $val.'_class';
			$GLOBALS[$val] = new $classname();
			$G[$val] = $GLOBALS[$val];
		}
	}	
}

set_error_handler(array($G['system'],"php300_error_handler"));	//处理错误

if($U['URL_WAY'] == '1'){
	url_routing($_SERVER['QUERY_STRING'],$U);	//URL路由
}

exec_url($_GET['c'],$_GET['f']);	//执行URL指针

/**
* M(模型名称)
* 存在返回模型对象,否则返回false
*/

function M($modelname){	//返回MODEL
	if($modelname==''){
		return false;
	}
	if(class_exists($modelname.'_class')){
		return $GLOBALS[$modelname];
	}else{
		return false;
	}
}

/**
* C(配置名称)
* 存在返回配置值,否则返回false
*/

function C($configname){	//返回配置信息
	if($GLOBALS['PHP300_CON'][$configname]!=''){
		return $GLOBALS['PHP300_CON'][$configname];
	}
	if($GLOBALS['DB'][$configname]!=''){
		return $GLOBALS['DB'][$configname];
	}
	if($GLOBALS['U'][$configname]!=''){
		return $GLOBALS['U'][$configname];
	}
	if(is_array($GLOBALS[$configname])){
		return $GLOBALS[$configname];
	}
	return false;
}

/**
* DB()
* 返回MYSQL类
*/

function DB(){
	if(is_object(M('mysql'))){
		return M('mysql');
	}
	return false;
}

/**
* exec_url(模型,方法)
* 执行处理URL参数,f参数默认等于index
*/

function exec_url($c,$f=''){
	$c = htmlspecialchars($c,ENT_QUOTES);
	$f = htmlspecialchars($f,ENT_QUOTES);
	$f = ($f)?($f):('index');
	$us = array('c'=>$c,'f'=>$f);
	use_controller($us);
}

/**
* use_controller(配置数组)
* 调用控制器
*/

function use_controller($option=array()){
	if($option['c']!=''){
		if(!isset($GLOBALS[$option['c']])){
			$GLOBALS['system']->get_error_page('找不到'.$option['c'].'控制器');
		}
		if(method_exists($GLOBALS[$option['c']],$option['f'])){
			$GLOBALS[$option['c']] -> $option['f']();
			exit();
		}else{
			$GLOBALS['system']->get_error_page('在'.$option['c'].'控制器中找到'.$option['f'].'方法');
		}
	}
}

/**
* url_routing(URL全路径,配置信息)
* 执行静态路由
*/

function url_routing($info,$U){
	$info = str_replace($U['URL_TAIL'],'',$info);
	$url_arr = explode($U['URL_MIDDLE'],$info);
	if($U['URL_MIDDLE']!='/'){	//处理分隔符
		array_unshift($url_arr,$U['URL_MIDDLE']);
		$url_arr[1] = str_replace('/','',$url_arr[1]);
	}
	$count = count($url_arr);
	if($count > 1){
		$urls .= 'c='.$url_arr[1].'&f='.$url_arr[2];
		$n = 0;
		for($i=3;$i<=$count;$i++){
			if($i != $n){
				$n = $i + 1;
				if($url_arr[$i]!=''){
					$n = $i + 1;
					$urls .= '&'.$url_arr[$i].'='.$url_arr[$n];
				}
			}	
		}
		$urls = $urls;
		parse_str($urls,$out);
		$us = array('c'=>$out['c'],'f'=>$out['f']);
		foreach($out as $key=>$val){
			if($key!='c' and $key!='f'){
				$_GET[$key] = $val;
			}
		}
		use_controller($us);
	}
}

/**
* firstload(类名称)
* 预加载继承类
*/

function firstload($classname){
	if($classname!=''){
		$classname = str_replace('_class','',$classname);
		loads($classname);
	}
}

/**
* loads(类名称)
* 承接加载
*/

function loads($classname){
	if($classname!='' and $classname!='php300'){
		if(in_array($classname,$GLOBALS['system_class_arr'])){
			$classname = 'class/'.$classname;
		}
		$classname = 'model/'.$classname.FUNCTION_NAME;
		if(file_exists($classname)){
			include_once($classname);
		}
	}
}

/**
* update_class(配置信息)
* 更新云类库
*/

function update_class($CON){
	if(!file_exists(FILE_PATH.'cache')){
		mkdir(FILE_PATH.'cache');
		}
	$cachefile = FILE_PATH.'cache/cache_class.php';
	$file_link = @fopen($cachefile,'w');
	$object_url = 'http://yun.php300.cn/framework.php?sn='.$CON['SN'].'&t='.$CON['TIME'];
	$cacheclass = @file_get_contents($object_url);
	if($cacheclass==''){
		echo ($CON['DEBUG'])?('当前SN无云程序或获取云程序失败'):('');
		}
	$success = substr($cacheclass,0,5);
	if($success!='error'){
		$cacheclass = urldecode($cacheclass);
		$cacheclass = '<?php class php300_class{ '.$cacheclass.' } ?>';
		@fwrite($file_link,$cacheclass);
		fclose($file_link);
		if($CON['CONFUSION']){
			$confusion =  @php_strip_whitespace($cachefile);
			@file_put_contents($cachefile,$confusion);
		}
	}else{
		fclose($file_link);
		echo ($CON['DEBUG'])?($cacheclass):('');
	}
}