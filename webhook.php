<?php

ini_set("max_execution_time", "7200");

$secret_token = 'your_secret_key'; //Alert! Input secret key of your zoom marketplace app

$webhook_logfile = "./logs/".basename(__FILE__, '.php').".log.txt";
if (!file_exists($webhook_logfile)) {
	if (!is_dir("logs")) {
		mkdir("logs");
	}
	file_put_contents($webhook_logfile, "");
}

$cleaner_logfile = "./logs/zoom-cleaner.log.txt";

if (!file_exists($cleaner_logfile)) {
	file_put_contents($cleaner_logfile, "");
}

$input = json_decode(file_get_contents("php://input"), true);

if ($input["event"] == "endpoint.url_validation") {
	file_put_contents($webhook_logfile, date("Y-m-d H:i:s")."\t".json_encode($input)."\n", FILE_APPEND | LOCK_EX);
	$data = array("plainToken" => $input["payload"]["plainToken"], "encryptedToken" => hash_hmac('sha256', $input["payload"]["plainToken"], $secret_token));
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data);
	return;
} else if ($input["event"] == "recording.completed") {
	require "downloader.php";
    download_record($input);
} else if ($input["event"] == "recording.batch_recovered") {
	file_put_contents($cleaner_logfile, date("Y-m-d H:i:s")."\t{"."type: recover_manual, rec_uuid: ".$input["payload"]["object"]["meetings"][0]["meeting_uuid"]."}\n", FILE_APPEND | LOCK_EX);
} else if ($input["event"] == "recording.batch_trashed") {
	file_put_contents($cleaner_logfile, date("Y-m-d H:i:s")."\t{"."type: delete_manual, rec_uuid: ".$input["payload"]["object"]["meeting_uuids"][0]."}\n", FILE_APPEND | LOCK_EX);
} else {
    file_put_contents($webhook_logfile, date("Y-m-d H:i:s")."\t".json_encode($input)."\n", FILE_APPEND | LOCK_EX);
}
?>