<?php

if ($_REQUEST['scrt'] != "qwerjbvkladsblqkwjebt") exit();

function logToFile($info) {
	if (is_array($info)) {
		$info = json_encode($info);
	}
	$log = "[".date("Y-m-d H:i:s")."] {$info}\n\n";
	file_put_contents('/var/log/custom/code/movieSearch-'.date('Y-m-d').'.log', $log, FILE_APPEND);
}

function call_tmdb($url) {
	$ch = curl_init();
	$timeout = 0;
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$file_contents = curl_exec($ch);

	$curl_error = (curl_errno($ch)==0) ? false : true;
	$curl_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	$result = json_decode($file_contents, true);
	if (isset($result['errors'])) {
		logToFile("TMDB error: url - {$url}, error(s) - ". implode(', ', $result['errors']));
		$result = [];
	}
	return $result;
}

function post_file($url, $fields) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

function getApiKey() {
	return "api-key";
}

function search ($title) {
	$query = urlencode($title);
	$api_key = getApiKey();
	$url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&query={$query}";
	return call_tmdb($url);
}

function getDetails ($id) {
	$api_key = getApiKey();
	$url = "https://api.themoviedb.org/3/movie/{$id}?api_key={$api_key}&append_to_response=releases";
	return call_tmdb($url);
}

function matchResults ($title, $year, $results) {
	if ($results['total_results'] == 1) {
		return $results['results'][0];
	}
	$possible_results = [];
	foreach ($results['results'] as $result) {
		if ($title == cleanTitle($result['title'])) {
			$possible_results[] = $result;
			if ($year && $year != cleanYear($result['release_date'])) {
				continue;
			}
			return $result;
		}
	}
	if (count($possible_results) == 1) {
		return $possible_results[0];
	} elseif (count($possible_results) > 1) {
		logToFile("too many possible results for {$title}");
	}
}

function cleanTitle ($title_raw) {
	$title = preg_replace('/[^\w\s]|\d{4}/', '', $title_raw);
	$title = strtolower(trim($title));
	if (!$title) {
		logToFile("no title found from {$title_raw}");
	}
	return $title;
}

function cleanYear ($title) {
	$year = preg_replace('/\D|\b\d{1,3}\b/', '', $title);
	$year = intval($year);
	if (strlen($year) != 4) {
		logToFile("bad year of '{$year}' found from {$title}");
	}
	return $year;
}

function getMpa ($details) {
	if (is_array($details['releases']['countries'])) {
		foreach ($details['releases']['countries'] as $release) {
			if ($release['iso_3166_1'] == 'US' && $release['certification']) {
				return $release['certification'];
			}
		}
	}
	logToFile("no mpa found. details:");
	logToFile($details);
	return "N/A";
}

function getGenres ($details) {
	if (!$details['genres'] || !count($details['genres'])) {
		logToFile("no genre found. details:");
		logToFile($details);
		return "No Genre";
	}
	$genres = array();
	foreach ($details['genres'] as $genre) {
		$genres[] = $genre['name'];
	}
	return implode(", ", $genres);
}

function getPayloadUrl ($source) {
	switch ($source) {
		case 'misc':
			$payload_url = "https://hooks.slack.com/services/rest-of-url";
			break;

		case 'upcoming':
		default:
			$payload_url = "https://hooks.slack.com/services/rest-of-url";
			break;
	}
	return $payload_url;
}

logToFile($_REQUEST);

$result = [];
$title_search = preg_replace('/\[.*/', '', $_REQUEST['title']);
$title = cleanTitle($title_search);
if ($title) {
	$year = cleanYear($title_search);
	if ($year < 2000) exit;
	$results = search($title);
	if ($results) {
		$result = matchResults($title, $year, $results);
	}
}

if (!$result) {
	$content = "No TMDB result for {$_REQUEST['title']}";
	$title_link = $_REQUEST['url'];
} else {
	$details = getDetails($result['id']);
	$mpa = getMpa($details);
	$genres = getGenres($details);
	$content = "{$mpa}\n{$genres}\n{$details['release_date']}\n{$details['overview']}";
	$title_link = "http://www.imdb.com/title/{$details['imdb_id']}/";

}
$payload = array(
	"attachments" => array(
		array(
			"title" => $_REQUEST['title'],
			"title_link" => $title_link,
			"thumb_url" => $_REQUEST['image'],
			"text" => $content,
			"mrkdwn_in" => array("text"),
			"fallback" => $_REQUEST['title'],
		)
	)
);
$payload_json = json_encode($payload);

$url = getPayloadUrl($_REQUEST['src']);
post_file($url, array("payload" => $payload_json));
?>
