<?php

$url = $_REQUEST['url'] ? $_REQUEST['url'] : null;
if (!$url) exit;

$ch = curl_init('https://www.googleapis.com/urlshortener/v1/url?key=api-key');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('longUrl' => $url)));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER,
	array('Content-Type: application/json')
);
$result_json = curl_exec($ch);
curl_close($ch);
$result = json_decode($result_json, true);
if (isset($result['error'])) {
	error_log(json_encode($result['error']));
} else {
	$short_url = str_replace('https://', '', $result['id']);
	if (php_sapi_name() === 'cli') {
		return $short_url;
	} else {
		print $short_url;
	}
}