<?php

$json = json_decode($input);

$data['user_id'] = isset($json->user_id) ? $json->user_id : "";
$data['promocode'] = isset($json->promocode) ? $json->promocode : "";
$data['os'] = isset($json->os) ? $json->os : "";

if (trim($data['user_id']) == "") {
    return array(
        "error" => 1,
        "message" => "Error: user_id is empty"
    );
}
if (trim($data['promocode']) == "") {
    return array(
        "error" => 2,
        "message" => "Error: promocode is empty"
    );
}

include("config.php");
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

$sql1 = "SELECT promocode_id, promocode, limit_user,
	(select count(*) from promocode_claim where promocode_id = promocode.promocode_id) as promocode_count,
	(select count(*) from promocode_claim where promocode_id = promocode.promocode_id and user_id = :user_id) as promocode_taken
    FROM promocode WHERE promocode = :promocode 
    AND os IN ('All', :os)
    AND status = 1 
    AND $filter_time
    ";
$statement1 = $connection->prepare($sql1);
$statement1->bindParam(":promocode", $data['promocode']);
$statement1->bindParam(":os", $data['os']);
$statement1->bindParam(":user_id", $data['user_id']);
$statement1->execute();
$row = $statement1->fetch(PDO::FETCH_ASSOC);

if (isset($row['promocode']) && $row['promocode'] == $data['promocode']) {

    if ($row['promocode_taken'] > 0) {
        return array(
            "error" => 3,
            "message" => "Error: user already claim this promocode"
        );                    
    } elseif ($row['promocode_count'] < $row['limit_user']) {
        
        $sql2 = "INSERT IGNORE INTO promocode_claim (promocode_id, user_id, last_update) "
                . "VALUES (:promocode_id, :user_id, NOW())";
        $statement2 = $connection->prepare($sql2);
        $statement2->bindParam(":promocode_id", $row['promocode_id']);
        $statement2->bindParam(":user_id", $data['user_id']);
        $statement2->execute();
        $affected_row = $statement2->rowCount();

        if ($affected_row > 0) {
            
            $sql3 = "INSERT INTO master_inbox (title, message, reward_1, reward_2, reward_3, target_device, target_fb, os, status, valid_from, valid_to)
                    SELECT title, message, reward_1, reward_2, reward_3, :target_device, '', os, 1, null, null
                    FROM promocode WHERE promocode_id = :promocode_id ";
            $statement3 = $connection->prepare($sql3);
            $statement3->bindParam(":target_device", $data['user_id']);
            $statement3->bindParam(":promocode_id", $row['promocode_id']);
            $statement3->execute();
            
            return array(
                "affected_row" => $affected_row,
                "error" => 0,
                "message" => "Success"
            );                        
        } else {
            return array(
                "error" => 3,
                "message" => "Error: user already claim this promocode"
            );            
        }
        
    } else {
        return array(
            "error" => 4,
            "message" => "Error: user who claimed this promocode has reached limit"
        );
    }
    
} else {
    return array(
        "error" => 5,
        "message" => "Error: promocode is not valid"
    );
}
