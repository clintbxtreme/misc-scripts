<?php

if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $_REQUEST);
} elseif($_SERVER['SERVER_PORT'] != '443') {
	header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	exit();
}
require_once("nest-api/nest.class.php");
define('USERNAME', 'username');
define('PASSWORD', 'password');
$nest = new Nest();
$did = "";
$tmp_dir = "~/.nest_tmp";
$status = $_REQUEST['status'];
$person = $_REQUEST['person'];
if (!isset($person) || !isset($status)) exit();
exec("~/sendSlackMessage test web '{$person} {$status} home at {$_REQUEST['at']}'");
if ($status == 'entered') {
	$home_already = (count(glob("{$tmp_dir}/*")) > 0);
	if (!$home_already) {
		$nest->setAway(false);
		$did = "off";
	}
	touch("{$tmp_dir}/{$person}");
} elseif ($status == 'exited') {
	$delte_file = "{$tmp_dir}/{$person}";
	if (file_exists($delte_file)) unlink($delte_file);
}
if (count(glob("{$tmp_dir}/*")) === 0 ) {
	$nest->setAway(true);
	$did = "on";
}
if ($did) {
	$msg = "turned away mode {$did}";
	exec("~/sendSlackMessage general web '{$msg}'");
}