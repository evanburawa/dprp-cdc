<?php
require_once(__DIR__ . "/../../redcap_connect.php");
$plugin_dir = __DIR__;

if(!defined("ENVIRONMENT")) {
	if (is_file('/app001/victrcore/lib/Victr/Env.php')) include_once('/app001/victrcore/lib/Victr/Env.php');
	if (class_exists("Victr_Env")) {
		$envConf = Victr_Env::getEnvConf();

		if ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_PROD) {
			define("ENVIRONMENT", "PROD");
			define("PROJECT_ID", 93783);	// real project on prod
		} elseif ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_DEV) {
			define("ENVIRONMENT", "TEST");
			define("PROJECT_ID", 1498);
		}
	} else {
		define("ENVIRONMENT", "DEV");
		define("PROJECT_ID", 46);
	}
}

file_put_contents("$plugin_dir/log.txt", "logging...\n");
function _log($text) {
	file_put_contents("$plugin_dir/log.txt", "$text\n", FILE_APPEND);
}