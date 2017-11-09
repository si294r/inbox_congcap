<?php

include("/var/www/mysql-config2.php");

$mydatabase = $IS_DEVELOPMENT ? "congcapdev" : "congcap";


define('CACHE_USER_DEV', "congcapdev_user_");
define('CACHE_USER', "congcap_user_");

