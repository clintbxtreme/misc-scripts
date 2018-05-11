<?php

require_once "~/tools.php";
header("Content-type: text/plain");
date_default_timezone_set("America/Phoenix");

$tools = new Tools();
$tools->log_file = 'openGarageControl/log';

$slack_token = 'slack_token';
$secret = 'secret';

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
	if ($HTTP_RAW_POST_DATA) {
		$_REQUEST = array_merge($_REQUEST, json_decode($HTTP_RAW_POST_DATA, true));
	}
}

if (isset($_REQUEST['token']) && $_REQUEST['token'] == $slack_token) {
	$_REQUEST['action'] = $_REQUEST['text'];
	$tools->slack_url = $_REQUEST['response_url'];
} elseif (!isset($_REQUEST['scrt']) || $_REQUEST['scrt'] != $secret) {
	exit('Permissions Denied');
}

switch ($_REQUEST['action']) {
	case 'open':
		$tools->triggerOpenGarage('open');
		break;

	case 'close':
		$tools->triggerOpenGarage('close');
		break;

	case 'status':
		list($status, $vehicle) = $tools->getOpenGarageStatus();
		$status = ($status == 'close') ? 'closed' : $status;
		$tools->postToSlack("Garage Door is {$status} {$vehicle}");
	default:
		exit;
		break;
}