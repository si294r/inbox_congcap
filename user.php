<?php

include("config.php");
include_once('function.php');

$json = json_decode($input, true);
$device_id = $json["device_id"];

$connection = new PDO(
    "mysql:dbname=$mydatabase;host=$myhost;port=$myport",
    $myuser, $mypass
);

$response = array(
    'device_id' => $device_id,
    'user_id' => get_user_id($device_id),
);

return $response;
