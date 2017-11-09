<?php

include("config.php");
include_once('function.php');

$json = json_decode($input);

$data['device_id'] = isset($json->device_id) ? $json->device_id : "";
$data['info_id'] = isset($json->info_id) ? $json->info_id : 0;

$connection = new PDO(
    "mysql:dbname=$mydatabase;host=$myhost;port=$myport",
    $myuser, $mypass
);
    
if ($data['device_id'] != "") {
    
    $user_id = get_user_id($data['device_id']); 
            
    // create record if not exists
    $sql1 = "INSERT INTO inbox (device_id, info_id, last_update)
        VALUES (:device_id, :info_id, NOW())
        ON DUPLICATE KEY UPDATE
        last_update = NOW()
    ";
    $statement1 = $connection->prepare($sql1);
    $statement1->bindParam(":device_id", strval($user_id));
    $statement1->bindParam(":info_id", $data['info_id']);
    $statement1->execute();

    $data['info_id'] = intval($data['info_id']);
    $data['affected_row'] = $statement1->rowCount();
    $data['error'] = 0;
    $data['message'] = 'Success';
    
} else {
    
    $data['error'] = 1;
    $data['message'] = 'Error: Device ID is required';
    
}

return $data;
