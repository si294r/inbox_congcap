<?php

function get_user_id($device_id) 
{
    global $connection, $IS_DEVELOPMENT;
    
    $key = $IS_DEVELOPMENT ? CACHE_USER_DEV . $device_id : CACHE_USER . $device_id;
    $user_id = apcu_fetch($key);

    if ($user_id === FALSE) {        
        $sql = "INSERT INTO device_user (device_id, create_date) "
        . "SELECT * FROM (SELECT :device_id, NOW()) t WHERE NOT EXISTS ("
        . "  SELECT 1 FROM device_user WHERE device_id = :device_id1"
        . ")";
        $statement1 = $connection->prepare($sql);
        $statement1->bindParam(":device_id", $device_id);
        $statement1->bindParam(":device_id1", $device_id);
        $statement1->execute();

        $sql = "SELECT * FROM device_user WHERE device_id = :device_id";
        $statement1 = $connection->prepare($sql);
        $statement1->execute(array(':device_id' => $device_id));
        $row = $statement1->fetch(PDO::FETCH_ASSOC);

        $user_id = $row['user_id'];    
        apcu_store($key, $user_id, 900);
    }
    
    return $user_id;
}
