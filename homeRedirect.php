<?php
$path = "";
$url = "http://my-server.com";
$url2 = "http://192.168.1.11";
if (isset($_REQUEST['p'])) {
	$path = "/{$_REQUEST['p']}/";
	unset($_REQUEST['p']);
}

$queryParams = http_build_query($_REQUEST);
$queryParams = ($queryParams) ? "?{$queryParams}" : "";

$paramsUrl = "{$url}{$path}{$queryParams}";
$paramsUrl2 = "{$url2}{$path}{$queryParams}";
?>

<!doctype html>
<html>
	<head>
		<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
		<script src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
		<script type="text/javascript">
			var url = '<?= $url ?>';
			var url2 = '<?= $url2 ?>';
			$.ajax({
				url: url2,
				cache: false,
				crossDomain: true,
				dataType: "script",
				timeout: 300,
				context: document.body,
				success: function(data, textStatus, jqxhr) {
					window.location = '<?= $paramsUrl2 ?>'
				},
				error: function(jqxhr, textStatus, errorThrown) {
					window.location = '<?= $paramsUrl ?>'
				}
			})

		</script>
	</head>
</html>

