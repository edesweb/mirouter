<?php
/**
 *
 *
 */
include '../../MiRouter.php';

const DEBUG = false;
const ROUTER_INI_FILENAME = 'router.ini';

$DEBUG = DEBUG || ($_GET['DEBUG']?? false);


if( !function_exists('eEcho') ) {
	function eEcho($s, $ret = false): string
	{
		if (is_string($s)) {
			$s = str_replace('<', '&lt;', $s);
			$s = str_replace('>', '&gt;', $s);
		}
		if ($ret === false) {
			echo '<code><pre>' . print_r($s, true) . '</pre></code><br>';
			return '';
		}
		return print_r($s, true);
	}
}

// Parse router.ini
$routerIni = parse_ini_file(ROUTER_INI_FILENAME, true);
if ($DEBUG)
{
	eEcho($routerIni);
	foreach ($routerIni['paths'] as $k => $v)
	{
		eEcho( '[paths] '.$k.'='.$v.' ----> '.realpath($v) );
	}
}

// Init MiRouter
$router = new MiRouter( $routerIni  );


header("HTTP/1.1 200 OK");

// Error
if ($router->returnCode == -1) {
	eEcho("($router->returnCode) $router->reason");
	exit;
}
// Authentication required
if ($router->returnCode == -2) {
	eEcho("($router->returnCode) $router->reason");
	exit;
}

// Ok, process file
if ($DEBUG) {
	eEcho('OK process the file $router->filename = ' . $router->filename );
}

include $router->filename;

exit;
