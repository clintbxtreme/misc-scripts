<?php
$scrt = 'asdfpwoiernakjnvaskfn';

if ($_REQUEST['scrt'] != $scrt) {
	exit("Permissions Denied");
}

header("Access-Control-Allow-Origin: *");
$filename = '/tmp/shopStevieDetailsGrabber.txt';
switch ($_REQUEST['a']) {
	case 'get':
		echo file_get_contents($filename);
		unlink($filename);
		break;

	case 'append':
		if (!file_exists($filename)) {
			$base = "Title,Body (HTML),Vendor,Type,Published,Option1 Name,Handle,Option1 Value,Variant SKU,Variant Grams,Variant Inventory Tracker,Variant Inventory Qty,Variant Inventory Policy,Variant Fulfillment Service,Variant Price,Variant Requires Shipping,Variant Taxable,Variant Weight Unit,Image Src,Image Position\n";
			file_put_contents($filename, $base);
		}
		file_put_contents($filename, $_REQUEST['d'], FILE_APPEND);
		break;

	default:
		# code...
		break;
}