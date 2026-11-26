<?php

mb_internal_encoding('UTF-8');

$_POST['RunQuery']		= 0;

$sessionData = $_SESSION['nubuilder_session_data'] ?? null;

$DBHost			= $sessionData['DB_HOST'] ?? $nuConfigDBHost;
$DBName			= $sessionData['DB_NAME'] ?? $nuConfigDBName;
$DBPort			= $sessionData['DB_PORT'] ?? ($nuConfigDBPort ?? '3306');
$DBUser			= $sessionData['DB_USER'] ?? $nuConfigDBUser;
$DBPassword		= $sessionData['DB_PASSWORD'] ?? $nuConfigDBPassword;
$DBCharset		= $sessionData['DB_CHARSET'] ?? 'utf8';
$DBOptions		= $sessionData['DB_OPTIONS'] ?? $nuConfigDBOptions ?? null;
$charSet		= [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $DBCharset"];

if (is_array($DBOptions)) {
	$DBOptions = $DBOptions + $charSet;	// '+' keeps the integer PDO option keys; array_merge() would renumber them (and its result was being discarded)
} else {
	$DBOptions = $charSet;
}

try {
	$nuDB = new PDO("mysql:host=$DBHost;dbname=$DBName;charset=$DBCharset;port=$DBPort", $DBUser, $DBPassword, $DBOptions);
	$nuDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
	echo 'Connection to the nuBuilder database failed: ' . $e->getMessage();
	echo '<br><br>Verify and update the settings in nuconfig.php';
	echo '<br><br>Restart your browser after modifying nuconfig.php in order for changes to be reflected';
	die();
}

$GLOBALS['sys_table_prefix'] = [
	'access' => 'sal',
	'access_form' => 'slf',
	'access_php' => 'slp',
	'access_report' => 'srp',
	'browse' => 'sbr',
	'cloner' => 'clo',
	'code_snippet' => 'cot',
	'config' => 'cfg',
	'debug' => 'deb',
	'event' => 'sev',
	'file' => 'sfi',
	'form' => 'sfo',
	'format' => 'srm',
	'info' => 'inf',
	'note' => 'not',
	'note_category' => 'hoc',
	'object' => 'sob',
	'php' => 'sph',
	'report' => 'sre',
	'select' => 'sse',
	'select_clause' => 'ssc',
	'session' => 'sss',
	'setup' => 'set',
	'sso_login' => 'sso',
	'tab' => 'syt',
	'timezone' => 'stz',
	'translate' => 'trl',
	'user' => 'sus'
];

function nuRunQueryNoDebug($query, $params = [], $isInsert = false){

	global $nuDB;

	$stmt = $nuDB->prepare($query);

	try {
		$stmt->execute($params);
	}catch(PDOException $ex){
	}

	return $isInsert ? $nuDB->lastInsertId() : $stmt;

}

function nuRunQueryTest($query, $params = []){

	global $nuDB;

	$stmt = $nuDB->prepare($query);

	try {
		$stmt->execute($params);
	}catch(PDOException $ex){
		return $ex->getMessage();
	}

	return true;

}

function nuDebugMessageString($user, $message, $sql, $trace) {

		$debug	= "
		===PDO MESSAGE===

		$message

		===SQL===========

		$sql

		===BACK TRACE====

		$trace

		";

		return trim(preg_replace('/\t/', '', $debug));

}

function nuNoteQueryTime($start, $sql) {

	//-- Adds up the time spent in the database for this request and logs any single query slower than 2 seconds (see SLOW in nuapi.php)
	$sec = microtime(true) - $start;
	$GLOBALS['nuSqlTime']  = ($GLOBALS['nuSqlTime'] ?? 0) + $sec;
	$GLOBALS['nuSqlCount'] = ($GLOBALS['nuSqlCount'] ?? 0) + 1;

	if ($sec > 2) {
		nuLog('SLOWSQL', round($sec, 1) . 's', gmdate('H:i:s') . ' UTC ' . substr(trim(preg_replace('/\s+/', ' ', $sql)), 0, 400));
	}

}

function nuRunQuery($sql, $params = [], $isInsert = false){

	global $DBHost;
	global $DBName;
	global $DBUser;
	global $DBPassword;
	global $nuDB;
	global $DBCharset;

	if($sql == ''){
		$params 		= [];
		$params[0] 	= $DBHost;
		$params[1] 	= $DBName;
		$params[2] 	= $DBUser;
		$params[3] 	= $DBPassword;
		$params[4] 	= $nuDB;
		$params[4] 	= $DBCharset;
		return $params;
	}

	// nuLog($s, count($params)> 0 ? $params[0] : '');
	$stmt = $nuDB->prepare($sql);

	$nuQueryStart = microtime(true);

	try {
		$stmt->execute($params);
		nuNoteQueryTime($nuQueryStart, $sql);
	}catch(PDOException $ex){

		nuNoteQueryTime($nuQueryStart, $sql);
		$user 		= nuDebugUserId();
		$message	= $ex->getMessage();
		$array 		= debug_backtrace();
		$trace 		= '';

		$count = count($array);
		for($i = 0 ; $i < $count; $i ++){
			$trace .= $array[$i]['file'] . ' - line ' . $array[$i]['line'] . ' (' . $array[$i]['function'] . ")\n\n";
		}

		$debug = nuDebugMessageString($user, $message, $sql, $trace);

		$_POST['RunQuery']		= 1;
		nuDebug($debug);
		$_POST['RunQuery']		= 0;

		$GLOBALS['ERRORS'][]	= $debug;

		$error = new stdClass();
		$error->user = $user;
		$error->message = $message;
		$error->sql = $sql;
		$error->trace = $trace;
		$GLOBALS['LAST_ERROR'] = $error;

		return -1;

	}

	return $isInsert ? $nuDB->lastInsertId() : $stmt;

}

function nuGetLastError() {
	return $GLOBALS['LAST_ERROR'] ?? null;
}

function nuRunQueryString($sql, $sqlWithHK) {

	global $nuDevSelectQueryRunParameterised;

	if ($nuDevSelectQueryRunParameterised) {
		$args = [];
		$sqlWithHK = preg_replace_callback('/#(\'?)(.*?)(\'?)#/', function($match) use(&$count, &$args) {
			$args[] = $match[2];
			return '?';
		}, $sqlWithHK);
		$sqlWithHK = str_replace("'?'", "?", $sqlWithHK);
		
		foreach ($args as &$value) {
			$value = nuReplaceHashVariables('#' . $value. '#');
		}

		return nuRunQuery(nuSanitizeSqlQuery($sqlWithHK), $args);

	} else {
		return nuRunQuery(nuSanitizeSqlQuery($sql));
	}

}

function nuSanitizeSqlQuery($query) {

	// List of SQL commands to remove
	$patterns = array(
		'/DROP\s+TABLE/i',
		'/CREATE\s+TABLE/i',
		'/ALTER\s+TABLE/i',
		'/TRUNCATE\s+TABLE/i',
		'/INSERT\s+INTO\s+.*\s+SELECT/i',
		'/DELETE\s+FROM/i',
		'/UPDATE/i'
	);

	// Sanitize the query by removing the specified SQL commands
	return preg_replace($patterns, '', $query);

}

function db_is_auto_id($table, $primaryKey) {

	$query = "SHOW COLUMNS FROM `$table` WHERE `Field` = ?";
	$stmt = nuRunQuery($query, [$primaryKey]);

	if (db_num_rows($stmt) == 0) {
		nuDisplayError(nuTranslate("The primary key is invalid"). ": ". $primaryKey);
		return false;
	}

	$row = db_fetch_object($stmt);
	return $row->Extra == 'auto_increment';

}

function db_fetch($obj, $style = 'object', $fetchAll = false) {

	$fetchStyles = [
		'array' => [PDO::FETCH_ASSOC, 'array'],
		'keypairarray' => [PDO::FETCH_KEY_PAIR, 'array'],
		'object' => [PDO::FETCH_OBJ, 'boolean'],
		'row' => [PDO::FETCH_NUM, 'boolean'],
		'column' => [PDO::FETCH_COLUMN, 'array']
	];

	if (!isset($fetchStyles[$style])) {
		return false;
	}

	[$fetchStyle, $returnType] = $fetchStyles[$style];

	if (!is_object($obj)) {
		if ($returnType === 'array') {
			return [];
		} elseif ($returnType === 'boolean') {
			return false;
		}
	}

	return $fetchAll ? $obj->fetchAll($fetchStyle) : $obj->fetch($fetchStyle);

}

function db_fetch_array($obj){
	return db_fetch($obj, 'array');
}

function db_fetch_all_array($obj){
	return db_fetch($obj, 'array', true);
}

function db_fetch_key_pair_array($obj){
	return db_fetch($obj, 'keypairarray');
}

function db_fetch_object($obj){
	return db_fetch($obj, 'object');
}

function db_fetch_all_column($obj){
	return db_fetch($obj, 'column', true);
}

function db_fetch_all_object($obj){
	return db_fetch($obj, 'object', true);
}

function db_fetch_row($obj){
	return db_fetch($obj, 'row');
}

function db_update_value($table, $pk, $recordId, $column, $newValue) {

	$update = "UPDATE `$table` SET `$column` = ? WHERE `$pk` = ?";
	nuRunQuery($update, [$newValue, $recordId]);

}

function db_fetch_value($table, $pk, $recordId, $column) {

	$select = "SELECT `$column` FROM `$table` WHERE `$pk` = ?";
	$result = nuRunQuery($select, [$recordId]);
	if (db_num_rows($result) == 1) {
		$arr = db_fetch_array($result);
		return $arr[$column];
	} else {
		return false;
	}

}

function db_field_info($tableName) {

    $fields = [];
    $types = [];
    $primaryKeys = [];

    $query = "DESCRIBE `$tableName`";
    $result = nuRunQueryNoDebug($query);

	while ($row = db_fetch_row($result)) {
		$fields[] = $row[0];
		$types[] = $row[1];

		if ($row[3] === 'PRI') {
			$primaryKeys[] = $row[0];
		}
	}

	return [$fields, $types, $primaryKeys];

}

function db_field_names($n){

	$a	= [];
	$s	= "DESCRIBE `$n`";
	$t	= nuRunQuery($s);

	while($r = db_fetch_row($t)){
		$a[] = $r[0];
	}

	return $a;

}


function db_field_types($n){

	$a		= [];
	$s		= "DESCRIBE `$n`";
	$t		= nuRunQuery($s);

	while($r = db_fetch_row($t)){
		$a[] = $r[1];
	}

	return $a;

}

function db_field_exists($tableName, $fieldName) {

	$fields = db_field_names($tableName);
	return array_search($fieldName, $fields) != false;

}

function db_primary_key($n){

	$a		= [];
	$s		= "DESCRIBE `$n`";
	$t		= nuRunQuery($s);

	while($r = db_fetch_row($t)){

		if($r[3] == 'PRI'){
			$a[] = $r[0];
		}

	}

	return $a;

}

function nuDBQuote($s) {

	global $nuDB;
	return $nuDB->quote($s);

}

function db_num_rows($o) {

	if (!is_object($o)) {
		return 0;
	}
	return $o->rowCount();

}

function db_num_columns($o) {

	if (!is_object($o)) {
		return 0;
	}
	return $o->columnCount();

}

function db_quote($s) {

	global $nuDB;
	return $nuDB->quote($s);

}

function nuEncodeQueryRowResults($sql, $args = [], $prefixedData = []) {

	$stmt = nuRunQuery($sql, $args);
	$results = $prefixedData;
	while ($row = db_fetch_row($stmt)) {
		$results[] = $row;
	}
	return base64_encode(json_encode($results));

};

function nuViewExists($view) {

	$sql = "SELECT table_name as TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'VIEW' AND table_schema = DATABASE() AND TABLE_NAME = ?";
	$qry = nuRunQuery($sql, [$view]);

	return db_num_rows($qry);

}

function nuCanCreateView() {

	$dbName = $_SESSION['nubuilder_session_data']['DB_NAME'];

	$qry = nuRunQuery("SHOW GRANTS FOR CURRENT_USER()");
	while ($row = db_fetch_row($qry)) {

		$grants = $row[0];

		$createView	= nuStringContains("CREATE VIEW", $grants, true);
		$grant1		= nuStringContains("ALL PRIVILEGES ON `$dbName`.*", $grants, true);
		$grant2		= nuStringContains("ALL PRIVILEGES ON *.*", $grants, true);
		$grant3		= nuStringContains("ALL PRIVILEGES ON %", $grants, true);

		if ($createView || $grant1 || $grant2 || $grant3) {
			return true;
		}

	}

	return false;

}

function nuDebugUserId() {

	$nuDebugUserId = null;
	if (function_exists('nuHash')) {
		$hash = nuHash();
		$nuDebugUserId = isset($hash) && isset($hash['USER_ID']) ? $hash['USER_ID'] : null;
		$nuDebugUserId = $nuDebugUserId == null && isset($_POST['nuSTATE']['username']) ? $_POST['nuSTATE']['username'] : $nuDebugUserId;
	}

	return $nuDebugUserId;

}

function nuDebugResult($nuDebugMsg){

	if(is_object($nuDebugMsg)){
		$nuDebugMsg = print_r($nuDebugMsg,1);
	}

	$nuDebugId = nuID();

	$insert = "INSERT INTO zzzzsys_debug (zzzzsys_debug_id, deb_message, deb_added, deb_user_id) VALUES (:id , :message, :added, :user_id)";

	$params = [
		"id"		=> $nuDebugId,
		"message"	=> $nuDebugMsg,
		"added"		=> time(),
		"user_id"	=> nuDebugUserId()
	];

	nuRunQuery($insert, $params);

	$proc	= nuProcedure('NUDEBUGRESULTADDED');
	if($proc != '') { 
		eval(nuPrepEval($proc)); 
	}

	return $nuDebugId;

}

function nuDebug($a){

	$date				= date("Y-m-d H:i:s");
	$b					= debug_backtrace();
	$f					= $b[0]['file'];
	$l					= $b[0]['line'];
	$m					= "$date - $f line $l\n\n<br>\n";

	$nuSystemEval				= '';
	if ( isset($_POST['nuSystemEval']) ) {
		$nuSystemEval			= $_POST['nuSystemEval'];
	}
	$nuProcedureEval			= '';
	if ( isset($_POST['nuProcedureEval']) ) {
		$nuProcedureEval		= $_POST['nuProcedureEval'];
	}

	if($_POST['RunQuery'] == 1){
		$m				= "$date - SQL Error in <b>nuRunQuery</b>\n\n<br>\n" ;
	}else{
		$m				= "$date - $nuProcedureEval $nuSystemEval line $l\n\n<br>\n" ;
	}

	for($i = 0 ; $i < func_num_args() ; $i++){

		$p				= func_get_arg($i);

		$m				.= "\n[$i] : ";

		if(gettype($p) == 'object' or gettype($p) == 'array'){
			$m			.= print_r($p,1);
		}else{
			$m			.= $p;
		}

		$m				.= "\n";

	}

	nuLog('NUDEBUG', substr(trim(preg_replace('/\s+/', ' ', strip_tags($m))), 0, 800));	//-- also keep it in the log file, next to the other errors

	nuDebugResult($m);

}

function nuApplyStatementTimeout($callType) {

	//-- A database query that takes minutes keeps its PHP worker busy; a web host only has a few workers, so a handful of such requests make every other request fail with a 503.
	//-- (PHP's own max_execution_time does not help: it does not count the time spent waiting for the database.) MariaDB can stop a statement itself after N seconds.
	//-- Set $nuConfigMaxStatementTime in nuconfig.php: seconds, 0 = no limit. Default 60, below the 100+ seconds a host usually waits before it answers 503.
	//-- Not applied to runphp / runreport / runhtml (user started jobs that can legitimately run long).
	global $nuDB;

	if (in_array($callType, ['runphp', 'runreport', 'runhtml'], true)) {
		return;
	}

	$seconds = (int) ($GLOBALS['nuConfigMaxStatementTime'] ?? 60);
	if ($seconds <= 0) {
		return;
	}

	try {
		$nuDB->exec('SET SESSION max_statement_time = ' . $seconds);		//-- MariaDB (MySQL does not know this variable; the error is ignored)
	} catch (Throwable $e) {
	}

	//-- backstop for a PHP loop that never ends (database waiting time is not included)
	if ((int) ini_get('max_execution_time') === 0) {
		@set_time_limit(max($seconds * 2, 120));
	}

}

function nuLog($s1, $s2 = '', $s3 = '') {

	$dataToLog = [date("Y-m-d H:i:s"), $s1, $s2, $s3];

	$data = implode(" - ", $dataToLog);
	// $data = print_r($dataToLog, true);

	$dir = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR. 'temp' . DIRECTORY_SEPARATOR;
	$file = $dir . 'nulog.txt';
	if (@filesize($file) > 2097152) {		//-- keep the log small: roll over at 2 MB
		@rename($file, $dir . 'nulog.old.txt');
	}
	file_put_contents($file, $data.PHP_EOL , FILE_APPEND | LOCK_EX);

}

function nuPrepEval($code) {

	//-- PHP 7 treated an unknown bare word such as ACCESS_LEVEL_CODE as the string 'ACCESS_LEVEL_CODE' (with a warning). PHP 8 throws an Error instead.
	//-- Stored code written for PHP 7 therefore stops working after an upgrade. For upper case bare words that are not defined anywhere,
	//-- define a constant whose value is its own name, which is exactly what PHP 7 did. Nothing happens on PHP 7.
	if (PHP_VERSION_ID < 80000 || !is_string($code) || $code === '') {
		return $code;
	}

	static $seen = [];
	$key = md5($code);
	if (isset($seen[$key])) {
		return $seen[$key];
	}
	$seen[$key] = $code;

	$ignore = [];
	if (preg_match_all('/\bdefine\s*\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/i', $code, $m)) { foreach ($m[1] as $n) { $ignore[$n] = true; } }
	if (preg_match_all('/\bconst\s+([A-Za-z0-9_]+)\s*=/i', $code, $m)) { foreach ($m[1] as $n) { $ignore[$n] = true; } }

	$tokens = [];
	try {
		foreach (token_get_all('<?php ' . $code) as $t) {
			if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
			$tokens[] = $t;
		}
	} catch (Throwable $e) {
		return $code;
	}

	$skipBefore = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_CLASS, T_INTERFACE, T_TRAIT, T_NEW, T_NAMESPACE, T_USE, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS, T_NS_SEPARATOR, T_GOTO];
	if (defined('T_NULLSAFE_OBJECT_OPERATOR')) { $skipBefore[] = T_NULLSAFE_OBJECT_OPERATOR; }

	$reserved = ['true', 'false', 'null', 'self', 'parent', 'static', 'int', 'float', 'string', 'bool', 'array', 'callable', 'iterable', 'object', 'mixed', 'void', 'never', 'numeric'];

	$count = count($tokens);
	for ($i = 1; $i < $count; $i++) {

		$t = $tokens[$i];
		if (!is_array($t) || $t[0] !== T_STRING) { continue; }

		$name = $t[1];
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) || in_array(strtolower($name), $reserved, true) || isset($ignore[$name]) || defined($name)) { continue; }

		$prev = $tokens[$i - 1];
		$next = $tokens[$i + 1] ?? null;

		//-- Other than UPPER_CASE names (nearly always constants), only treat a word as a bare constant where a value is expected:
		//-- $row[station], f(station), $a . station . $b, $x = station;  This keeps class names, type hints and the like untouched.
		if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
			$before = is_array($prev) ? ($prev[0] === T_DOUBLE_ARROW ? '=>' : ($prev[0] === T_ECHO ? 'echo' : ($prev[0] === T_RETURN ? 'return' : ''))) : $prev;
			if (!in_array($before, ['[', '(', ',', '.', '=', '=>', 'echo', 'return'], true)) { continue; }
			$after = is_array($next) ? ($next[0] === T_DOUBLE_ARROW ? '=>' : '') : $next;
			if (!in_array($after, [']', ')', ',', ';', '.', '=>'], true)) { continue; }
		}

		if (is_array($prev) && in_array($prev[0], $skipBefore, true)) { continue; }
		if ($next === '(' || (is_array($next) && in_array($next[0], [T_DOUBLE_COLON, T_NS_SEPARATOR], true))) { continue; }
		if ($next === ':' && ($prev === '(' || $prev === ',')) { continue; }		//-- named argument

		define($name, $name);
		nuLogOnce('PHP8COMPAT', $name, "bare constant $name treated as the string '$name' (PHP 7 behaviour). Write it with quotes in the stored code.");

	}

	return $seen[$key] = nuPrepEvalFunctions($code);

}

