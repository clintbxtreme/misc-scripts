#!/usr/bin/php
<?php
function getPrice($type, $page) {
	$regex = "/{$type}.*?(\d.\d\d)/s";
	preg_match($regex, $page, $match);
	if (!isset($match[1])) return false;
	return $match[1];
}

function query($query) {
	$db = new mysqli("localhost", "username", "password", "db")
			or die("Unable to connect to database");
	$result = $db->query($query);
	if ($result instanceof mysqli_result) {
		$results_array = array();
		while ($row = $result->fetch_assoc()) {
			$results_array[] = $row;
		}
		$result->close();
		$result = $results_array;
	}
	$db->close();
	return $result;
}


$url = "http://www.gasbuddy.com/Station/124875";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$page = curl_exec($ch);
curl_close($ch);
$prices = $existing_prices = $counts = array();
$raw = "";
$prices['Regular'] = getPrice("Regular", $page);
$prices['Premium'] = getPrice("Premium", $page);
$prices['Diesel'] = getPrice("Diesel", $page);
$data = query("SELECT * FROM prices");
foreach ($data as $d) {
	$existing_prices[$d['type']] = $d;
}
foreach($prices as $type => $price) {
	if ($price === false) continue;
	if (!isset($existing_prices[$type])) {
		$counts[] = query("INSERT INTO prices (type, price) VALUES ('{$type}', {$price})");
	} elseif ($existing_prices[$type]['price'] != $price) {
		$counts[] = query("UPDATE prices SET price={$price} WHERE type='{$type}'");
	}
	$raw .= "{$type}\t\${$price}\n";
}
if (empty($counts)) exit();
exec("~/sendSlackMessage.sh fuel web '{$raw}'");
print_r($raw);
?>