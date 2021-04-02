<?php
include __DIR__.'/config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	$postdata = file_get_contents("php://input");

	$jsonData = json_decode($postdata, true);

	if(!is_array($jsonData)) {
		header("HTTP/1.1 403 Forbidden" );
		exit;	
	}

	$SQL = $DBH->prepare("INSERT INTO `callback` SET `alert_id` = :alert_id, `eventcode` = :eventcode, `jsonData` = :jsonData, `receivedDate` = UNIX_TIMESTAMP()");
	$SQL->execute([
		'alert_id' 	=> $jsonData['alert_id'],
		'eventcode' => $jsonData['eventcode'],
		'jsonData' 	=> $postdata
	]);

	echo 'OK';
	exit;

} else {
	header("HTTP/1.1 403 Forbidden" );
	echo 'Access Deny';
	exit;
}

?>