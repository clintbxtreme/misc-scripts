<?php

function find($regex, $page) {
	$regex = "/{$regex}/";
	preg_match($regex, $page, $match);
	$count = count($match);
	if ($count == 1) {
		return $match[0];
	} elseif ($count == 2) {
		return $match[1];
	} elseif ($count > 2) {
		array_shift($match);
		return $match;
	} else {
		return false;
	}
}

function getEvents() {
	$data = file_get_contents("~/data/events");
	return json_decode($data, true);
}

function saveEvents($data) {
	if (is_array($data)) {
		$data = json_encode($data);
	}
	$filename = "~/data/events";
	file_put_contents($filename, $data);
}

if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $_REQUEST);
}

$raw = $error = "";
$day_ts = time();
$day_ts_prev = $day_ts + 1;
$latest_ts = strtotime("+ 6 months");
$count = 0;
$savedEvents = array();
$existingEvents = getEvents();
$script = 'shorten.php';
$shorten_file_1 = dirname($_SERVER['PHP_SELF']).'/'.$script;
$shorten_file_2 = dirname($_SERVER['HOME'].'/'.$_SERVER['PHP_SELF']).'/'.$script;
if (file_exists($shorten_file_1)) {
	$shorten_script = $shorten_file_1;
} elseif(file_exists($shorten_file_2)){
	$shorten_script = $shorten_file_2;
} else {
	echo "shorten filename not found\n";
	exit;
}
while($day_ts < $latest_ts) {
	$count ++;
	if ($day_ts == $day_ts_prev) {
		$day_ts += 86400; // 1 day
		echo "Manually skipping to next day\n";
	}
	$day_ts_prev = $day_ts;
	$date = date("Y-m-d", $day_ts);
	$url = "http://event-url.com";
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$page = curl_exec($ch);
	curl_close($ch);
	$events = preg_split('/<!-- Event  -->/', $page);
	array_shift($events);
	echo "check: {$count}, events: ".count($events).", date: {$date}\n";
	if (!count($events)) break;
	foreach ($events as $event) {
		$title = html_entity_decode(find('Event Title.*?title=\\\"(.*?)\\\"', $event));
		$time = strip_tags(find('<span.*?start.*?event.*?span>', $event));
		list($day, $timeframe) = preg_split('/@/', $time);
		$day_ts = strtotime($day);
		$day = date("D n/j", $day_ts);
		$date = "{$day} {$timeframe}";
		$value = $title.' - '.$date;
		$exists = in_array($value, $existingEvents);
		if (!$exists || isset($_REQUEST['alert_all'])) {
			$venue = strip_tags(find('ue-d.*?\>(.*?)\<sp', $event));
			$venue = trim(str_replace(array('\n','\t',','), '', $venue));
			$url = stripslashes(find('url.*?href=\\\"(.*?)\\\"', $event));
			$_REQUEST['url'] = $url;
			$shorturl = include $shorten_script;
			if (!$shorturl) {
				$error .= "*{$title}*  _{$date} @ {$venue}_ {$url}\n";
				continue;
			}
			echo "{$title} - {$date} alerting\n";
			$raw .= "*{$title}*  _{$date} @ {$venue}_ {$shorturl}\n";
		}
		$savedEvents[] = $value;
	}
}
saveEvents($savedEvents);
if (strlen($error)) {
	exec("~/sendSlackMessage error_logs web 'Short URL Error: \n>>>{$error}'");
}
if (strlen($raw)) {
	exec("~/sendSlackMessage work-events web '{$raw}'");
}
