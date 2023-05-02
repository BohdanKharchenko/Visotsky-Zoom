<?php

require_once './google-api/vendor/autoload.php';
require_once 'ZoomApp.php';

ini_set("max_execution_time", "7200");

$credentials_path = "google-key.json"; //Alert! Input path to your json (json key to your google api for service account)

$upload_logfile = "./logs/drive-upload.log.txt";
if (!file_exists($upload_logfile)) {
	if (!is_dir("logs")) {
		mkdir("logs");
	}
	file_put_contents($upload_logfile, "");
}


if (array_key_exists("server_name", $_GET) && file_exists($file_name = urldecode($_GET["server_name"]))) {
	$upload_name = urldecode($_GET["upload_name"]);
    $account_id = urldecode($_GET["account_id"]);
	$f_id = "folder_id";//Alert! Input id to your folder for uploading files

	putenv('GOOGLE_APPLICATION_CREDENTIALS='.$credentials_path);
	$client = new Google\Client();
	$client->setApplicationName('Drive Uploader');
	$client->addScope(Google\Service\Drive::DRIVE);
	$client->useApplicationDefaultCredentials();

	$service = new Google\Service\Drive($client);

	$file = new Google\Service\Drive\DriveFile();
	$file->name = $upload_name;
	$file->parents = array($f_id);
	$chunkSizeBytes = 1 * 1024 * 1024;

	// Call the API with the media upload, defer so it doesn't immediately return.
	$client->setDefer(true);
	$request = $service->files->create($file, array("supportsAllDrives" => true));

	// Create a media file upload to represent our upload process.
	$media = new Google\Http\MediaFileUpload(
		$client,
		$request,
		'video/mp4',
		null,
		true,
		$chunkSizeBytes
	);
	$media->setFileSize(filesize($file_name));

	// Upload the various chunks. $status will be false until the process is
	// complete.
	$status = false;
	$handle = fopen($file_name, "rb");
	while (file_exists($file_name = urldecode($_GET["server_name"])) && !$status && !feof($handle)) {
		// read until you get $chunkSizeBytes from $file_name
		// fread will never return more than 8192 bytes if the stream is read
		// buffered and it does not represent a plain file
		// An example of a read buffered file is when reading from a URL
		$chunk = readVideoChunk($handle, $chunkSizeBytes);
		$status = $media->nextChunk($chunk);
	}

	// The final value of $status will be the data from the API for the object
	// that has been uploaded.
	$result = false;
	if ($status != false) {
		$result = $status;
	}

	fclose($handle);

	file_put_contents($upload_logfile, date("Y-m-d H:i:s")."\t".$upload_name." (".$f_id.")\n", FILE_APPEND | LOCK_EX);

	$delete_response_code = ZoomApp::get_by_account_id($account_id)->delete_recording($_GET["uuid"]);

	file_put_contents("./logs/zoom-cleaner.log.txt", date("Y-m-d H:i:s")."\t{"."type: delete_auto, response_status: ".$delete_response_code.", rec_name: ".$upload_name.", rec_uuid: ".$_GET["uuid"]."}\n", FILE_APPEND | LOCK_EX);

	unlink($file_name);
}

function readVideoChunk($handle, $chunkSize)
{
    $byteCount = 0;
    $giantChunk = "";
    while (file_exists($file_name = urldecode($_GET["server_name"])) && !feof($handle)) {
        // fread will never return more than 8192 bytes if the stream is read
        // buffered and it does not represent a plain file
        $chunk = fread($handle, 8192);
        $byteCount += strlen($chunk);
        $giantChunk .= $chunk;
        if ($byteCount >= $chunkSize) {
            return $giantChunk;
        }
    }
    return $giantChunk;
}

?>