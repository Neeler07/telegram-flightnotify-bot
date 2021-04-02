<?php
include __DIR__.'/config.php';
// Load composer
require __DIR__ . '/vendor/autoload.php';

/* Database Config */
$db_host = 'localhost';
$db_name = 'flybot';
$db_user = 'root';
$db_pass = 'dalida1959';

$db_options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
);

/* DB Connection */
$DBH = new PDO("mysql:host=".$db_host.";dbname=".$db_name, $db_user, $db_pass, $db_options);
$DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );

/* Telegram Bot Config */
$bot_api_key  = '785489188:AAGt8L3AkFz9cWp6GNpBqJPGxcV-zYm18JI';
$bot_username = 'FlightNotifyBot';

$commands_paths = [__DIR__ . '/Commands/'];

$soapOptions = [
    'trace' => true,
    'exceptions' => 0,
    'login' => $config['FAv2']['username'],
    'password' => $config['FAv2']['apiKey'],
];

$Soap = new SoapClient('http://flightxml.flightaware.com/soap/FlightXML2/wsdl', $soapOptions);

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);

   	//Longman\TelegramBot\TelegramLog::initDebugLog(__DIR__ . "/_debug.log");
   	//Longman\TelegramBot\TelegramLog::initErrorLog(__DIR__ . "/_error.log");
   	//Longman\TelegramBot\TelegramLog::initUpdateLog(__DIR__ . "/_update.log");

	$telegram->addCommandsPaths($commands_paths);

    $telegram->handle();


} catch (Longman\TelegramBot\Exception\TelegramException $e) {
     echo $e->getMessage();
}

function executeCurlRequest($endpoint, $queryParams) {
    $username = "Neeler";
    $apiKey = "bdef7006cb0ea4b7026dc3da47f90cc18cdcc2e2";
	$fxmlUrl = "https://flightxml.flightaware.com/json/FlightXML3/";

	$url = $fxmlUrl . $endpoint . '?' . http_build_query($queryParams);
    
    $ch = curl_init($url);
	curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $apiKey);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	if ($result = curl_exec($ch)) {
		curl_close($ch);
		return $result;
	}
	return;
}
