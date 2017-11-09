<?php

include("config.php");
include_once('function.php');

$json = json_decode($input);

$data['device_id'] = isset($json->device_id) ? $json->device_id : "";
$data['os'] = isset($json->os) ? $json->os : "";
$data['limit'] = isset($json->limit) ? $json->limit : 100;

$connection = new PDO(
    "mysql:dbname=$mydatabase;host=$myhost;port=$myport",
    $myuser, $mypass
);
    
if ($IS_DEVELOPMENT == false) {
    $filter_time = "NOW() BETWEEN COALESCE(valid_from, NOW()) AND COALESCE(valid_to, NOW())"; 
} else {
    $iservice = "gettime-dev".$BUILD_TYPE;
    $result_gettime = file_get_contents('http://alegrium5.alegrium.com/congcap/cloudsave/?'.$iservice, null, stream_context_create(
            array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json'. "\r\n"
                    . 'x-api-key: ' . X_API_KEY_TOKEN . "\r\n"
                    . 'Content-Length: ' . strlen('{}') . "\r\n",
                    'content' => '{}'
                )
            )
        )
    );
    $result_gettime = json_decode($result_gettime, true);
    $timestamp = $result_gettime['timestamp'];
    
    $filter_time = "$timestamp BETWEEN COALESCE(UNIX_TIMESTAMP(valid_from), $timestamp) AND COALESCE(UNIX_TIMESTAMP(valid_to), $timestamp)";
}

if ($data['device_id'] != "") {
    
    $user_id = get_user_id($data['device_id']);
            
    $sql1 = "
        SELECT master_inbox.*,
            COALESCE(inbox.device_id, :device_id) device_id
        FROM master_inbox 
        LEFT JOIN inbox 
            ON master_inbox.info_id = inbox.info_id
            AND inbox.device_id = :device_id
        WHERE 
            (
		master_inbox.target_device is null 
                OR master_inbox.target_device = ''
                OR master_inbox.target_device = :device_id
            )
            AND master_inbox.os IN ('All', :os)
            AND master_inbox.status = 1
            AND $filter_time
            AND inbox.device_id IS NULL
        ORDER BY COALESCE(valid_from, NOW()), info_id
        LIMIT {$data['limit']}
    ";
    $statement1 = $connection->prepare($sql1);
    $statement1->bindParam(":device_id", strval($user_id));
    $statement1->bindParam(":os", $data['os']);
    $statement1->execute();
    $row_inbox = $statement1->fetchAll(PDO::FETCH_ASSOC);

    $data['inbox'] = $row_inbox;

} else {
    
    $data['error'] = 1;
    $data['message'] = 'Error: Device ID is required';
    
}

return $data;


