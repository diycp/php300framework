<?php
/*
* URL路由解析
* 解析的第一个参数为C(控制器),第二个参数为F(方法),如果不使用路由则不进行解析
*/
$U['URL_WAY'] = '1';	//(0=>不使用路由,1=>使用基础模式)默认为1;

$U['URL_MIDDLE'] = '/';	//分隔符(禁止设置成：&、?、!、~、*、: 等特殊符号)

$U['URL_TAIL'] = '.html';	//静态化后缀，当设置为空将不需要使用后缀

$GLOBALS['U'] = $U;
?>