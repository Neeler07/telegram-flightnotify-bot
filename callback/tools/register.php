<?php
$options = array(
                 'trace' => true,
                 'exceptions' => 0,
                 'login' => 'Login',
                 'password' => 'Password',
                 );
$client = new SoapClient('http://flightxml.flightaware.com/soap/FlightXML2/wsdl', $options);

$params = array("address" => "http://callback/", "format_type" => "json/post");
$result = $client->RegisterAlertEndpoint($params);
print_r($result);

?>
