<?php

if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $_REQUEST);
}
$json_data = '';
if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true, 512, JSON_BIGINT_AS_STRING);

	$log = "[".date("Y-m-d H:i:s")."]\n".$json_data."\n\n";
	file_put_contents('/var/log/custom/shopStevie.log', $log, FILE_APPEND);
}

define('OVERRIDE', isset($_REQUEST['ovrd']));

if ($json_data) {
	ignore_user_abort(true);
	set_time_limit(0);
	header('Connection: close');
	header('Content-Length: '.ob_get_length());
	header("Content-Encoding: none");
	flush();
}

function callShippoApi($endpoint='', $json=null)
{
	$shippo_api_test = "shippo_test_rest-of-token";
	$shippo_api_live = "shippo_live_rest-of-token";
	$token = $shippo_api_live;
	if (TESTING) {
		$token = $shippo_api_test;
	}

	if (is_array($json)) {
		$json = json_encode($json);
	}
	$url = "https://api.goshippo.com/{$endpoint}";
	$error = false;
	$timeout = 0;
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$header = array("Authorization: ShippoToken {$token}");
	if ($json) {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		$header[] = "Content-Type: application/json";
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	$file_contents = curl_exec($ch);

	$curl_error = (curl_errno($ch)==0) ? false : true;
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$data = json_decode($file_contents, true);
	if($curl_error) {
	    $error = 'Curl error: ' . curl_error($ch) . '; Status code: ' . $http_code;
	} elseif(isset($data['status']) && $data['status'] != 'SUCCESS') {
		$error = "Call error to {$endpoint}, http_code {$http_code}: {$file_contents}";
	} elseif (!in_array($http_code, array(200, 201, 204))) {
		$error = "Call error to {$endpoint}, http_code {$http_code}: {$file_contents}";
	}
	if ($error) {
		error_log($error);
		throw new Exception('Call Error', 1);
	}
	curl_close($ch);
	return $data;
}

function createLabel($data) {
	$order_url = "#{$data['order_number']}";
	if (isset($data['id']) && $data['id']) {
		$order_url = "<https://my-server.shopify.com/admin/orders/{$data['id']}|#{$data['order_number']}>";
	}
	if (isset($data['source_name']) && $data['source_name'] == 'pos') {
		logToSlack("POS order: {$order_url}", true);
	}
	$shippoOrder = null;
	$retried = 0;
	$page = '';
	do {
		$orders = callShippoApi("orders{$page}");
		foreach ($orders['results'] as $order) {
			$o_num = str_replace("#", '', $order['order_number']);
			if ($data['order_number'] == $o_num) {
				$shippoOrder = $order;
				break 2;
			}
		}
		if ($orders['next']) {
			list($junk, $page) = explode("orders", $orders['next']);
		} else {
			if (!$retried) {
				callShippoApi('orders/sync');
				$page = '';
			}
			$retried ++;
		}
	} while ($orders['next'] || $retried <= 1);

	if (!$shippoOrder) {
		logToSlack("No Shippo order matches Shopify order {$order_url}", true);
	}
	$shipping_charged = 0;
	if ($shippoOrder['shipping_cost']) {
		$shipping_charged = number_format($shippoOrder['shipping_cost'], 2);
	}
	if (isset($data['discount_codes'])) {
		foreach ($data['discount_codes'] as $discount_code) {
			if ($discount_code['type'] == 'shipping') {
				$shipping_charged -= number_format($discount_code['amount'], 2);
			}
		}
	}
	$insurance = intval($shippoOrder['total_price']) + 2;
	if (!$shipping_charged || $shipping_charged <= 0) {
		logToSlack("no shipping for order {$order_url}", !OVERRIDE);
		$insurance += 10;
	}
	foreach ($shippoOrder['transactions'] as $trans) {
		if ($trans['object_status'] == "SUCCESS") {
			logToSlack("Label already created for {$order_url}", !OVERRIDE);
		}
	}
	$total_items = 0;
	$item_msg = '';
	$items = ($shippoOrder['items']) ? $shippoOrder['items'] : $shippoOrder['line_items'];
	foreach ($items as $item) {
		$total_items += $item['quantity'];
		$item_msg .= ">{$item['title']} [{$item['sku']}] ({$item['quantity']}) \n";
	}
	if (!$weight = $shippoOrder['weight']) {
		logToSlack("no weight for {$order_url}", !OVERRIDE);
	}

	if ($total_items > 3 || $weight > 1.5) {
		logToSlack("too many items or too heavy - {$order_url}", !OVERRIDE);
	}
	if (!$to_address = $shippoOrder['to_address']) {
		logToSlack("no to_address for {$order_url}", true);
	}
	$to_keep = array('name','company','street_no','street1','street2','street3','city','state','zip','country','phone','email');
	$address_to = array_intersect_key($to_address, array_flip($to_keep));
	$ship_data = array(
        "address_from" => array(
            "name" => "Shopify",
            "street1" => "1234 Example ST",
            "city" => "Mesa",
            "state" => "AZ",
            "zip" => "85208",
            "country" => "US",
            "phone" => "4801234567",
            "email" => "Shopify@gmail.com"
        ),
		"address_to" => $address_to,
		"async" => false,
		"parcels" => array(
			array(
				"length" => "10",
				"width" => "13",
				"height" => "1",
				"distance_unit" => "in",
				"weight" => $weight,
				"mass_unit" => "lb",
			)
		),
	    "extra" => array(
			"insurance" => array(
				"amount" => $insurance,
				"currency" => "USD"
			),
		),
	);
	$shipment = callShippoApi('shipments', $ship_data);

	$rate_id = '';
	foreach($shipment['rates'] as $rate) {
		if ($rate['servicelevel']['name'] == $shippoOrder['shipping_method']) {
			$rate_id = $rate['object_id'];
		}
		if (in_array("CHEAPEST", $rate['attributes'])) {
			$cheapest_rate_id = $rate['object_id'];
		}
		$rates_by_id[$rate['object_id']] = $rate;
	}
	$rate_id = ($rate_id) ? $rate_id : $cheapest_rate_id;

	$shipping_paid = $rates_by_id[$rate_id]['amount'];
	if ($shipping_paid > $shipping_charged) {
		logToSlack("Didn't pay enough for shipping in order {$order_url} (\${$shipping_charged} paid, \${$shipping_paid} required)", !OVERRIDE);
	}

	if (!$rate_id) {
		logToSlack("No Rate ID for order {$order_url}", true);
	}
	$trans_data = array(
  			"rate" => $rate_id,
  			"label_file_type" => "PDF_4x6",
  			"async" => false,
	);
	$status = "Test";
	if (!TESTING) {
		$trans_data['order'] = $shippoOrder['object_id'];
		$transaction = callShippoApi('transactions', $trans_data);
		$status = "Created";
	}

	$msg = "*Label {$status}:* \n";
	$msg .= "{$to_address['name']} \n";
	$msg .= "{$to_address['email']}  {$to_address['phone']} \n";
	$msg .= "Order: {$order_url} \n";
	$msg .= "Shipping: \${$shipping_charged} charged, \${$shipping_paid} paid \n";
	$msg .= "Ordered: \n";
	$msg .= "{$item_msg} \n";
	logToSlack($msg);
}

function printLabel($url) {
	$pdf = file_get_contents($url);
	$filename = "/tmp/".microtime(true).".pdf";
	file_put_contents($filename, $pdf);
	exec("lp {$filename}", $result, $failed);
	if (!$failed) {
		unlink($filename);
		return true;
	}
	return false;
}

function logToSlack($message, $error=false)
{
    $url = 'https://hooks.slack.com/services/rest-of-url';
    $testing = (TESTING) ? '*_IN TEST_*' : '';
	$color = ($error) ? 'danger' : 'good';
    $fields = json_encode(array(
    	'attachments'=>array(
    		array(
    			"pretext" => $testing,
    			"text" => $message,
    			"color" => $color,
    			"mrkdwn_in" => array("text", "pretext")
			)
		)
	));
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $result = curl_exec($ch);
    curl_close($ch);
    if ($error) {
    	exit;
    }
}

function verify_shopify_webhook($data, $hmac_header)
{
	$shopify_app_secret = 'app-secret';
	$calculated_hmac = base64_encode(hash_hmac('sha256', $data, $shopify_app_secret, true));
	return ($hmac_header == $calculated_hmac);
}

if (isset($_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'])) {
	$verified = verify_shopify_webhook($json_data, $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256']);
	if (!$verified) {
		logToSlack('Shopify Not Verified', true);
	}

	define('TESTING', isset($data['test']) && $data['test']);

	if (TESTING) {
		$data['order_number'] = 1040;
		$data['financial_status'] = "paid";
	}

	if ($_SERVER['HTTP_X_SHOPIFY_TOPIC'] == 'orders/paid' && $data['financial_status'] == 'paid') {
		createLabel($data);
	}

} elseif (isset($data['label_url']) && $data['label_url']) {
	$status = "Didn't Print";
	define('TESTING', isset($data['test']) && $data['test']);
	if (!TESTING && printLabel($data['label_url'])) {
		$status = "Printed";
	}
	logToSlack("Label {$status} for {$data['metadata']}");

} elseif (isset($_REQUEST['create_shippo_label'])) {
	if (!isset($_REQUEST['order_number'])) exit;
	define('TESTING', isset($_REQUEST['testing']));

	createLabel($_REQUEST);
}