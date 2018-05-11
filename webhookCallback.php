<?php

header("Content-type: text/plain");
date_default_timezone_set("America/Phoenix");

$secret = 'hrtkwertlkqjbglakjrbg';
if (!isset($_REQUEST['scrt']) || $_REQUEST['scrt'] != $secret) {
	exit('Permissions Denied');
}

require_once "~/tools.php";
$tools = new Tools();
$tools->log_file = 'webhookCallback/'.date('Y-m-d').'.log';

$action = $_REQUEST['action'];
switch ($action) {
	case 'work_exited':
		$tools->logToFile('exited work');
		if (time() < mktime(15,0,0) || time() > mktime(18,0,0)) $tools->logToFile('wrong time left', true);
		$loves = array('sweetie','baby doll','gorgeous','hun','hunny','boo','hot mama','honey','baby cakes','cuddle bug','sexy','babe','baby','cutie','hottie','honey buns','my love','princess','beautiful','doll face','baby girl','hot stuff','lover','pookie','pretty lady','mama bear','mama','love','sweetheart','love bug','my queen',);
		$love = 'Heading home '.$loves[date('j')-1];
		// $fields = array(
		// 	'text' =>$love,
		// 	'as_user' =>true,
		// 	'channel' =>'D02UY5Z98',
		// 	// 'channel' =>'#misc',
		// );
		// postToSlack($fields);

		$tools->sendText('6021234567', $love);
		break;

	case 'frys_entered':
		$tools->logToFile('entered frys');
		$tools->sendText('4801234567', 'Get free Frys deals');
		break;

	case 'home_entered':
	case 'garage_open':
		$tools->logToFile('entered home');
		$tools->triggerOpenGarage('open');
		if (!in_array(date('N'), array(3, 7))) {
			$tools->logToFile('not right day');
		} else {
			$trash_type = (date('N') == 3) ? "recycle" : "regular";
			$text = "take out {$trash_type} trash";
			$tools->sendText('4801234567', $text);
		}
		break;

	case 'home_exited':
	case 'garage_close':
		$tools->logToFile('exited home');
		$tools->triggerOpenGarage('close');
		break;

	default:
		break;
}