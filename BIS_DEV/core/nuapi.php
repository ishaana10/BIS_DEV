<?php
	header("Content-Type: application/json");
	header("Cache-Control: no-cache, must-revalidate");

	$nuState = $_POST['nuSTATE'] ?? null;
	if ($nuState == null && !empty($_FILES["file"])) {
		require_once('nuupload.php');
		echo nuUploadFile();
		return;
	}

	if ($nuState == null) {
		http_response_code(400);
		return;
	}

	$_POST['nuSTATE'] = json_decode($nuState, true);

	require_once('../nuconfig.php');

	// Compress the response (getform responses can be large). Set $nuConfigDisableGzip = true; in nuconfig.php to switch this off.
	if (empty($nuConfigDisableGzip) && extension_loaded('zlib') && !ini_get('zlib.output_compression') && strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
		ob_start('ob_gzhandler');
	}
	require_once('nusecurity.php');
	require_once('nusession.php');
	require_once('nucommon.php');
	require_once('nuform.php');
	require_once('nudata.php');
	require_once('nudrag.php');
	require_once('nudatabase.php');
	
	nuIncludeConfigPHPFiles();

	$_POST['nuCounter']						= rand(0, 999);
	$_POST['nuErrors']						= [];

	$user									= nuGetUserAccess();
	$state									= $_POST['nuSTATE'];

	$_POST['nuHash']						= array_merge($user, nuSetHashList($state));
	$callType								= $state['call_type'];

	$globalAccess 							= nuGlobalAccess(false);
	$refreshCache							= nuGetJSONData('REFRESH_CACHE') == '1';

	if ($refreshCache && !$globalAccess)	{
		nuLoginSetupNOTGlobeadmin(false);
		nuUpdateTableSchema($callType, true);
		nuUpdateFormSchema(true);
	};

	if (empty($user)) nuDie(nuTranslate('Your session has timed out.'));

	$formAndSessionData						= nuGatherFormAndSessionData($user['HOME_ID'], $globalAccess);
	$sessionData							= $_SESSION['nubuilder_session_data'];
	$formId									= $formAndSessionData->form_id;
	$recordId								= $formAndSessionData->record_id;

	$_POST['FORM_ID'] 						= $formId;

	// 2FA, check authentication status.
	if ((($globalAccess && nuObjKey($sessionData,'2FA_ADMIN')) || (!$globalAccess && nuObjKey($sessionData,'2FA_USER'))) && nuObjKey($sessionData,'SESSION_2FA_STATUS') == 'PENDING') {
		if ($formAndSessionData->form_id != $sessionData['2FA_FORM_ID'] && $callType != 'runhiddenphp') {
			nuDisplayError(nuTranslate('Access denied. Authentication Pending.'));
		}
	}

	// Release the PHP session file lock for read-only calls, so one slow request
	// does not queue every other request from the same user (A2 "user cap" freeze).
	// $_SESSION stays readable in memory; login/logout/update/run* calls keep the lock.
	$readOnlyCalls = ['getform', 'getphp', 'getreport', 'getlookupid', 'getlookupcode', 'getfile'];
	//-- Set $nuConfigKeepSessionLock = true; in nuconfig.php to keep the old behaviour (one request per user at a time).
	if (empty($nuConfigKeepSessionLock) && in_array($callType, $readOnlyCalls, true) && session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}

	// Log requests slower than 5 seconds to temp/nulog.txt (remove once the cause is found)
	$nuT0 = microtime(true);
	register_shutdown_function(function () use ($nuT0, $callType, $formId) {
		$sec = microtime(true) - $nuT0;
		$peakMb = memory_get_peak_usage(true) / 1048576;
		$err = error_get_last();
		$fatal = $err !== null && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR], true);
		if ($sec > 5 || $peakMb > 96 || $fatal) {
			nuLog('SLOW', round($sec, 1) . 's', "call=$callType form=$formId peak=" . round($peakMb) . "MB" . ($fatal ? ' FATAL: ' . $err['message'] : ''));
		}
	});

	$_POST['nuHash']['PREVIOUS_RECORD_ID'] 	= $recordId;
	$_POST['nuHash']['RECORD_ID'] 			= $recordId;
	$_POST['nuHash']['FORM_ID'] 			= $formId;
	$_POST['nuHash']['nuFORMdata']			= json_decode(json_encode(nuObjKey($_POST['nuSTATE'],'nuFORMdata')));		//-- this holds data from an Edit Form
	$_POST['nuHash']['TABLE_ID'] 			= nuTT();
	$_POST['nuHash']['SESSION_ID'] 			= $sessionData['SESSION_ID'];
	$_POST['nuValidate']					= [];
	$_POST['nuCallback']					= '';
	$_POST['nuAfterEvent']					= false;

	$f										= new stdClass;
	$f->forms[0]							= new stdClass;

	if(count($formAndSessionData->errors) == 0){

		if($callType == 'logout') nuLogout();
		elseif($callType == 'login') nuRunLoginProcedure('nuStartup');
		elseif($callType == 'ssologin') nuSsoLoginCheckParams();

		if($callType == 'getform' || $callType == 'getphp' || $callType == 'login' || $callType == 'getreport') {
		   nuBeforeEdit($formId, $recordId);
		   $f->forms[0] = nuGetFormObject($formId, $recordId, 0);
		}
		elseif($callType == 'getlookupid') {
		   $f->forms[0] = nuGetAllLookupValues();
		}
		elseif($callType == 'getlookupcode') {
		   $f->forms[0] = nuGetAllLookupList();
		}
		elseif($callType == 'getfile') {
		   $f->forms[0]->JSONfile = nuGetFile();
		}
		elseif($callType == 'runhiddenphp') {
		   $f->forms[0] = nuRunPHPHidden($recordId);
		}
		elseif($callType == 'runphp') {
		   $f->forms[0]->id = nuRunPHP($formId);
		}
		elseif($callType == 'runreport') {
		   $f->forms[0]->id = nuRunReport($formId);
		}
		elseif($callType == 'runhtml') {
		   $f->forms[0]->id = nuRunHTML();
		}
		elseif($callType == 'update') {
		   $f->forms[0]->record_id = nuUpdateDatabase();
		}
		elseif($callType == 'nudragsave') {
		   $f->forms[0] = nuDragSave($state);
		}
		elseif($callType == 'systemupdate') {
		   $f->forms[0]->id = nuRunSystemUpdate();
		}

	}

	if($callType != 'logout')	{

		if (!isset($f->forms[0])) { // Form deleted etc.
			json_encode($f);
			return;
		}

		$f->forms[0]->after_event				= nuObjKey($_POST, 'nuAfterEvent');
		$f->forms[0]->user_id					= nuObjKey($user, 'USER_ID', null);
		$f->forms[0]->login_name				= nuObjKey($user, 'LOGIN_NAME', null);
		$f->forms[0]->user_team					= $globalAccess ? '' : nuObjKey($user, 'USER_TEAM', null);
		$f->forms[0]->user_department			= $globalAccess ? '' : nuObjKey($user, 'USER_DEPARTMENT', null);
		$f->forms[0]->user_position				= $globalAccess ? '' : nuObjKey($user, 'USER_POSITION', null);
		$f->forms[0]->user_code					= $globalAccess ? '' : nuObjKey($user, 'USER_CODE', null);
		$f->forms[0]->user_additional1			= $globalAccess ? '' : nuObjKey($user, 'USER_ADDITIONAL1', null);
		$f->forms[0]->user_additional2			= $globalAccess ? '' : nuObjKey($user, 'USER_ADDITIONAL2', null);
		$f->forms[0]->user_a11y					= $globalAccess ? '' : nuObjKey($user, 'USER_A11Y', null);
		$f->forms[0]->user_name					= $globalAccess ? '' : nuUser($user['USER_ID'])->sus_name;
		$f->forms[0]->home_id					= $sessionData['HOME_ID'];
		$f->forms[0]->language					= $sessionData['language'];

		$f->forms[0]->access_level_id			= $user['USER_GROUP_ID'];
		$f->forms[0]->access_level_code			= $user['ACCESS_LEVEL_CODE'];
		$f->forms[0]->access_level_group		= $user['ACCESS_LEVEL_GROUP'];

		$f->forms[0]->database					= $sessionData['DB_NAME'];
		$f->forms[0]->dimensions				= $formAndSessionData->dimensions ?? null;
		$f->forms[0]->translation				= $formAndSessionData->translation;

		$f->forms[0]->tableSchema				= nuUpdateTableSchema($callType, $refreshCache && $globalAccess);
		$f->forms[0]->viewSchema				= nuBuildViewSchema();
		$f->forms[0]->formSchema				= nuUpdateFormSchema($refreshCache  && $globalAccess);

		//-- The table/form schemas (~40 KB) rarely change: if the browser already holds this exact version, do not send it again
		$schemaHash								= md5(json_encode($f->forms[0]->tableSchema) . '|' . json_encode($f->forms[0]->formSchema));
		if ($f->forms[0]->tableSchema !== null && ($state['schema_hash'] ?? '') === $schemaHash) {
			unset($f->forms[0]->tableSchema, $f->forms[0]->formSchema);
			$f->forms[0]->schema_unchanged		= 1;
		}
		$f->forms[0]->schema_hash				= $schemaHash;

		if ($refreshCache) nuSetJSONData('REFRESH_CACHE','0');

		$f->forms[0]->session_id				= $sessionData['SESSION_ID'];

		$f->forms[0]->callback					= nuSetGlobalPropertiesJS()."\n".nuObjKey($_POST,'nuCallback');
		$f->forms[0]->errors					= nuObjKey($_POST,'nuErrors');
		$f->forms[0]->log_again					= nuObjKey($_POST,'nuLogAgain');
		$f->forms[0]->global_access				= $globalAccess ? '1' : '0';
		$f->forms[0]->data_mode					= $globalAccess ? null : nuGetFormPermission($formId,'slf_data_mode');
		$f->forms[0]->form_type_access			= $globalAccess ? null : nuGetFormPermission($formId,'slf_form_type');
		$f->forms[0]->is_demo					= nuDemo(false);
		$f->forms[0]->remember_me_2fa			= $sessionData['2FA_REMEMBER_ME'];
		$f->forms[0]->token_validity_time_2fa	= $sessionData['2FA_TOKEN_VALIDITY_TIME'];
		$f->forms[0]->form_access				= $GLOBALS['nuSetup']->set_denied;
		$f->forms[0]->javascript				= nuObjKey($GLOBALS,'EXTRAJS');
		$f->forms[0]->style						= nuObjKey($GLOBALS,'STYLE');
		$f->forms[0]->javascript_bc				= nuObjKey($GLOBALS,'EXTRAJS_BC');
		$f->forms[0]->target					= nuObjKey($state,'target');

		$buttons								= nuButtons($formId, $state);
		$f->forms[0]->buttons					= $buttons[0];
		$f->forms[0]->run_code					= $buttons[1];
		$f->forms[0]->run_description			= $buttons[2];

	}

	$j											= json_encode($f->forms[0]);

	print $j;
?>
