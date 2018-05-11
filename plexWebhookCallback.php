<?php

function callPlex($endpoint) {
	$error = '';
	$ip = trim(file_get_contents('/home/user/.ip_nas'));
	if (!$ip) {
		error_log('unable to find NAS IP');
		throw new Exception('NAS IP find failure', 1);
	}
	$url = "http://{$ip}:32400{$endpoint}";
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,
		array(
			'Accept: application/json',
			'X-Plex-Token: plex_token'
		)
	);
	$file_contents = curl_exec($ch);

	$curl_error = (curl_errno($ch)==0) ? false : true;
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$data = json_decode($file_contents, true);
	if($curl_error) {
	    $error = 'Curl error: ' . curl_error($ch) . '; Status code: ' . $http_code;
	} elseif($http_code != 200) {
		$error = "Call error to {$endpoint}, http_code {$http_code} " . print_r($data, true);
	}
	if ($error) {
		error_log($error);
		throw new Exception('Call Error', 1);
	}
	curl_close($ch);
	return $data;
}

function callOmbi($endpoint) {
	$url = "https://www.my-server.com/api/v1/{$endpoint}";
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,
		array(
			'Accept: application/json',
			'ApiKey: api_key'
		)
	);
	$file_contents = curl_exec($ch);

	$curl_error = (curl_errno($ch)==0) ? false : true;
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$data = json_decode($file_contents, true);
	if($curl_error) {
	    $error = 'Curl error: ' . curl_error($ch) . '; Status code: ' . $http_code;
	} elseif($http_code != 200) {
		$error = "Call error to {$endpoint}, http_code {$http_code} " . print_r($data, true);
	}
	if ($error) {
		error_log($error);
		throw new Exception('Call Error', 1);
	}
	curl_close($ch);
	return $data;
}

function processArgv() {
	list($script, $server, $action, $media_type, $imdb_id) = explode(',', $argv);
	$log = "[".date("Y-m-d H:i:s")."] - ARGV: ".json_encode($argv)."\n\n";
	file_put_contents('/tmp/plexWebhookCallback.log', $log, FILE_APPEND);
	$endpoint = ($media_type == 'movie') ? 'Request/movie' : 'Request/tv';
	$requests = callOmbi($endpoint);
	foreach ($requests as $request) {
		if ($request['imdbId'] == $imdb_id) {
			$log = "[".date("Y-m-d H:i:s")."] - REQUESTED: ".json_encode($request)."\n\n";
			file_put_contents('/tmp/plexWebhookCallback.log', $log, FILE_APPEND);
		}
	}
}

if (isset($argv)) {
	processArgv()
} elseif (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
    $HTTP_RAW_POST_DATA = file_get_contents("php://input");
    if ($HTTP_RAW_POST_DATA) {
	    $_REQUEST = array_merge($_REQUEST, json_decode($HTTP_RAW_POST_DATA, true));
    }
}

if (!isset($_REQUEST['payload']) || !$_REQUEST['payload']) {
	exit;
}

$payload = json_decode($_REQUEST['payload'], true);
$md = $payload['Metadata'];
$user = $payload['Account']['title'];
$title = $md['title'];
if ($md['type'] == 'movie') {
	$title = "*{$title}* ({$md['year']})";
} elseif ($md['type'] == 'episode') {
	$title = "*{$md['grandparentTitle']} - S{$md['parentIndex']}E{$md['index']}* - {$title}";
} elseif ($md['type'] == 'season') {
	$title = "*{$md['parentTitle']} - {$title}*";
} elseif ($md['type'] == 'show') {
	$title = "*{$title}*";
}
$client = $payload['Player']['title'];
$server = $payload['Server']['title'];
$metakey = $md['key'];
$action = $msg = '';
switch ($payload['event']) {
	case 'media.scrobble':
		$action = "watched";
		break;

	case 'media.play':
		break;
	case 'media.pause':
	case 'media.resume':
	case 'media.stop':
		exit;
		break;
	case 'media.rate':
		$rating = $payload['rating'];
		$action = "rated {$rating}/10";
		if ($user == 'user') {
			$data = callPlex($metakey);
			$filename = $data['MediaContainer']['Metadata'][0]['Media'][0]['Part'][0]['file'];
			$path_info = pathinfo($filename);
			$dirname = $path_info['dirname'];
			$media_dir = basename($dirname);
			$media_location = str_replace($media_dir, '', $dirname);
			$media_location = str_replace('/shares/', '/mount/', $media_location);
			$local_dir = "/mount/media/Videos/Movies/";
			$archive_dir = "/mount/media/Other Videos/Movies/";
			$moved = false;
			if ($rating < 5 && $media_location == $local_dir) {
				$moved = rename($media_location.$media_dir, $archive_dir.$media_dir);
				$actn = "archived";
			}
			if ($rating > 5 && $media_location == $archive_dir) {
				$moved = rename($media_location.$media_dir, $local_dir.$media_dir);
				$actn = "unarchived";
			}
			if ($moved) {
				$action .= " and {$actn}";
				exec("~/postProcessVideos.sh movies &> /dev/null &");
			}
		}
		break;
	default:
		$log = "[".date("Y-m-d H:i:s")."]".json_encode($_REQUEST)."\n\n";
		file_put_contents('/tmp/plexWebhookCallback.log', $log, FILE_APPEND);
		break;
}
if ($action) {
	$msg = "{$user} {$action} {$title} using {$client} on {$server}";
}
exec("~/sendSlackMessage plexpy plex \"{$msg}\"");