<?php
/**
 *  mysqli_class.php MYSQLI数据库类
 *
 * @copyright			(C) 2015-2016 PHP300
 * @license				http://framework.php300.cn
 * @lastmodify			2016-05-05
 */
class mysqli_class extends system_class{
	
	/**
	 * 数据库配置信息
	 */
	private $config = null;
	
	/**
	 * 数据库连接资源句柄
	 */
	public $link = null;
	
	/**
	 * 最近一次查询资源句柄
	 */
	public $lastqueryid = null;
	
	/**
	 *  统计数据库查询次数
	 */
	public $querycount = 0;
	
	public $sql = '';
	
	public function __construct() {

	}
	
	/**
	 * 打开数据库连接,有可能不真实连接数据库
	 * @param $config	数据库连接参数
	 * 			
	 * @return void
	 */
	public function open($config) {
		$this->config = $config;
		if($config['autoconnect']) {
			$this->connect();
		}
	}

	/**
	 * 真正开启数据库连接
	 * 			
	 * @return void
	 */
	public function connect() {
		 $this->link = mysqli_connect($this->config['hostname'].':'.$this->config['port'],$this->config['username'],$this->config['password'],$this->config['database']);
		 if(mysqli_connect_errno()){
		 	$this->halt('数据库连接失败');
			return false;
		 };
		 mysqli_query($this->link,"set names ".$this->config['charset']);
		 return $this->link;
	}

	/**
	 * 数据库查询执行方法
	 * @param $sql 要执行的sql语句
	 * @return 查询资源句柄
	 */
	private function execute($sql) {
		if(C('sqldebug')){
			echo $sql;
		}
		
		$this->sql = $sql;
		
		$this->lastqueryid = mysqli_query($this->link,$sql) or $this->halt(mysqli_connect_error(), $sql);

		$this->querycount++;
		return $this->lastqueryid;
	}

	/**
	 * 执行sql查询
	 * @param $data 		需要查询的字段值[例`name`,`gender`,`birthday`]
	 * @param $table 		数据表
	 * @param $where 		查询条件[例`name`='$name']
	 * @param $limit 		返回结果范围[例：10或10,10 默认为空]
	 * @param $order 		排序方式	[默认按数据库默认方式排序]
	 * @param $group 		分组方式	[默认为空]
	 * @param $key 			返回数组按键名排序
	 * @return array		查询结果集数组
	 */
	public function select($data, $table, $where = '', $limit = '', $order = '', $group = '', $key = '') {
		$where = $where == '' ? '' : ' WHERE '.$where;
		$order = $order == '' ? '' : ' ORDER BY '.$order;
		$group = $group == '' ? '' : ' GROUP BY '.$group;
		$limit = $limit == '' ? '' : ' LIMIT '.$limit;
		$field = explode(',', $data);
		array_walk($field, array($this, 'add_special_char'));
		$data = implode(',', $field);
		
		$sql = 'SELECT '.$data.' FROM `'.$this->config['database'].'`.`'.$table.'`'.$where.$group.$order.$limit;
		$this->execute($sql);

		$datalist = array();
		while(($rs = $this->fetch_next()) != false) {
			if($key) {
				$datalist[$rs[$key]] = $rs;
			} else {
				$datalist[] = $rs;
			}
		}
		$this->free_result();
		return $datalist;
	}

	/**
	 * 获取单条记录查询
	 * @param $data 		需要查询的字段值[例`name`,`gender`,`birthday`]
	 * @param $table 		数据表
	 * @param $where 		查询条件
	 * @param $order 		排序方式	[默认按数据库默认方式排序]
	 * @param $group 		分组方式	[默认为空]
	 * @return array/null	数据查询结果集,如果不存在，则返回空
	 */
	public function get_one($data, $table, $where = '', $order = '', $group = '') {
		$where = $where == '' ? '' : ' WHERE '.$where;
		$order = $order == '' ? '' : ' ORDER BY '.$order;
		$group = $group == '' ? '' : ' GROUP BY '.$group;
		$limit = ' LIMIT 1';
		$field = explode( ',', $data);
		array_walk($field, array($this, 'add_special_char'));
		$data = implode(',', $field);

		$sql = 'SELECT '.$data.' FROM `'.$this->config['database'].'`.`'.$table.'`'.$where.$group.$order.$limit;
		$this->execute($sql);
		$res = $this->fetch_next();
		$this->free_result();
		return $res;
	}
	
	/**
	 * 遍历查询结果集
	 * @return array
	 */
	public function fetch_next() {
		$res = mysqli_fetch_array($this->lastqueryid,MYSQLI_ASSOC);
		if(!$res) {
			$this->free_result();
		}
		return $res;
	}
	