function nuPrepEvalFunctions($code) {

	//-- PHP 8 throws a TypeError where PHP 7 gave a warning and carried on: in_array($x, null) and count(null). Stored code written for PHP 7 does this
	//-- without anyone noticing (an empty setting, a missing array). Calls to these two functions in stored code are routed to nuInArray() and nuCount(),
	//-- which behave as PHP 7 did (false and 0) and write one line to temp/nulog.txt saying where it happened. Only the function name changes: line numbers stay the same.
	if (!preg_match('/\b(in_array|count|sizeof)\s*\(/i', $code)) {
		return $code;
	}

	$map  = ['in_array' => 'nuInArray', 'count' => 'nuCount', 'sizeof' => 'nuCount'];
	$skip = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_NS_SEPARATOR];
	if (defined('T_NULLSAFE_OBJECT_OPERATOR')) { $skip[] = T_NULLSAFE_OBJECT_OPERATOR; }

	try {
		$all = token_get_all('<?php ' . $code);
	} catch (Throwable $e) {
		return $code;
	}

	$out     = '';
	$changed = false;
	$prev    = null;		//-- last token that is not whitespace or a comment
	$n       = count($all);

	for ($i = 0; $i < $n; $i++) {

		$t = $all[$i];

		if (is_array($t) && $t[0] === T_STRING && isset($map[strtolower($t[1])]) && !(is_array($prev) && in_array($prev[0], $skip, true))) {

			$j = $i + 1;
			while ($j < $n && is_array($all[$j]) && in_array($all[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { $j++; }

			if ($j < $n && $all[$j] === '(') {
				$out    .= $map[strtolower($t[1])];
				$changed = true;
				$prev    = $t;
				continue;
			}
		}

		$out .= is_array($t) ? $t[1] : $t;
		if (!is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { $prev = $t; }

	}

	return $changed ? substr($out, 6) : $code;		//-- 6 = length of the '<?php ' added above

}

function nuInArray($needle, $haystack, $strict = false) {

	if (!is_array($haystack)) {
		nuPhp8Note('in_array', $haystack, 'false');
		return false;
	}

	return in_array($needle, $haystack, $strict);

}

function nuCount($value, $mode = COUNT_NORMAL) {

	if (is_array($value) || $value instanceof Countable) {
		return count($value, $mode);
	}

	nuPhp8Note('count', $value, $value === null ? '0' : '1');
	return $value === null ? 0 : 1;

}

function nuPhp8Note($function, $value, $returned) {

	$b   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
	$loc = ($b[1]['file'] ?? '?') . ':' . ($b[1]['line'] ?? 0);
	nuLogOnce('PHP8COMPAT', $function . ' ' . $loc, "$function() was given " . gettype($value) . " instead of an array at $loc (PHP 7 carried on and returned $returned). Fix the stored code (e.g. use ?? []).");

}

function nuLogOnce($type, $key, $message) {

	//-- one line per $key in temp/nulog.txt (remembered in temp/logonce.txt)
	$file  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'logonce.txt';
	$known = is_file($file) ? (array) file($file, FILE_IGNORE_NEW_LINES) : [];
	$id    = $type . ':' . $key;

	if (!in_array($id, $known, true)) {
		@file_put_contents($file, $id . PHP_EOL, FILE_APPEND | LOCK_EX);
		nuLog($type, $key, $message);
	}

}

function nuID(){

	global $DBUser;
	$i	= uniqid();
	$s	= md5($i);

	while($i == uniqid()){ usleep(1); }

	$prefix = $DBUser == 'nudev' ? 'nu' : '';
	return $prefix.uniqid().$s[0].$s[1];

}

?>
