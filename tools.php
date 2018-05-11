<?php

class Tools
{
	public $log_file = '';
	public $slack_url = "https://slack.com/api/chat.postMessage";

	public function logToFile($info, $exit=false) {
		if (is_array($info)) {
			$info = json_encode($info);
		}
		if ($this->log_file) {
			$log = "[".date("Y-m-d H:i:s")."] {$info}\n\n";
			file_put_contents('/var/log/custom/' . $this->log_file, $log, FILE_APPEND);
		} else {
			error_log("no log_file set in {$_SERVER['PHP_SELF']}");
		}
		if ($exit) exit;
	}

	public function sendText($number, $text) {
		$this->logToFile("sending '{$text}' to '{$number}'");
		$this->sendToIfttt('send_sms', ['value1' => $number, 'value2' => $text]);
	}

	private function sendToIfttt($trigger, $values) {
		$url = "https://maker.ifttt.com/trigger/{$trigger}/with/key/api-key";
		$result = $this->postToUrl($url, $values);
		if (strpos($result, 'Congratulations') === false) {
			$this->logToFile($result);
		}
	}

	public function postToSlack($fields) {
		if (is_array($fields)) {
			$fields['token'] = 'slack-token';
			$msg = $fields['text'];
		} else {
			$msg = $fields;
			$fields = json_encode(["text" => $fields]);
		}

		$this->logToFile("sending '{$msg}' to slack");
		$result_json = $this->postToUrl($this->slack_url, $fields);
		$result = json_decode($result_json, true);
		if (!$result['ok']) {
			$this->logToFile($result_json);
		}
	}

	private function postToUrl($url, $fields) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	private function callOpenGarage($endpoint, $params = []) {
		$open_garage_ip = $this->getOpenGarageIp();
		$params_json = json_encode($params);
		$params['dkey'] = 'garagekey';
		$url_params = http_build_query($params);
		$url = "http://{$open_garage_ip}/{$endpoint}?{$url_params}";
		$error = false;
		$timeout = 0;
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$file_contents = curl_exec($ch);
		$data = json_decode($file_contents, true);

		$curl_error_num = (curl_errno($ch)==0) ? false : true;
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($curl_error_num) {
			$curl_error = curl_error($ch);
			$error = "Curl error: {$curl_error}; Status code: {$http_code}";
		} elseif($http_code != 200 || (isset($data['result']) && $data['result'] != 1)) {
			$error = "Call error to {$endpoint}, http_code {$http_code}, Params: {$params_json} Result: {$file_contents}";
		}
		curl_close($ch);
		if ($error) {
			error_log($error);
			throw new Exception('Call Error', 1);
		}
		$msg = "{$endpoint} - params: {$params_json}, result: {$file_contents}";
		$this->logToFile($msg);
		return $data;
	}

	private function getOpenGarageIp() {
		$ip = trim(file_get_contents('/home/user/.ip_garage'));
		if (!$ip) {
			error_log('unable to find OpenGarage IP');
			throw new Exception('IP find failure', 1);
		}
		return $ip;
	}

	public function getOpenGarageStatus() {
		$result = $this->callOpenGarage('jc');
		if (isset($result['door'])) {
			$status = ($result['door']) ? "open" : "close";
			$vehicle = ($result['vehicle']) ? "with vehicle inside" : "";
			return [$status, $vehicle];
		} else {
			error_log("unable to get garage status");
			throw new Exception('OpenGarageStatus Error', 1);
		}
	}

	public function triggerOpenGarage($action) {
		$this->slack_url = "https://hooks.slack.com/services/rest-of-url";
		if ($this->getOpenGarageStatus()[0] != $action) {
			$result = $this->callOpenGarage('cc', [$action=>1]);
			$msg = "triggered {$action} garage";
			$this->logToFile($msg);
			$this->postToSlack($msg);
		} else {
			$this->logToFile("not triggering {$action} garage");
		}
	}
}