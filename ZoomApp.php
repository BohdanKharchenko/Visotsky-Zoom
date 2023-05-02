<?php
require_once "Utils.php";


class ZoomApp {
 
	static private $init_apps_arr = [
		[
			"id" => "account_id", //Alert! Input information about your zoom markeplace app 
			"username" => "client_id",
			"password" => "client_secret_key",

			"accounts" => [
				"account_name_1" => "account_zoom_id_1", //Alert! Input information about your zoom account
				"account_name_2" => "account_zoom_id_2"
			]
		]
	];

	static private $multiton_instances = [];

	private $id;
	private $username;
	private $password;
	private $accounts;
	private $access_token;


	static public function get_by_account_id($account_id) {
		self::init();

		foreach (self::$multiton_instances as $zoom_app) {
			$accounts = $zoom_app->get_accounts();

			foreach ($accounts as $account_name_inner => $account_id_inner) {
				if ($account_id == $account_id_inner) {
					return $zoom_app;
				}
			}
		}
	}

	static public function get_by_app_id($app_id) {
		self::init();

		foreach (self::$multiton_instances as $zoom_app) {
			if ($zoom_app->get_id() == $app_id) {
				return $zoom_app;
			}
		}
	}

	static public function get_by_account_name($account_name) {
		self::init();

		foreach (self::$multiton_instances as $zoom_app) {
			$accounts = $zoom_app->get_accounts();

			foreach ($accounts as $account_name_inner => $account_id_inner) {
				if ($account_name == $account_name_inner) {
					return $zoom_app;
				}
			}
		}
	}

	static public function get_account_id_by_name($account_name) {
		self::init();

		foreach (self::$multiton_instances as $zoom_app) {
			$accounts = $zoom_app->get_accounts();

			foreach ($accounts as $account_name_inner => $account_id_inner) {
				if ($account_name == $account_name_inner) {
					return $account_id_inner;
				}
			}
		}
	}

	static public function get_account_name_by_id($account_id) {
		self::init();

		foreach (self::$multiton_instances as $zoom_app) {
			$accounts = $zoom_app->get_accounts();

			foreach ($accounts as $account_name_inner => $account_id_inner) {
				if ($account_id == $account_id_inner) {
					return $account_name_inner;
				}
			}
		}
	}

	private function __construct($id, $username, $password, $accounts) {
		$this->id = $id;
		$this->username = $username;
		$this->password = $password;
		$this->accounts = $accounts;

		$this->set_access_token();
	}

	public function delete_recording($rec_uuid) {
		$url = 'https://api.zoom.us/v2/meetings/'.$rec_uuid.'/recordings';

		$headers = array(
			'Authorization: Bearer '.$this->access_token,
			'Content-Type: application/json',
		);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);
		$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $response_code;
	}

	function get_meetings($account_id) {
		$url = 'https://api.zoom.us/v2/users/'.$account_id.'/meetings';

		$headers = array(
			'Authorization: Bearer ' . $this->access_token,
			'Content-Type: application/json'
		);

		$params = array(
			'type' => 'upcoming_meetings',
			'page_size' => 300
		);

		$query_string = http_build_query($params);

		$url .= '?' . $query_string;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($response, true);

		return $data;
	}

	public function create_meeting($account_id, $name, $date, $start_time, $end_time) {
		global $accounts_id;

		$duration = intdiv(Utils::get_timestamp($date, $end_time) - Utils::get_timestamp($date, $start_time), 60);

		$url = 'https://api.zoom.us/v2/users/'.$account_id.'/meetings'; // URL API-сервера

		$meeting_params = array(
			'topic' => $name,
			'timezone' => 'Europe/Moscow',
			'start_time' => date("Y-m-d\TH:i:s", Utils::get_timestamp($date, $start_time)),
			'duration' => $duration,
			'type' => 2,
			'password' => "234194",
			'settings' => array(
				'auto_recording' => 'cloud',
				'allow_multiple_devices' => false,
				'meeting_authentication' => false,
				"breakout_room" => array(
					"enable" => false
				),
				"calendar_type" => 2,
				"encryption_type" => "enhanced_encryption",
				"focus_mode" => false,
				"global_dial_in_countries" => ["US"],
				"host_video" => false,
				"join_before_host" => true,
				"jbh_time" => 0,
				"mute_upon_entry" => true,
				"participant_video" => true,
				"private_meeting" => false,
				"registrants_confirmation_email" => true,
				"registrants_email_notification" => true,
				"show_share_button" => false,
				"host_save_video_order" => false,
				"alternative_host_update_polls" => false
			)
		);

		$json = json_encode($meeting_params);

		$headers = array(
			'Authorization: Bearer ' . $this->access_token,
			'Content-Type: application/json',
			'Content-Length: ' . strlen($json)
		);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($response, true);

		return array("account" => array_search($account_id, $this->accounts), "join_url" => $data["join_url"], "id" => $data["id"], "password" => $data["password"]);
	}

	public function get_id() {
		return $this->id;
	}

	public function get_username() {
		return $this->username;
	}

	public function get_password() {
		return $this->password;
	}

	public function get_accounts() {
		return $this->accounts;
	}

	public function view_access_token() {
		return $this->access_token;
	}

	private static function init() {
		if (count(self::$multiton_instances) == 0) {
			foreach (self::$init_apps_arr as $account_init) {
				self::$multiton_instances[] = new ZoomApp(
					$account_init["id"],
					$account_init["username"],
					$account_init["password"],
					$account_init["accounts"]
				);
			}
		}
	}

	private function set_access_token() {
		if (!isset($this->access_token)) {
			$url = 'https://zoom.us/oauth/token';

			$username = $this->username;
			$password = $this->password;
			$id = $this->id;

			$params = array(
				'grant_type' => 'account_credentials', // параметр 1
				'account_id' =>  $id // параметр 2
			);

			$query_string = http_build_query($params); // преобразуем массив параметров в строку запроса

			$url .= '?' . $query_string; // добавляем строку запроса к URL

			$auth = base64_encode($username . ':' . $password); // кодируем имя пользователя и пароль в формате Base64

			$headers = array(
				'Authorization: Basic ' . $auth, // добавляем заголовок с авторизацией в формате Base64
				'Content-Type: application/json', // указываем тип контента
			);

			$ch = curl_init(); // инициализируем сеанс cURL

			curl_setopt($ch, CURLOPT_URL, $url); // устанавливаем URL с query string параметрами
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // устанавливаем заголовки
			curl_setopt($ch, CURLOPT_POST, true); // устанавливаем метод запроса POST
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // устанавливаем флаг возврата результата в виде строки

			$response = curl_exec($ch); // выполняем запрос
			curl_close($ch); // закрываем сеанс cURL

			$data = json_decode($response, true); // преобразуем полученный JSON в массив

			$this->access_token = $data["access_token"];
		}
	}
}
?>