<?php
$token = 'slack-token';
$channels_not_to_purge = array(
	'general',
);
$total_messages_deleted = $total_messages_left = 0;
$users = array();
$d = postToSlack("users.list", array('token' => $token));
foreach ($d['members'] as $user) {
	$users[$user['id']] = $user;
}
$channel_params = [
	'token' => $token,
	'types'=>'public_channel,private_channel,mpim,im'
];
$channels = postToSlack("conversations.list", $channel_params);
foreach ($channels['channels'] as $channel) {
	$messages_deleted = 0;
	$number_of_messages = 100;
	$channel_id = $channel['id'];
	$channel_name = isset($channel['name']) ? $channel['name'] : $users[$channel['user']]['name'];
	$info = postToSlack('conversations.info', ['token' => $token, 'channel' => $channel_id]);
	$last_read = time();
	if ($info['channel']['is_im'] || !$info['channel']['is_archived']){
		$last_read = $info['channel']['last_read'];
	}
	$params = [
		'token' => $token,
		'channel' => $channel_id,
		'count' => 1000,
	];
	$messages_left = 0;
	do {
		$data_history = postToSlack("conversations.history", $params);
		$messages_left += count($data_history['messages']);
		$params['latest'] = end($data_history['messages'])['ts'];
		if (in_array($channel_name, $channels_not_to_purge)) continue;
		foreach($data_history['messages'] as $message) {
			$ts = $message['ts'];
			if ($ts > strtotime("14 days ago") || $ts >= $last_read) continue;
			$deleteParams = array(
				'token' => $token,
				'ts' => $message['ts'],
				'channel' => $channel_id,
			);
			$response = postToSlack('chat.delete', $deleteParams);
			$response = ['ok'=> true];
			sleep(1);
			if ($response['ok']) {
				$retry = 0;
				$total_messages_deleted ++;
				$messages_deleted ++;
				$messages_left --;
			} else {
				error_log(date('m/d/y H:i:s')." - can't delete {$message['ts']} on {$channel_name}. e: {$response['message']}\n");
				if ($response['code'] == 429 && $retry < 3) {
					$retry ++;
					sleep(60);
				} else {
					exit;
				}
			}
		}
	} while ($data_history['has_more']);

	$delete_text = '';
	if ($messages_deleted) {
		$delete_text = "{$messages_deleted} messages deleted, ";
	}
	if ($messages_left) {
		echo "{$channel_name}: {$delete_text}{$messages_left} messages left\n";
		$total_messages_left += $messages_left;
	}
}
echo date('m/d/y H:i:s')." - {$total_messages_deleted} total messages deleted, {$total_messages_left} total messages left\n";
echo "======================================================================\n";

if ($total_messages_left >= 7000) {
	error_log("{$total_messages_left} total Slack messages");
}

function postToSlack($endpoint, $fields) {
	$header = array();
	$error = false;
	$url = "https://slack.com/api/".$endpoint;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($handle, $data) use (&$header) {
		if (trim($data)) {
			$parts = explode(":", $data, 2);
			if (count($parts)==2) {
				$header[$parts[0]] = trim($parts[1]);
			} else {
				$header[] = trim($data);
			}
		}
		return strlen($data);
	});
	$result_json = curl_exec($ch);
	$curl_error = (curl_errno($ch)==0) ? false : true;
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$data = json_decode($result_json, true);
	if($curl_error) {
		$error = 'Curl err: ' . curl_error($ch) . '; http code: ' . $http_code;
	} elseif($http_code != 200) {
		if($http_code == 429) {
			$error = "Hit rate limit. Retry after: " . $header['Retry-After'];
		} else {
			$error = "Call err to {$endpoint}, code {$http_code} " . print_r($data, true);
		}
	}
	curl_close($ch);
	if ($error) {
		$data = array(
			'ok' => false,
			'message' => $error,
			'code' => $http_code,
		);
	}
	return $data;
}