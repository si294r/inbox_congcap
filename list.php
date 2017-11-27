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
    $result_gettime = file_get_contents($url_static_time.'?'.$iservice, null, stream_context_create(
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
            COALESCE(inbox.device_id, :device_id) device_id,
            CASE WHEN inbox.device_id is null THEN 0 ELSE 1 END is_claimed
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
            AND (inbox.device_id is null OR inbox.is_deleted = 0)
        ORDER BY pinned DESC, COALESCE(valid_from, NOW()), info_id
        LIMIT {$data['limit']}
    ";
    $statement1 = $connection->prepare($sql1);
    $statement1->bindParam(":device_id", strval($user_id));
    $statement1->bindParam(":os", $data['os']);
    $statement1->execute();
    $row_inbox = $statement1->fetchAll(PDO::FETCH_ASSOC);

    foreach ($row_inbox as $k=>$v) {
        $row_inbox[$k]['info_id'] = intval($v['info_id']);
        $row_inbox[$k]['use_webview'] = intval($v['use_webview']);
        $row_inbox[$k]['pinned'] = intval($v['pinned']);
        $row_inbox[$k]['status'] = intval($v['status']);
        $row_inbox[$k]['is_claimed'] = intval($v['is_claimed']);
    }
    
    $data['inbox'] = $row_inbox;

} else {
    
    $data['error'] = 1;
    $data['message'] = 'Error: Device ID is required';
    
}

return $data;


