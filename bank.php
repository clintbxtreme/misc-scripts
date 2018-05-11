<?php

require_once "~/tools.php";

if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $_REQUEST);
} elseif ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'on') ||
	(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] != "https")) {
	exit("Need to be HTTPS");
}

$slack_url = "https://hooks.slack.com/services/rest-of-url";

$access_token_bofa = "bofa_token";
$item_id_bofa = "bofa_item_id";

$access_token_citi = "citi_token";
$item_id_citi = "citi_item_id";

$defaultParams = array(
	"client_id" => "plaid_client_id",
	"secret" => "plaid_secret",
);
$all_params = array(
	'bofa' => array_merge($defaultParams, array("access_token" => $access_token_bofa,)),
	'citi' => array_merge($defaultParams, array("access_token" => $access_token_citi,))
);

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
    $HTTP_RAW_POST_DATA = file_get_contents("php://input");
    if ($HTTP_RAW_POST_DATA) {
	    $_REQUEST = array_merge($_REQUEST, json_decode($HTTP_RAW_POST_DATA, true));
    }
}

$tools = new Tools();
$tools->log_file = 'bank_php/log';
$tools->logToFile($_REQUEST);

if (isset($_REQUEST['item_id'])) {
	$bad_id = 'bad_id';
	if (in_array($_REQUEST['item_id'], array($item_id_citi, $bad_id))) exit;
	if ($_REQUEST['item_id'] != $item_id_bofa) {
		$msg = "Unknown item_id: " . print_r($_REQUEST, true);
		exec("~/sendSlackMessage error_logs web '{$msg}'");
		exit;
	}
	if ($_REQUEST['webhook_code'] != 'DEFAULT_UPDATE') exit;
	$_REQUEST['q'] = 'available';
}

if (isset($_REQUEST['text'])) {
	if ($_REQUEST['token'] != "po8weurbnqasdfweklrhblasduhf") {
		echo "Invalid Token";
		exit();
	}

	//for FastCGI
	// session_write_close();
	// fastcgi_finish_request();

	// for non-FastCGI
	ignore_user_abort(true);
	set_time_limit(0);
	header('Connection: close');
	header('Content-Length: '.ob_get_length());
	header("Content-Encoding: none");
	flush();

	$_REQUEST['q'] = $_REQUEST['text'];
	$_REQUEST['force'] = true;
	$slackReturn = true;
}

function call($endpoint, $params) {
	$json = json_encode($params);
	$url = "https://development.plaid.com/{$endpoint}";
	$error = false;
	$timeout = 0;
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($json)
		)
	);
	$file_contents = curl_exec($ch);

	$curl_error = (curl_errno($ch)==0) ? false : true;
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$data = json_decode($file_contents, true);
	if($curl_error) {
	    $error = 'Curl error: ' . curl_error($ch) . '; Status code: ' . $http_code;
	} elseif($http_code != 200) {
		$error = "Call error to {$endpoint}, http_code {$http_code}, Params: ".print_r($params, true) . "Result: " . print_r($data, true);
	}
	if ($error) {
		error_log($error);
		throw new Exception('Call Error', 1);
	}
	curl_close($ch);
	return $data;
}

function getBalance($params) {
	$data = call("accounts/balance/get", $params);
	$accounts = array();
	foreach($data['accounts'] as $account) {
		$accounts[$account['mask']] = array(
			"available" => $account['balances']['available'],
			"current" 	=> $account['balances']['current'],
			"name" 		=> $account['name']
		);
	}
	return $accounts;
}

function getTransactions($params) {
	$start_date = date('Y-m-d', strtotime('-16 days'));
	$end_date = date('Y-m-d');
	$options = array(
		"options" => array(
			"count"		=>	500,
		),
		"start_date" => $start_date,
		"end_date" => $end_date,
	);
	$params = array_merge($params, $options);
	$data = call("transactions/get", $params);
	$accounts = $transactions = $balances = array();
	foreach($data['accounts'] as $account) {
		$accounts[$account['account_id']] = $account['mask'];
		$transactions[$account['mask']] = array();
		$bal_type = ($account['subtype'] == 'credit card') ? 'current' : 'available';
		$balances[$account['mask']] = $account['balances'][$bal_type];
	}
	foreach($data['transactions'] as $transaction) {
		$transactions[$accounts[$transaction['account_id']]][] = $transaction;
	}
	return array($transactions, $balances);
}

