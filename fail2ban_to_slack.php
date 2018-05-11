<?php

$status = $argv[1];
$ip = $argv[2];
$time = date("n/j/y g:i:s", $argv[3]);
$name = $argv[4];

$message = "Fail2Ban {$status} {$ip} on {$time} using {$name} config";
if ($status == "blocked") {
	$failures = $argv[5];
	$logs = $argv[6];
	$logs = explode("\n", $logs);
	$unique_logs = array();
	foreach ($logs as $log) {
		switch ($name) {
			case 'sshd':
			case 'sshd-ddos':
				list($dt, $lg) = explode(": ", $log);
				break;

			case 'apache-badbots':
				list($dt, $lg) = explode("] ", $log);
				break;

			default:
				$lg = $log;
				$dt = "not set yet";
				break;
		}
		$unique_logs[$lg][] = $dt;
	}
	foreach ($unique_logs as $kind => $dates) {
		$times = "";
		if (count($dates) > 1) $times = " (". count($dates)." times)";
		$unique_logs_strings[] = $kind.$times;
	}
	$logs_clean = implode("\n", $unique_logs_strings);
	$message .= " after {$failures} failures \n>>>{$logs_clean}";
}
exec("~/sendSlackMessage fail2ban server '{$message}'");
