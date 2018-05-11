<?php

function getConnections() {
	$filename = "~/data/connections_car";
	$last_seen = date("m/d/Y H:i:s");
	$ip = $_SERVER['REMOTE_ADDR'];
	$ua = urlencode($_SERVER['HTTP_USER_AGENT']);
	$current_id = md5($ip.$ua);

	$data = json_decode(file_get_contents($filename), true);
	foreach ($data as $id => $d) {
		if ((time() - strtotime($d['last_seen'])) > 5*60) {
			unset($data[$id]);
		}
	}
	if (isset($data[$current_id])) {
		$data[$current_id]['last_seen'] = $last_seen;
	} else {
		$ua_api_url = "https://useragentapi.com/api/v3/json/7e4a411e/".$ua;
		$user_agent_json = file_get_contents($ua_api_url);
		$user_agent = json_decode($user_agent_json, true);
		if (strtolower($user_agent['data']['platform_name']) == 'googlebot') {
		    header("HTTP/1.0 404 Not Found");
		    exit;
		}
		$message = "connection to car page from {$ip}: \n".print_r($user_agent['data'], true);
		exec("~/sendSlackMessage.sh misc web '{$message}'");
		$data[$current_id] = [
			'last_seen' => $last_seen,
			'ip' => $ip,
			'ua' => $ua,
			'uaj' => $user_agent_json,
		];
	}
	file_put_contents($filename, json_encode($data));
	return count($data);
}

$connections = getConnections();

if (isset($_REQUEST['keepAlive'])) {
	print $connections;
	exit;
}
?>

<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/underscore.js/1.8.3/underscore-min.js"></script>
    <script>
    	keepAlive = function() {
			$.post( window.location, {"keepAlive":1}, function( data ) {
				$('#connections').html(data);
			});
    	}
    	setInterval(keepAlive, 10000);
    </script>
</head>
<body>
	<span style='position:absolute;top:5px;right:5px;z-index:9999;color:#F44E52' id='connections'><?= $connections ?></span>
	<iframe style='width:100%; height:100%; position:absolute; border:none; top:0; left:0;' src="https://portal.gpsinsight.com/d/publicmap.php?key=key_here"></iframe>
</body>
</html>