function post_file_slack($url, $raw) {
	$json = "{\"text\":\"{$raw}\"}";
	if (!$url || !$json) return false;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($json)
		)
	);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

function getLinkHtml($extra_html) {
	$public_key = 'plaid_key';
	return "
	<script src='https://cdn.plaid.com/link/v2/stable/link-initialize.js'></script>
	<script src='//code.jquery.com/jquery-1.11.3.min.js'></script>
	<div></div>
	<script>
	var linkHandler = Plaid.create({
		env: 'development',
		clientName: 'Plaid Link',
		key: '{$public_key}',
		apiVersion: 'v2',
		product: ['transactions'],
		webhook: 'https://my-server.com/bank.php',
		onLoad: function() {
			linkHandler.open();
		},
		onEvent: function(eventName, metadata) {
			console.log(eventName, metadata);
		},
		onExit: function(err, metadata) {
			if (err != null) {
				console.log(err);
				console.log(metadata);
			}
		},
		{$extra_html}
	});
	</script>";
}

function getData($type) {
	$data = file_get_contents("./data/{$type}.data");
	return json_decode($data, true);
}

function saveData($type, $data) {
	if (!$type) return false;
	if (is_array($data)) {
		$data = json_encode($data);
	}
	$filename = "./data/{$type}.data";
	file_put_contents($filename, $data);
}

$style = "";

