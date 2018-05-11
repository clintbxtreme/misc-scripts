<?php

if (!isset($_REQUEST['scrt']) || $_REQUEST['scrt'] != "sdflkqern,notihafnglkajn") exit();

$directory = "/home/user/Videos/Movies";
$move_directory = "/mount/media/Other Videos/Movies";

$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';
if ($action == 'archive') {
	if (isset($_REQUEST['files']) && $_REQUEST['files']) {
		$failed = array();
		foreach ($_REQUEST['files'] as $file) {
			$success = rename("{$directory}/{$file}", "{$move_directory}/{$file}");
			if (!$success) {
				$failed[] = $file;
			}
		}
		if ($failed) {
			print "unable to move:</br>";
			foreach ($failed as $file) {
				print "{$file}</br>";
			}
			exit;
		}
	}
	header("Location: /videoArchive.php");
	exit;
}

$files = scandir($directory);
$files_html = '';
foreach ($files as $file) {
	if (in_array($file, array(".",".."))) continue;
	$files_html .= "<div style='margin: 0 auto; max-width:500px; display:flex; font-size:xx-large; line-height:100px; border:1px dashed;'>
						<span style='border: 1px solid; width:90px; height:90px; align-self:center; margin: 5px;'>
							<input type='checkbox' name='files[]' value=\"{$file}\"
							style='width:90px; height:90px; margin:0;'>
						</span>
						<span style='vertical-align:middle; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title=\"{$file}\">
							{$file}
						</span>
					</div>";
}
print "<form action='/videoArchive.php'>
		{$files_html}
		<div style='margin: 0 auto; max-width:500px; display:flex; position: fixed; bottom: 0; left: 0; right: 0;'>
			<button type='submit' name='action' value='archive' style='width:500px; height:100px; font-size:30px;'>Archive</button>
		</div>
	</form>";