	/**
	 * 释放查询资源
	 * @return void
	 */
	public function free_result() {
		if(is_resource($this->lastqueryid)) {
			mysqli_free_result($this->lastqueryid);
			$this->lastqueryid = null;
		}
	}
	
	/**
	 * 直接执行sql查询
	 * @param $sql							查询sql语句
	 * @return	boolean/query resource		如果为查询语句，返回资源句柄，否则返回true/false
	 */
	public function query($sql) {
		return $this->execute($sql);
	}
	
	/**
	 * 执行添加记录操作
	 * @param $data 		要增加的数据，参数为数组。数组key为字段值，数组值为数据取值
	 * @param $table 		数据表
	 * @return boolean
	 */
	public function insert($data, $table, $return_insert_id = false, $replace = false) {
		if(!is_array( $data ) || $table == '' || count($data) == 0) {
			return false;
		}
		
		$fielddata = array_keys($data);
		$valuedata = array_values($data);
		array_walk($fielddata, array($this, 'add_special_char'));
		array_walk($valuedata, array($this, 'escape_string'));
		
		$field = implode (',', $fielddata);
		$value = implode (',', $valuedata);

		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
		$sql = $cmd.' `'.$this->config['database'].'`.`'.$table.'`('.$field.') VALUES ('.$value.')';
		$return = $this->execute($sql);
		return $return_insert_id ? $this->insert_id() : $return;
	}
	
	/**
	* 添加数据
	* @param 数据 $data
	* @param 表名 $table
	* @param 是否插入ID $return_insert_id
	* @param 替换 $replace
	* 
	* @return bool
	*/
	public function add($data, $table, $return_insert_id = false, $replace = false){
		$this->insert($data,$table,$return_insert_id,$replace);
	}
	
	/**
	 * 获取最后一次添加记录的主键号
	 * @return int 
	 */
	public function insert_id() {
		return mysqli_insert_id($this->link);
	}
	
	/**
	 * 执行更新记录操作
	 * @param $data 		要更新的数据内容，参数可以为数组也可以为字符串，建议数组。
	 * 						为数组时数组key为字段值，数组值为数据取值
	 * 						为字符串时[例：`name`='phpcms',`hits`=`hits`+1]。
	 *						为数组时[例: array('name'=>'phpcms','password'=>'123456')]
	 *						数组可使用array('name'=>'+=1', 'base'=>'-=1');程序会自动解析为`name` = `name` + 1, `base` = `base` - 1
	 * @param $table 		数据表
	 * @param $where 		更新数据时的条件
	 * @return boolean
	 */
	public function update($data, $table, $where = '') {
		if($table == '' or $where == '') {
			return false;
		}

		$where = ' WHERE '.$where;
		$field = '';
		if(is_string($data) && $data != '') {
			$field = $data;
		} elseif (is_array($data) && count($data) > 0) {
			$fields = array();
			foreach($data as $k=>$v) {
				switch (substr($v, 0, 2)) {
					case '+=':
						$v = substr($v,2);
						if (is_numeric($v)) {
							$fields[] = $this->add_special_char($k).'='.$this->add_special_char($k).'+'.$this->escape_string($v, '', false);
						} else {
							continue;
						}
						
						break;
					case '-=':
						$v = substr($v,2);
						if (is_numeric($v)) {
							$fields[] = $this->add_special_char($k).'='.$this->add_special_char($k).'-'.$this->escape_string($v, '', false);
						} else {
							continue;
						}
						break;
					default:
						$fields[] = $this->add_special_char($k).'='.$this->escape_string($v);
				}
			}
			$field = implode(',', $fields);
		} else {
			return false;
		}

		$sql = 'UPDATE `'.$this->config['database'].'`.`'.$table.'` SET '.$field.$where;
		return $this->execute($sql);
	}
	
	
	/**
	* 更新数据
	* @param 字段 $data
	* @param 表名 $table
	* @param 条件 $where
	* 
	* @return bool
	*/
	public function save($data, $table, $where = ''){
		$this->update($data,$table,$where);
	}
	
	/**
	 * 执行删除记录操作
	 * @param $table 		数据表
	 * @param $where 		删除数据条件,不充许为空。
	 * 						如果要清空表，使用empty方法
	 * @return boolean
	 */
	public function delete($table, $where) {
		if ($table == '' || $where == '') {
			return false;
		}
		$where = ' WHERE '.$where;
		$sql = 'DELETE FROM `'.$this->config['database'].'`.`'.$table.'`'.$where;
		return $this->execute($sql);
	}
	
