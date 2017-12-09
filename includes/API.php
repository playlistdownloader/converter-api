<?php
namespace Tools;

class API
{
    public static $key;
    public static $length;
    public static function init()
    {
        global $_ENV;
        global $config;
        self::$key = $_ENV['ENCRYPT_KEY'];
        self::$length = $config['api']['keyLength'];
    }

    public static function generateKey()
    {
        self::init();
        return bin2hex(random_bytes(self::$length));
    }

    public static function validateAPI($key, $pdo)
    {
        $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE api_key=? AND expiry > NOW()');
        $stmt->bindParam(1, $key);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return False;
        } else {
            return true;
        }
    }
}
