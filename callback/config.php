<?php
/* Database Config */
$db_host = 'localhost';
$db_name = 'flybot';
$db_user = 'xxx';
$db_pass = 'xxx';

$db_options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
);

/* DB Connection */
$DBH = new PDO("mysql:host=".$db_host.";dbname=".$db_name, $db_user, $db_pass, $db_options);

?>