<?php
ini_set("max_execution_time", "7200");
require_once 'ZoomApp.php';

function download_record($input) {
	$lock_file = str_replace("/", "", $input["payload"]["object"]["uuid"]).".lock";

	if (array_key_exists("object", $input["payload"]) && !file_exists($lock_file)) {

		// Определение log файла
		$webhook_filename = basename(debug_backtrace()[0]["file"], '.php');
		$webhook_logfile = "./logs/".$webhook_filename.".log.txt";
		if (!file_exists($webhook_logfile)) {
			if (!is_dir("logs")) {
				mkdir("logs");
			}
			file_put_contents($webhook_logfile, "");
		}

		// проверка не обрабатывалась ли еще запись с таким uuid
		$uuid = str_replace("/", "\\/", $input["payload"]["object"]["uuid"]);
		if (strrpos(file_get_contents($webhook_logfile), $uuid) !== false) {
			return;
		}
		file_put_contents($webhook_logfile, date("Y-m-d H:i:s")."\t".json_encode($input)."\n", FILE_APPEND | LOCK_EX);

		// выбор основных полей записи
		file_put_contents($lock_file, "", LOCK_EX);
		$date = substr($input["payload"]["object"]["start_time"], 0, 10);
		$title = $input["payload"]["object"]["topic"];
		$url = null;
		$file_size = null;

		// выбор проекции записи
		foreach ($input["payload"]["object"]["recording_files"] as $rec_file) {
			if ("active_speaker" == $rec_file["recording_type"]) {
				$url = $rec_file["download_url"];
				$file_size = $rec_file["file_size"];
			}
			if ("shared_screen_with_speaker_view" == $rec_file["recording_type"]) {
				$url = $rec_file["download_url"];
				$file_size = $rec_file["file_size"];
				break;
			}
		}

		// проверка на размер файла выбраной записи
		if ($file_size < 15000000) {
			sleep(1);
			ZoomApp::get_by_app_id($input["payload"]["account_id"])->delete_recording($input["payload"]["object"]["uuid"]);
			unlink($lock_file);
			return;
		}

		// скачивание записи и вызов скрипта drive-upload.php для выгрузки на диск
		$upload_name = $title." ".$date.".mp4";
		$server_name = str_replace(["/", "+", "="], "", $input["payload"]["object"]["uuid"]).".mp4";

		$options = array(
			CURLOPT_FILE => is_resource($server_name) ? $server_name : fopen($server_name, 'w'),
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_URL => $url,
			CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
		  );

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$return = curl_exec($ch);

		if ($return === false) {
		echo curl_error($ch);
		} else {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			curl_setopt($ch, CURLOPT_URL, 'https://tolik.app/zoom/drive-upload.php?'.http_build_query(array("server_name"=>$server_name, "upload_name"=>$upload_name, "account_id"=>$input["payload"]["object"]["host_id"], "uuid"=>$input["payload"]["object"]["uuid"])));
			curl_exec($ch);
			curl_close($ch);
		}
		unlink($lock_file);
	}
}
?>