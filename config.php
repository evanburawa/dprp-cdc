<?php
require_once("../../redcap_connect.php");

if(!defined("ENVIRONMENT")) {
	if (is_file('/app001/victrcore/lib/Victr/Env.php')) include_once('/app001/victrcore/lib/Victr/Env.php');
	if (class_exists("Victr_Env")) {
		$envConf = Victr_Env::getEnvConf();

		if ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_PROD) {
			define("ENVIRONMENT", "PROD");
			define("PROJECT_ID", 93783);	// real project on prod
		} elseif ($envConf[Victr_Env::ENV_CURRENT] === Victr_Env::ENV_DEV) {
			define("ENVIRONMENT", "TEST");
			// define("PROJECT_ID", 35);
		}
	} else {
		define("ENVIRONMENT", "DEV");
		define("PROJECT_ID", 35);
	}
}
