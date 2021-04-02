<?php
// Load composer
require __DIR__ . '/vendor/autoload.php';

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Entities\InlineKeyboard;

class BotCore {
	
	private $config;
	private $DBH;

	/* Debug */
	private $debug = true;

	/* Telegram */
	private $t;

	function __construct($config) {
		$this->config = $config;
		$this->DBConnect();
	}

    /* DB Connection */
    private function DBConnect() {
        $this->DBH = new PDO("mysql:host=".$this->config['DB']['db_host'].";dbname=".$this->config['DB']['db_name'], 
        	$this->config['DB']['db_user'], 
        	$this->config['DB']['db_pass'], 
        	$this->config['DB']['db_options']
        );
    }

    /*
    processNewSubscriptions
	Обрабатываем новые подписки
    */
    public function processNewSubscriptions() {

    	$SQL = $this->DBH->query("SELECT * FROM `eventsQuery` WHERE `pushNotify` = 0 AND `error` = ''");

    	/* Кол-во подписок */
    	$numsNewSubscriptions = $SQL->rowCount();

    	$this->toLog('Новых подписок: '.$numsNewSubscriptions);

    	/* Нету подписок, выходим */
    	if($numsNewSubscriptions == 0) return;
    

    	/* Loop новых подписок */
    	while($row = $SQL->fetch()) {
    		$this->toLog('Подписка на рейс '.$row['ident']);

    		/* Может мы уже проверяли некоторое время назад, подождем */

    		if((date('U') - $row['faUpdate']) < (4 * 3600)) {
    			$this->toLog('Пропустим этот рейс, уже недавно обновили данные');
    			continue;
    		}

    		/* Get ALert ID */
    		if($row['alertId'] == 0) $this->setAlert($row);
    	}
    }

    public function processCallabacks() {
		$this->toLog('Обрабатываем свежие колбеки');

		$SQL = $this->DBH->query("SELECT `callback`.`id`, `callback`.`alert_id`, `callback`.`eventcode`, `callback`.`jsonData`, `eventsQuery`.`airlineIata`, `eventsQuery`.`fa_ident`, `eventsQuery`.`flightNumber`, `eventsQuery`.`telegramChatId`, `eventsQuery`.`enroute`, `eventsQuery`.`enrouteUpdate`, `eventsQuery`.`ident`, `eventsQuery`.`enrouteFail` FROM `callback` LEFT JOIN `eventsQuery` ON `eventsQuery`.`alertId` = `callback`.`alert_id` WHERE `callback`.`telegramed` = 0"); 

		$newEventsCount = $SQL->rowCount();

		/* Нет новых событий */
		if($newEventsCount == 0) {
			$this->toLog('Новых колбеков не обнаружено');
			return;
		}

		/* Loat Telegram */
		$this->t = new Longman\TelegramBot\Telegram($this->config['Telegram']['bot_api_key'], $this->config['Telegram']['bot_username']);

		while($row = $SQL->fetch()) {

			$eventData = json_decode($row['jsonData'], true);
			//var_dump($eventData);
			$this->toLog('Обрабатываем EventCode. EventCode - '.$eventData['eventcode']);

			switch ($eventData['eventcode']) {
				case 'filed':
					$this->eventFiled($eventData, $row);
					break;
				case 'arrival':
					$this->eventArrival($eventData, $row);
					break;					
				case 'departure':
					$this->eventDeparture($eventData, $row);
					break;
				case 'change':
					$this->eventChange($eventData, $row);
					break;
				case 'delay':
					$this->eventDelay($eventData, $row);
					break;
				case 'minutes_out':
					$this->eventMinutesOut($eventData, $row);
					break;
				case 'diverted':
					$this->eventDiverted($eventData, $row);
					break;	
				case 'cancelled':
					$this->eventCancelled($eventData, $row);
					break;															
			}
		}
    }

    private function sendMAP($faIdent, $telegramChatId, $showAirport = true) {

    	$tmpFname = tempnam("/tmp", 'IMG').'.gif';

        /* Получили Картинку */
        $options = 
        [
            'trace'         => true,
            'exceptions'    => 0,
            'login'         => $this->config['FAv2']['username'],
            'password'      => $this->config['FAv2']['apiKey']
        ];

        $queryParams =   
        [
            'faFlightID' => $faIdent,
			'mapHeight' => 480,
			'mapWidth' => 640,
			'show_data_blocks' => true,
			'layer_on' => array(),
			'layer_off' => array(),
			'show_airports' => $showAirport,
			'latlon_box' => array(),
			'airports_expand_view' => true            
        ]; 

		$SOAP = new SoapClient('http://flightxml.flightaware.com/soap/FlightXML2/wsdl', $options);
		$faData = $SOAP->MapFlightEx($queryParams);

		if(isset($faData->MapFlightExResult)) {
			file_put_contents($tmpFname, base64_decode($faData->MapFlightExResult));

			if(file_exists($tmpFname)) {
				$result = Request::sendPhoto(['chat_id' => $telegramChatId, 'photo' => Request::encodeFile($tmpFname)]);
				//$result = Request::sendPhoto(['chat_id' => 'XXXX', 'photo' => Request::encodeFile($tmpFname)]);
                                    
				unlink($tmpFname); 
			}
		}
    }

