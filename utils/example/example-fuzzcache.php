<?php

include_once __DIR__ . "/../PHPSHMCache.php";
$startTime = microtime(true);
function no_fuzzcache()
{
    $servername = "localhost";
    $username = "test";
    $password = "123456";
    $dbname = "cachedb";
    $conn = PHPSHMCache\sqlWrapperFunc('mysqli_connect', array($servername, $username, $password, $dbname));
    if (!$conn) {
        die("Connection failed: " . PHPSHMCache\sqlWrapperFunc('mysqli_connect_error', array()));
    }
    $result = PHPSHMCache\sqlWrapperFunc('mysqli_query', array($conn, "SELECT * FROM users"));
    foreach (PHPSHMCache\sqlWrapperFunc('mysqli_fetch_array', array($result)) as $row) {
    }
    PHPSHMCache\sqlWrapperFunc('mysqli_close', array($conn));
}
for ($i = 0; $i < 10000; $i++) {
    no_fuzzcache();
}
$endTime = microtime(true);
echo "Using time (10000 rounds)\n";
echo $endTime - $startTime;
echo "\n";