	/**
	* 删除数据
	* @param 表名 $table
	* @param 条件 $where
	* 
	* @return bool
	*/
	public function  del($table, $where){
		$this->delete($table, $where);
	}
	
	/**
	 * 获取最后数据库操作影响到的条数
	 * @return int
	 */
	public function affected_rows() {
		return mysqli_affected_rows($this->link);
	}
	
	/**
	 * 获取数据表主键
	 * @param $table 		数据表
	 * @return array
	 */
	public function get_primary($table) {
		$this->execute("SHOW COLUMNS FROM $table");
		while($r = $this->fetch_next()) {
			if($r['Key'] == 'PRI') break;
		}
		return $r['Field'];
	}

	/**
	 * 获取表字段
	 * @param $table 		数据表
	 * @return array
	 */
	public function get_fields($table) {
		$fields = array();
		$this->execute("SHOW COLUMNS FROM $table");
		while($r = $this->fetch_next()) {
			$fields[$r['Field']] = $r['Type'];
		}
		return $fields;
	}

	/**
	 * 检查不存在的字段
	 * @param $table 表名
	 * @return array
	 */
	public function check_fields($table, $array) {
		$fields = $this->get_fields($table);
		$nofields = array();
		foreach($array as $v) {
			if(!array_key_exists($v, $fields)) {
				$nofields[] = $v;
			}
		}
		return $nofields;
	}

	/**
	 * 检查表是否存在
	 * @param $table 表名
	 * @return boolean
	 */
	public function table_exists($table) {
		$tables = $this->list_tables();
		return in_array($table, $tables) ? 1 : 0;
	}
	
	public function list_tables() {
		$tables = array();
		$this->execute("SHOW TABLES");
		while($r = $this->fetch_next()) {
			$tables[] = $r['Tables_in_'.$this->config['database']];
		}
		return $tables;
	}

	/**
	 * 检查字段是否存在
	 * @param $table 表名
	 * @return boolean
	 */
	public function field_exists($table, $field) {
		$fields = $this->get_fields($table);
		return array_key_exists($field, $fields);
	}

	public function num_rows($sql) {
		$this->lastqueryid = $this->execute($sql);
		return mysqli_num_rows($this->lastqueryid);
	}

	public function num_fields($sql) {
		$this->lastqueryid = $this->execute($sql);
		return mysqli_num_fields($this->lastqueryid);
	}

	public function error() {
		return @mysqli_error($this->link);
	}

	public function errno() {
		return intval(@mysqli_errno($this->link)) ;
	}

	public function version() {
		if(!is_resource($this->link)) {
			$this->connect();
		}
		return mysqli_get_server_info($this->link);
	}

	public function close() {
		if (is_resource($this->link)) {
			@mysqli_close($this->link);
		}
	}
	
	public function halt($message = '', $sql = '') {
		$sql = ($sql!='')?($sql):('无');
		$message = ($message!='')?($message):('无');
		$errno = ($this->errno()!='')?($this->errno()):('未知');
		$error = ($this->error()!='')?($this->error()):('未知');
		if($this->config['debug']) {
			$this->errormsg = "数据库出错<br />SQL语句：{$sql}<br />错误原因：{$error}<br />错误代码：".$errno."<br />信息：".$message;
			$msg = $this->errormsg;
			$this->get_error_page($msg);
		} else {
			return false;
		}
	}

	/**
	 * 对字段两边加反引号，以保证数据库安全
	 * @param $value 数组值
	 */
	public function add_special_char(&$value) {
		if('*' == $value || false !== strpos($value, '(') || false !== strpos($value, '.') || false !== strpos ( $value, '`')) {//不处理包含* 或者 使用了sql方法。
		} else {
			$value = '`'.trim($value).'`';
		}
		if (preg_match("/\b(select|insert|update|delete)\b/i", $value)) {
			$value = preg_replace("/\b(select|insert|update|delete)\b/i", '', $value);
		}
		return $value;
	}
	
	/**
	 * 对字段值两边加引号，以保证数据库安全
	 * @param $value 数组值
	 * @param $key 数组key
	 * @param $quotation 
	 */
	public function escape_string(&$value, $key='', $quotation = 1) {
		if ($quotation) {
			$q = '\'';
		} else {
			$q = '';
		}
		$value = $q.$value.$q;
		return $value;
	}
	
	/**
	* 返回最后一次操作的SQL语句
	* @return sql string
	*/
	public function get_sql(){
		return $this->sql;
	}
}
?>