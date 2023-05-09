<?php
ini_set("display_errors", 0);
ini_set('soap.wsdl_cache_enabled',0);
ini_set('soap.wsdl_cache_ttl',0);
date_default_timezone_set("Asia/Vladivostok");
session_start();

require_once __DIR__ . '/vendor/autoload.php';

require_once "app/php/version.php";
require_once "app/php/const.php";
require_once "app/php/bonusapp.class.php";
require_once "app/php/lmx.class.php";
require_once "app/php/uty.class.php";
require_once "app/php/src.class.php";
require_once "app/php/libs/QTSMS.class.php";
require_once "app/php/libs/debug.php";

$BonusApp = new BonusApp();
$BonusApp->route();
