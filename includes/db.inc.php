<?php
$dsn = "mysql:host=".$_ENV['DATABASE_HOST'].";dbname=".$_ENV['DATABASE_NAME'].";charset=utf8";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASS'], $opt);
} catch (PDOException $e) {
    $logger->critical("FAILED TO CONNECT TO DATABASE USING CONFIG",[
        "Error" =>$e->getMessage(),
        "CONFIG"=>[
           "Host"=>$_ENV['DATABASE_HOST'],
           "Database"=>$_ENV['DATABASE_NAME'],
           "Username"=>$_ENV['DATABASE_USER'],
           "Password"=>$_ENV['DATABASE_PASS']
        ]
    ]);
    exit();
}
?>
