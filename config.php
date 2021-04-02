<?php

/* Database Config */
$config['DB'] = 
[
	'db_host' => 'localhost',
	'db_name' => 'flybot',
	'db_user' => 'xxx',
	'db_pass' => 'xxx',
	'db_options' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
];

/* FA XML v3 */
$config['FAv3'] = 
[
	'username' 	=> 'Login',
	'apiKey' 	=> 'Key'
];

/* FA XML v2 */
$config['FAv2'] = 
[
	'username' 	=> 'Login',
	'apiKey' 	=> 'Key'
];

/* Telegram */
$config['Telegram'] =
[
	'bot_api_key' => 'Telegram Key',
	'bot_username' => 'Bot Username'
];
?>