if (!isset($_REQUEST['q'])) $_REQUEST['q'] = "";
switch ($_REQUEST['q']) {
	case 'update_account':
		if (!isset($all_params[$_REQUEST['account']])) exit("invalid account");
		$params = $all_params[$_REQUEST['account']];
		$response = call('item/public_token/create', $params);
		$html = "
			onSuccess: function(public_token, metadata) {},
			token: '{$response['public_token']}'
		";
		print getLinkHtml($html);
		exit;
		break;
	// case 'new_account':
	// 	$html = "
	// 		onSuccess: function(public_token, metadata) {
	// 			$.post('./', {
	// 				q: 'validate_public_token',
	// 				public_token: public_token,
	// 			});
	// 		}
	// 	";
	// 	print getLinkHtml($html);
	// 	exit;
	// 	break;
	// case 'delete_account':
	// 	if (!isset($all_params[$_REQUEST['account']])) exit("invalid account");
	// 	$params = $all_params[$_REQUEST['account']];
	// 	$data = call("item/delete", $params);
	// 	print_r($data);
	// 	exit;
	// 	break;
	case 'list_account':
		if (!isset($all_params[$_REQUEST['account']])) exit("invalid account");
		$params = $all_params[$_REQUEST['account']];
		$data = call("item/get", $params);
		print_r($data);
		exit;
		break;
	case 'validate_public_token':
		if (!isset($_REQUEST['public_token']) || trim($_REQUEST['public_token']) == '') {
			exit;
		}
		$params = array_merge($defaultParams, array('public_token'=>$_REQUEST['public_token']));
		$response = call('item/public_token/exchange', $params);
		$msg = print_r($response, true);
		exec("~/sendSlackMessage money web '{$msg}'");
		exit;
		break;
	case 'balance':
		$title = "Balance";
		$style = "td, th{padding: 5px;} table{text-align: left;}";
		$rows = "<tr><th>Number</th><th>Available</th><th>Current</th><th>Name</th></tr>";
		$raw = "Number\tAvailable\tCurrent\tName\n";
		foreach ($all_params as $bank => $params) {
			$accounts = getBalance($params);
			foreach($accounts as $number => $account) {
				$rows .= "<tr><td>{$number} - {$bank}</td><td>\${$account['available']}</td><td>\${$account['current']}</td><td>{$account['name']}</td></tr>";
				$raw .= "{$number} - {$bank}\t\${$account['available']}\t\${$account['current']}\t{$account['name']}\n";
			}
		}
		break;
	case 'transactions':
		$title = "Transactions";
		$style = "td,th{padding: 5px; border: thin solid black;} table{border-collapse: collapse;}th b{font-size:x-large;}";
		$raw = $rows = "";
		foreach ($all_params as $bank => $params) {
			list($accounts, $balances) = getTransactions($params);
			$rows .= "<tr><th colspan=3 style='font-size: x-large;'>{$bank}</th></tr>";
			$raw .= "*{$bank}*\n";
			foreach ($accounts as $index => $account) {
				$rows .= "<tr><th colspan=3>{$index}</th></tr>";
				$raw .= "{$index}\n";
				foreach ($account as $t) {
					$dt = date("m/d", strtotime($t['date']));
					$p_h = "";
					if ($t['pending']) $p_h = " style='font-style:italic;color:gray;'";
					$rows .= "<tr{$p_h}><td>{$dt}</td><td>\${$t['amount']}</td>
								<td><div style='overflow: hidden; white-space: nowrap; text-overflow: ellipsis; width: 350px;'>
								<span title='{$t['name']}'>{$t['name']}</span></div></td></tr>";
					$raw .= "{$dt}\t\${$t['amount']}\t{$t['name']}\n";
				}
			}
		}
		break;
	case 'transactions_recent':
		$raw = "";
		$transactions = array();
		foreach ($all_params as $bank => $params) {
			list($accounts, $balances) = getTransactions($params);
			foreach ($accounts as $account_num => $account) {
				foreach ($account as $t) {
					if ($t['date'] < date('Y-m-d', time()-86400)) continue;
					$dt = $dtr = date("m/d", strtotime($t['date']));
					$pending_style = "";
					if ($t['pending']) {
						$dtr = "~{$dtr}~";
					}
					$raw .= "{$dtr}\t{$account_num}\t\${$t['amount']}\t{$t['name']}\n";
				}
			}
		}
		$_REQUEST['response_url'] = $slack_url;
		$slackReturn = true;
		break;
	case 'bills':
		$username = "username";
		$password = "password!";
		if ($_REQUEST['u'] == $username && $_REQUEST['ps'] == $password) {
			$bills = getData('bills');
			if(isset($_REQUEST['action'])) {
				if ($_REQUEST['action'] == "update_bills" && is_array($_REQUEST['bills'])) {
					$bills = $_REQUEST['bills'];
					if (isset($_REQUEST['new_bill']) && $_REQUEST['new_bill'] == 'on') {
						$bills['NEW'] = array('amount' => 0, 'date' => 0);
					}
					foreach ($_REQUEST['names'] as $old_name => $new_name) {
						if ($old_name != $new_name) {
							$bills[$new_name] = $bills[$old_name];
							unset($bills[$old_name]);
						}
					}
					if (isset($_REQUEST['delete']) && is_array($_REQUEST['delete'])) {
						foreach ($_REQUEST['delete'] as $name => $value) {
							if ($value == 'on') {
								unset($bills[$name]);
							}
						}
					}
					uasort($bills, function ($a, $b) {
						return $a['date'] <=> $b['date'];
					});
					saveData('bills', $bills);
				}
			}
			$bills_html = "";
			$raw = "Name\tDate\tAmount\n";
			foreach($bills as $name => $bill) {
				$bills_html .= "<tr>
								<td><input type='text' name='names[{$name}]' value='{$name}'/></td>
								<td><input type='text' name='bills[{$name}][date]' value='{$bill['date']}'/></td>
								<td><input type='text' name='bills[{$name}][amount]' value='{$bill['amount']}'/>
									<input type='checkbox' name='delete[{$name}]' value='on' title='delete'/>
								</td>
								</tr>";
				$raw .= "{$name}\t{$bill['date']}\t{$bill['amount']}\n";
			}
			$bills_html .= "<tr><td><input type='checkbox' name='new_bill' value='on' title='new'/></td></tr>";
			$title = "Bills";
			$style = "input{width: 75px;}";
			$rows =  <<<EOD
				<form method="POST">
					<tr><th>Name</th><th>Date</th><th>Amount</th></tr>
					{$bills_html}
					<tr><td colspan="3"><input type="submit" value="Update"/></td></tr>
					<input type='hidden' name='action' value='update_bills'/>
				</form>
EOD;
		}
		break;

	case 'available':
		$slackReturn = false;
		$d1 = 5;
		$d2 = 20;
		$sav_num = "5911";
		$acct_num = "5748";
		$calc_num = 'calc';

		$c_cards = array(
			'6798' 	=> array('name'=>'Double'),
			'3584' 	=> array('name'=>'Costco'),
			'2864' 	=> array('name'=>'Shop'),
		);

		$bills = getData('bills');
		$accounts = $balances = array();
		foreach ($all_params as $bank => $params) {
			getBalance($params);
			list($account, $balance) = getTransactions($params);
			$accounts += $account;
			$balances += $balance;
		}

		foreach ($c_cards as $num => $data) {
			if (!isset($balances[$num])) continue;
			$curr = $balances[$num];
			foreach ($accounts[$num] as $trans) {
				if ($trans['pending']) {
					$curr += $trans['amount'];
				}
			}
			$c_cards[$num]['curr'] = number_format($curr, 2, ".", "");
		}

		$bal_curr = number_format($balances[$acct_num], 2, ".", "");
		$sav_curr = number_format($balances[$sav_num], 2, ".", "");

		$balances_existing = getData('balances');

		$set1 = $set2 = $non_bills = array();
		$totalSet1 = $totalSet2 = 0;
		foreach ($bills as $name => $bill) {
			if ($bill['date'] == 0) {
				$non_bills[$name] = $bill;
			} elseif ($bill['date'] >= $d1 && $bill['date'] < $d2) {
				$totalSet1 += $bill['amount'];
				$set1[$name] = $bill;
			} else {
				$totalSet2 += $bill['amount'];
				$set2[$name] = $bill;

			}
		}
		$misc_ammount = (($totalSet1 + $totalSet2) / 2) - $totalSet2;
		$set2['Misc']['amount'] = number_format($misc_ammount, 2, ".", "");

		$current_date = intval(date('j'));
		$pay_period_bills = $set2;
		$next_pay_period_bills = $set1;
		$start;
		$end;
		if ($current_date >= $d1 && $current_date < $d2) {
			$pay_period_bills = $set1;
			$next_pay_period_bills = $set2;
			$start = mktime(0, 0, 0, date("m"), $d1, date("Y"));
			$end = mktime(23, 59, 59, date("m"), $d2 - 1, date("Y"));
		} else if ($current_date < $d1) {
			$start = mktime(0, 0, 0, date("m")-1, $d2, date("Y"));
			$end = mktime(23, 59, 59, date("m"), $d1 - 1, date("Y"));
		} else if ($current_date >= $d2) {
			$start = mktime(0, 0, 0, date("m"), $d2, date("Y"));
			$end = mktime(23, 59, 59, date("m") + 1, $d1 - 1, date("Y"));
		}
		$pay_period_bills = array_merge($pay_period_bills, $non_bills);

		$dues = 0;
		$bills_left = "";
		$bills_left_html = "";
		$addNext = false;
		$nextStart = date('N', $end + 86400);
		foreach ($accounts[$acct_num] as $transaction) {
			$trans_dt = strtotime($transaction['date']);
			if ($trans_dt < $start) continue;
			if (strpos($transaction['name'], "Paycheck")!==FALSE && $trans_dt >= ($start + (86400*5)) && $trans_dt <= $end) {
				$addNext = true;
			}
			if (strpos($transaction['name'], 'AUTOPAY')!==FALSE) {
				$trans_amount = $transaction['amount'];
				foreach ($c_cards as $num => $data) {
					if (!isset($pay_period_bills[$data['name']]['amount'])) continue;
					if ($pay_period_bills[$data['name']]['amount'] == $trans_amount) {
						unset($pay_period_bills[$data['name']]);
						continue;
					}
					if (!isset($accounts[$num])) continue;
					foreach ($accounts[$num] as $trans) {
						if (strpos($trans['name'], "AUTO-PMT")) {
							if (abs($trans['amount']) == abs($trans_amount)) {
								unset($pay_period_bills[$data['name']]);
							}
						}
					}
				}
			}
			foreach ($pay_period_bills as $name => $info) {
				if (strpos($transaction['name'], $name)!==FALSE) {
					unset($pay_period_bills[$name]);
				}
			}
		}

		$new_trans = '';
		$calc_update = date('Ymd', strtotime($balances_existing[$calc_num]['dt']));
		foreach ($accounts as $n => $account) {
			foreach ($account as $tr) {
				$transaction_date = date('Ymd', strtotime($tr['date']));
				if ($transaction_date >= $calc_update) {
					$amt = str_pad(number_format($tr['amount'], 2, ".", ""), 10);
					$new_trans .= ">{$n}\t|\t{$tr['date']}\t|\t\${$amt}\t|\t{$tr['name']}\n";
				}
			}
		}
		if ($addNext) {
			unset($pay_period_bills['Misc']);
			$pay_period_bills = array_merge($pay_period_bills, $next_pay_period_bills);
		}
		foreach ($pay_period_bills as $name => $bill) {
			$dues += $bill['amount'];
			$bills_left .= "\n>{$bill['date']}\t | \t\${$bill['amount']}\t | \t{$name}";
			$bills_left_html .= "<tr><td>{$bill['date']}</td><td>\${$bill['amount']}</td><td>{$name}</td></tr>";
		}

		$bills_left .= "\n>--------------------------------------------";
		$bills_left .= "\n>=\t | \t\${$dues}";
		$balance_after_bills = number_format($bal_curr - $dues, 2, ".", "");
		$cc_info = '';
		foreach ($c_cards as $num => $data) {
			if (!isset($data['curr'])) continue;
			$adj = str_pad($data['curr'], 10);
			$bal = str_pad($balances[$num], 10);
			$cc_info .= "*{$data['name']}:*\n>Adjusted: \${$adj}\t | \tBalance: \${$bal}\n";
		}
		$ch_adj = str_pad($balance_after_bills, 10);
		$ch_bal = str_pad($bal_curr, 10);
		$ch_info = "*Checking:*\n>Adjusted: \${$ch_adj}\t | \tBalance: \${$ch_bal}";
		$raw = "{$ch_info}\n{$cc_info}*Bills:*{$bills_left}\n*New Trans:*\n{$new_trans}";
		$title="Available";
		$style = "table{text-align: center;}";
		// can add credit card and citi card info if needed
		$rows = <<<EOD
			<tr><td></td><td>\${$bal_curr}</td><td>Balance</td></tr>
			<tr><td>-</td><td>\${$dues}</td><td>Bills</td></tr>
			<tr><td>=</td><td>\${$balance_after_bills}</td><td>Adjusted</td></tr>
			<tr><td colspan='3'>--------------------------------------------------------------------</td></tr>
			<tr><th>Date</th><th>Amount</th><th>Name</th></tr>
			{$bills_left_html}
EOD;
		$calc_exist = number_format($balances_existing[$calc_num]['bal'], 2, ".", "");

		if ($calc_exist == $balance_after_bills && !isset($_REQUEST['force'])) exit();

		post_file_slack($slack_url, $raw);
		$updated_dt = date("m/d/Y H:i:s");
		$balances_existing[$acct_num] = array('bal' => $bal_curr, 'dt' => $updated_dt);
		$balances_existing[$sav_num] = array('bal' => $sav_curr, 'dt' => $updated_dt);
		$balances_existing[$calc_num] = array('bal' => $balance_after_bills, 'dt' => $updated_dt);
		foreach ($c_cards as $num => $data) {
			if (!isset($data['curr'])) continue;
			$balances_existing[$num] = array('bal' => $data['curr'], 'dt' => $updated_dt);
		}
		saveData('balances', $balances_existing);
		break;
	default:
		http_response_code(404);
		print <<<EOD
			<html><head>
			<title>404 Not Found</title>
			<style type="text/css"></style></head><body>
			<h1>Not Found</h1>
			<p>The requested URL was not found on this server.</p>
			</body></html>
EOD;
		die();
		break;
}
if (isset($_REQUEST['response_url']) && $slackReturn) {
	post_file_slack($_REQUEST['response_url'], $raw);
} elseif (isset($argv)) {
	print($raw);
} else {
	print <<<EOD
		<!doctype html><html><head><title>{$title}</title><style>{$style}</style>
		<meta name="viewport" content="initial-scale=1, maximum-scale=1"></head>
		<body><table>{$rows}</table></body></html>
EOD;
}

?>