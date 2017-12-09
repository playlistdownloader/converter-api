<?php
namespace Tools;
class Hash
{
	static $key;
	static $length;

	public static function init() {
		global $_ENV;
		self::$key = $_ENV['ENCRYPT_KEY'];
	    self::$length = $_ENV['HASH_LENGTH'];
	}

	static function encrypt($string) {
		self::init();
		$key = self::$key;
		$result = '';
		for($i=0; $i<strlen($string); $i++) {
			$char = substr($string, $i, 1);
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$char = chr(ord($char)+ord($keychar));
			$result.=$char;
		}
		return rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
	}

	static function decrypt($string) {
		self::init();
		$key = self::$key;
		$result = '';
		$string = base64_decode(str_pad(strtr($string, '-_', '+/'), strlen($string) % 4, '=', STR_PAD_RIGHT));
		for($i=0; $i<strlen($string); $i++) {
			$char = substr($string, $i, 1);
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$char = chr(ord($char)-ord($keychar));
			$result.=$char;
		}
		return $result;
	}
}