    private function eventDiverted($eventData, $queryData) {
    	$msg = '⚠️ Flight '.$eventData['long_desc'];

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

    	if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);    
    	
    		$this->sendMAP($eventData['flight']['faFlightID'], $queryData['telegramChatId']);
    	}
    }

    private function eventMinutesOut($eventData, $queryData) {
    	$msg = $eventData['long_desc'];

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

    	if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);    
    	
    		$this->sendMAP($eventData['flight']['faFlightID'], $queryData['telegramChatId']);
    	}
    }

    private function eventCancelled($eventData, $queryData) {

    	$msg = '⚠️ '.$eventData['long_desc'];

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

    	if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);    
    	}
    }

    private function eventDelay($eventData, $queryData) {

    	$msg = $eventData['long_desc'];

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

    	if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);    
    	}
    }

    private function eventChange($eventData, $queryData) {

    	$msg = $eventData['long_desc'];

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

    	if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);    
    	}
    }

    /* Прибыл */
    private function eventArrival($eventData, $queryData) {

		$inline_keyboard = new InlineKeyboard([
                ['text' => '💲 Help Bot', 'url' => 'https://ko-fi.com/flightbot']
		]);
    
		$msg = '🛬 Flight '.$eventData['long_desc'];
		$msg.= PHP_EOL.PHP_EOL;
		$msg.= 'You can help Bot. Thanks!';

		$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg, 'reply_markup' => $inline_keyboard]);
		///$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg, 'reply_markup' => $inline_keyboard]);

		$SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `enroute` = 7 WHERE `fa_ident` = :fa_ident");
		$SQL->execute(['fa_ident' => $queryData['fa_ident']]);    

		if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);   
		}	
    }

    /* План полета */
    private function eventDeparture($eventData, $queryData) {

    	$msg = '🛫 Flight '.$eventData['long_desc'].PHP_EOL;

		if(isset($eventData['flight']['filed_departuretime']) && isset($eventData['flight']['actualdeparturetime'])) {
			if($eventData['flight']['actualdeparturetime'] > $eventData['flight']['filed_departuretime'] && ($eventData['flight']['actualdeparturetime'] - $eventData['flight']['filed_departuretime']) > 900) {
				$delaySeconds = $eventData['flight']['actualdeparturetime'] - $eventData['flight']['filed_departuretime'];
				$msg.= PHP_EOL.'⚠ Departure delayed are '.$this->secondsToHours($delaySeconds).PHP_EOL.PHP_EOL;
			}
		}

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

    	$this->sendMAP($eventData['flight']['faFlightID'], $queryData['telegramChatId'], false);

    	$SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `enroute` = 1, `faIdent` = :faIdent WHERE `fa_ident` = :fa_ident");
		$SQL->execute(['fa_ident' => $queryData['fa_ident'], 'faIdent' => $eventData['flight']['faFlightID']]);

		if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);   
		}		
    }

    /* План полета */
    private function eventFiled($eventData, $queryData) {

    	$msg = '📅 Flight '.$eventData['long_desc'];

    	$result = Request::sendMessage(['chat_id' => $queryData['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $msg]);
    	//$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $msg]);

		$SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `enroute` = 7, `faIdent` = :faIdent WHERE `fa_ident` = :fa_ident");
		$SQL->execute(['fa_ident' => $queryData['fa_ident'], 'faIdent' => $eventData['flight']['faFlightID']]);

    	if ($result->isOk()) {
    		$SQL = $this->DBH->prepare("UPDATE `callback` SET `telegramed` = 1 WHERE `id` = :id");
    		$SQL->execute(['id' => $queryData['id']]);    
    	}
    }

    private function setAlert($queryData) {

    	$this->toLog('Пробуем подписаться');

		/* Пробуем подписаться ? */
		$options = 
		[
			'trace' 		=> true,
			'exceptions' 	=> 0,
			'login' 		=> $this->config['FAv2']['username'],
			'password' 		=> $this->config['FAv2']['apiKey']
        ];

		$queryParams =   
		[
			"alert_id" => 0,
			'ident'	 => $queryData['ident'],
			"origin" => $queryData['origin'],
			"destination" => $queryData['destination'],
			"aircrafttype" => '',		
			'date_start' => $queryData['departuretime'],
			'date_end' => $queryData['arrivaltime'],
			"enabled" => true,
			"channels" => '{minutes_out 20} {16 e_filed e_departure e_arrival e_diverted e_cancelled e_eta}',
			"max_weekly" => 200			
		]; 

		/* SOAP вызов XML v2 */
        $SOAP 		= new SoapClient('http://flightxml.flightaware.com/soap/FlightXML2/wsdl', $options);
		$SOAPFaData = $SOAP->SetAlert($queryParams);
        $faData 	= json_decode(json_encode($SOAPFaData), True);

        if(isset($faData['SetAlertResult'])) {
        	$this->toLog('Успешно подписались. Alert ID: '.$faData['SetAlertResult']);

            $SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `pushNotify` = 1, `alertId` = :alertId WHERE `fa_ident` = :fa_ident");
            $SQL->execute([
                'alertId' 	=> $faData['SetAlertResult'],
                'fa_ident' 	=> $queryData['fa_ident']
            ]);        	
        } else {
			$this->toLog('Подписаться не получилось не получили (Response: '.json_encode($faData).'), обновим временные метки');

			if((date('U') - $queryData['arrivaltime']) > 43200) {
				$SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `error` = 'No FA Ident received' WHERE `ident` = :ident AND `departuretime` = :departuretime");
				$SQL->execute([
					'ident' 		=> $queryData['ident'],
					'departuretime' => $queryData['departuretime']
				]);

				return;
			}

			$SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `faUpdate` = UNIX_TIMESTAMP() WHERE `ident` = :ident AND `departuretime` = :departuretime");
			$SQL->execute([
					'ident' 		=> $queryData['ident'],
					'departuretime' => $queryData['departuretime']
			]);
        }	
    }

    private function fixDate($date) {
    	return str_replace(['.','/'], '', $date);
    }

    private function faV3call($method, $queryParams) {

    	$this->toLog('Запрос к FA XML v3: '.json_encode($queryParams));

		$response       = $this->executeCurlRequest($method, $queryParams);
		return json_decode($response, true);
    }

    private function getAirportDataByICAO($icao) {

    	$SQL = $this->DBH->prepare("SELECT * FROM `apiWorld`.`airports` WHERE `icao` = :icao LIMIT 1");
    	$SQL->execute([
    		'icao' => $icao
    	]);

    	if($SQL->rowCount() == 0) return false;
    	return $SQL->fetch();
    }

    private function toLog($log) {

    	echo '['.date('Y-m-d H:i:s').'] - '.$log.PHP_EOL;
    }

	private function executeCurlRequest($endpoint, $queryParams) {
	    $fxmlUrl = "https://flightxml.flightaware.com/json/FlightXML3/";

	    $url = $fxmlUrl . $endpoint . '?' . http_build_query($queryParams);
	    
	    $ch = curl_init($url);
	    curl_setopt($ch, CURLOPT_USERPWD, $this->config['FAv3']['username'] . ':' . $this->config['FAv3']['apiKey']);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	    if ($result = curl_exec($ch)) {
	        curl_close($ch);
	        return $result;
	    }
	    return;
	}

	private function secondsToHours($init) {
	    $hours = floor($init / 3600);
	    $minutes = floor(($init / 60) % 60);
	    $seconds = $init % 60;

	    if($hours == 0) return $hours.' h. '.$minutes.' min.';

	    return $minutes.' min.';
	}

	public function processEnroute() {

        /* Loat Telegram */
        $this->t = new Longman\TelegramBot\Telegram($this->config['Telegram']['bot_api_key'], $this->config['Telegram']['bot_username']);

        
        $SQL = $this->DBH->query("SELECT * FROM `eventsQuery` WHERE `enroute` = 1");
        $enrouteCount = $SQL->rowCount();
        if($enrouteCount == 0) return;

        $this->toLog('Обрабатываем рейсы в пути. Кол-во рейсов в пути '.$enrouteCount);

        while($row = $SQL->fetch()) {
            if($row['enroute'] == 1 && $row['enrouteFail'] < 6 && (date('U') - $row['enrouteUpdate']) > 1200 ) {

                $FAurl = 'https://flightaware.com/live/flight/'.$row['ident'];
                $data = $this->curlRequest($FAurl);

                if(preg_match('/var trackpollBootstrap = (.*?)\;\<\/script\>/i', $data, $json )) {

                    $json = json_decode($json[1], true);

                    $track = false;
                    $flightPlan = NULL;
                    $dst_terminal = NULL;
                    $org_terminal = NULL;

                    $elapsed = 0;
                    $remaining = 0;
                    $total = 0;

                    foreach($json['flights'] as $JsonData) {
                        if(isset($JsonData['track'])) $track = $JsonData['track'];
                        if(isset($JsonData['flightPlan'])) $flightPlan = json_encode($JsonData['flightPlan']);
                        if(isset($JsonData['destination'])) {
                            if($JsonData['destination']['gate'] != NULL) $gate = $JsonData['destination']['gate'];
                            if($JsonData['destination']['terminal'] != NULL) $terminal = $JsonData['destination']['terminal'];

                            if(isset($gate) && isset($terminal)) $dst_terminal = $terminal.' / '.$gate;
                            else if(isset($gate)) $dst_terminal = $gate;
                            else if(isset($terminal)) $dst_terminal = $terminal;
                        }

                        if(isset($JsonData['distance'])) {
                            $elapsed    = $JsonData['distance']['elapsed'];
                            $remaining  = $JsonData['distance']['remaining'];
                            $total      = $elapsed + $remaining;
                        }

                        $tracksCount = count($JsonData['track']) - 1;
                        $trackData = isset($JsonData['track'][$tracksCount]) ? $JsonData['track'][$tracksCount] : false;

                        if($total > 0) {
                            $this->toLog('Рейс ' . $row['ident'] . ' Длина: ' . $total . ' Пролетел: ' . $elapsed . ' Осталось: ' . $remaining);

                            if($remaining < $elapsed && $remaining > 150) {
                                $text = '🛫 Flight *' . $row['airlineIata'] . $row['flightNumber'] . '* covered *' . round($elapsed * 1.85200, 0) . '* km, left *' . round($remaining * 1.85200, 0) . ' km.* until the end of the route.';
                                $text.= PHP_EOL.PHP_EOL.'*About flight:*'.PHP_EOL;

                                if(isset($trackData['alt'])) {
                                    $altitudeMeters = round(($trackData['alt'] * 100) * 0.3048, 0);

                                    $text.= 'Flight altitude: *'.$altitudeMeters.'* m. / *'.round($altitudeMeters * 3.28084, 0).'* feet (FL'.$trackData['alt'].')'.PHP_EOL;

                                    if(isset($trackData['gs']) && $trackData['gs'] > 0) {
                                        $text.= 'Flight speed: *'.round($trackData['gs'] * 1.85).'* kph / '.round($trackData['gs'] * 1.15).' mph ('.$trackData['gs'].' knots)'.PHP_EOL;
                                    }
                                }

                                Request::sendMessage(['chat_id' => $row['telegramChatId'], 'parse_mode' => 'MARKDOWN', 'text' => $text]);
                                //$result = Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $text]);

                                if ($result->isOk()) {
                                    $SQL = $this->DBH->prepare("UPDATE `eventsQuery` SET `enroute` = 5 WHERE `fa_ident` = :faIdent");
                                    $SQL->execute(['faIdent' => $row['fa_ident']]);

                                    $this->sendMAP($row['faIdent'], $row['telegramChatId']);
                                }
                            } else {
                                $this->DBH->query("UPDATE `eventsQuery` SET `enrouteUpdate` = UNIX_TIMESTAMP() WHERE `fa_ident` = ".$this->DBH->quote($row['fa_ident']));
                            }
                        }
                    }
                } else {
                    $this->DBH->query("UPDATE `eventsQuery` SET `enrouteFail` = `enrouteFail` + 1 WHERE `fa_ident` = ".$this->DBH->quote($row['fa_ident']));
                }
            } else {
                $this->toLog('Ждем таймер');
            }
        }
    }

    private function curlRequest($url) {
        $proxy = $this->config['proxy_list'][array_rand($this->config['proxy_list'], 1)];
        $this->toLog('[Proxy] Use proxy: '.$proxy['address']);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, $proxy['useragent']);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy['user'].':'.$proxy['pass']);
        curl_setopt($curl, CURLOPT_PROXY, $proxy['address']);
        //curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            "ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3"
        ));

        $data = curl_exec($curl);

        if (!curl_errno($curl)) {
            $info = curl_getinfo($curl);
            $this->toLog('Прошло '. $info['total_time']. ' секунд во время запроса к '. $info['url']);
            return $data;

        } else {
            $this->toLog("CURL error. Bad bad");
            return false;
        }

        curl_close($curl);
    }
}