<?php

class Utils {
	public static function get_timestamp($date, $time) {
		$d = DateTime::createFromFormat('d.m.y H:i', $date." ".$time);
		return $d->getTimestamp();
	}
}
?>