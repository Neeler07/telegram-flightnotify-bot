<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Longman\TelegramBot\Commands\SystemCommands;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Entities\InlineKeyboard;
/**
 * Start command
 *
 * Gets executed when a user first starts using the bot.
 */
class FlightCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'flight';
    /**
     * @var string
     */
    protected $description = 'Flight command';
    /**
     * @var string
     */
    protected $usage = '/flight';
    /**
     * @var string
     */
    protected $version = '1.1.0';
    /**
     * @var bool
     */
    protected $private_only = false;
    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    
    /* Debug file */
    private $debugFile = __DIR__.'/../log/cmd_flight.log';

    private $qID;
    private $flightText;
    private $chatID;
    private $userData;
    private $userID;
    private $queryArray;

    /* Flight Number Info */
    private $flightNumberFull;
    private $flightNumberAirlineIATA;
    private $flightNumberAirlineICAO;
    private $flightNumber;    

    /* Fligt dates */
    private $flightStartDateTime;
    private $flightStopDateTime;

    /* Airports Data */
    private $airportsList;

    /* MSG From Channels */
    private $isChannel = false;

    /* Airline Data */
    private $airlineData;

    public function execute() {
        global $DBH;
        global $Soap;

        /* Set timezone to GMT */
        date_default_timezone_set('GMT');

        /* Set query ID */
        $this->qID = uniqid();

        $this->debug('====> Start query with ID: '.$this->qID.' <====');

        $Tmessage = $this->getMessage() ?: $this->getChannelPost();

        $this->flightText   = $Tmessage->getText(true);
        $this->chatID       = $Tmessage->getChat()->getId();
        $this->userData     = $Tmessage->getFrom();

        if($this->getChannelPost()) $this->isChannel = true;

        /* Debug query */
        $this->debug('Query text: '.$this->flightText);

        /* Парсим запрос и разбиваем на части */
        $this->queryArray = $this->parseQueryText($this->flightText);

        /* Debug  parsed query */
        $this->debug('Parsed Query: *** '.implode(', ', $this->queryArray).' ***');

        /* Нет IATA кода авиакомпании */
        if(is_numeric($this->queryArray[1])) {

            $text = 'Incorrect query. Please check flight query format!'.PHP_EOL.PHP_EOL;
            $text.= '*Good query examples:* '.PHP_EOL;
            $text.= '/flight AA123'.PHP_EOL;
            $text.= '/flight AA123 20.02.2018'.PHP_EOL;
            $text.= PHP_EOL.PHP_EOL;
            $text.= 'When "/flight" is command, "AA123" flight number.'.PHP_EOL.PHP_EOL;
            $text.= 'Read about how bot work /help.'.PHP_EOL.PHP_EOL;

            /* Debug */
            $this->debug('Parse error. Bad airline IATA code: '.$this->queryArray[1]);

            $this->sendTlgMsg($text);
            $this->sendTlgMsgDebug('Query: '.$this->flightText);
            return;
        }

        /* Если в запросе нет 4 элементов, то запрос плохой */
        if(count($this->queryArray) !== 4 || empty($this->queryArray[1]) || empty($this->queryArray[2])) {
            $text = '⚠️ Incorrect query. Please check flight query format!'.PHP_EOL.PHP_EOL;
            $text.= '*Good query examples:* '.PHP_EOL;
            $text.= '/flight AA123'.PHP_EOL;
            $text.= '/flight AA123 20.02.2018';

            /* Debug */
            $this->debug('Incorrect query');

            $this->sendTlgMsg($text);
            $this->sendTlgMsgDebug('Query: '.$this->flightText);
            return;
        }

        /* Заполняем переменные номера рейса */
        $this->flightNumberFull         = $this->queryArray[1].$this->queryArray[2];
        $this->flightNumberAirlineIATA  = $this->queryArray[1];
        $this->flightNumber             = $this->queryArray[2];

        /* Fix Number 0 */
        if(preg_match('/^0(\d+)/i', $this->flightNumber, $parsedFlightNumberTMP )) {
            if(isset($parsedFlightNumberTMP[1])) $this->flightNumber = $parsedFlightNumberTMP[1];
        }

        /* Пробуем получить Timezone аэропорта вылета */
        date_default_timezone_set($this->getOriginTimezoneByFlightID());

        /* Обрабатываем дату полета если нам ее передали */
        $this->prepareFlightDateTime();

        /* Проверим, может уже данный пользователь подписался на данный рейс? */
        if($this->getUserSubscribes() == false) return;

        /* Получим список аэропортов */
        $this->getAirportsList();

        /* Получим данные по Авиакомпании */
        $this->getAirlineData();

        /* Send search text */
        $this->sendTlgMsg('🔎 One moment, i search flight *'.$this->flightNumberFull.'*');

        /* Запросим информацию у FlightAware */
        $FlightAwareAirlineFlightSchedules = $this->getFlightAwareAirlineFlightSchedules();

        /* Не нашелся рейс  */
        if(!isset($FlightAwareAirlineFlightSchedules['AirlineFlightSchedulesResult']['flights'][0])) {
            $text = '⚠️ Unfortunately we could not find flight *'.$this->flightNumberFull.'*'.PHP_EOL;
            $text.= 'Please check flight number of flight date.';

            $this->sendTlgMsg($text);
            $this->debug('Flight *'.$this->flightNumberFull.'* not found via API FA');

            return;
        }

        /* Начинаем обрабатывать полученные рейсы */
        $flightsList    = [];
        $codsharesList  = [];

        foreach($FlightAwareAirlineFlightSchedules['AirlineFlightSchedulesResult']['flights'] as $k => $flightValue) {
            $this->debug($flightValue['ident'].'----'.$this->flightNumberAirlineICAO);
            if($flightValue['ident'] == $this->flightNumberAirlineICAO) $flightsList[] = $flightValue;
            else $codsharesList[$flightValue['ident']] = $flightValue['ident'];
        }

        /* Try USE actual_ident */
        if(count($flightsList) == 0) {
            $this->debug('Try use actual_ident');
            foreach($FlightAwareAirlineFlightSchedules['AirlineFlightSchedulesResult']['flights'] as $k => $flightValue) {
                $this->debug($flightValue['actual_ident'].'----'.$this->flightNumberAirlineICAO);
                if($flightValue['actual_ident'] == $this->flightNumberAirlineICAO) $flightsList[] = $flightValue;
            }
        }

        $this->debug('Flight Segments count: '.count($flightsList).' Codshared flights: '.count($codsharesList));

        /* Number of segments */
        $segments = 1;

        /* Обрабатываем рейсы */
        $text = '✅ You subscribed to flight notifications *'.$this->flightNumberFull.'*'.PHP_EOL.PHP_EOL;
        $text.= '*Information about flight '.$this->flightNumberFull.'*:'.PHP_EOL;

        $this->debug('[1] Send message');

        foreach($flightsList as $k => $flightValue) {
            $this->debug('Starting processing segment: '.$segments.' From: '.$flightValue['origin'].' to '.$flightValue['destination']);
            if(count($flightsList) > 1) $text.= PHP_EOL.'*'.$segments.' - Flight segment:*'.PHP_EOL.PHP_EOL;

            $secondText = false;
            $enroute    = 0;

            /* Проверяем, есть fa_ident или нет */
            if(!isset($flightValue['fa_ident'])) {

                $this->debug('No *fa_ident* found on FA answer');

                $departureTime      = $this->getAirportTZbyICAO($flightValue['origin'], $flightValue['departuretime']);
                $arrivalTime        = $this->getAirportTZbyICAO($flightValue['destination'], $flightValue['arrivaltime']);

                $this->debug('Origin Airport TZ: '.$departureTime.' Destination Airport TZ: '.$arrivalTime);

                if($departureTime && $arrivalTime) {
                    $text.= 'Flight *'.$this->flightNumberFull.'* departure from '.$this->getAirportName($flightValue['origin']).' at *'.$departureTime.'* and arrives to '.$this->getAirportName($flightValue['destination']).' at *'.$arrivalTime.'*.'.PHP_EOL.PHP_EOL;
                } else {
                    $text.= 'Flight *'.$this->flightNumberFull.'* departure from '.$this->getAirportName($flightValue['origin']).' at '.date('d.m.Y', $this->flightStartDateTime).' and arrives to '.$this->getAirportName($flightValue['destination']).'.'.PHP_EOL.PHP_EOL;
                }

                $text.= $this->getPlaneInformation($flightValue);
                $text.= $this->getMealInformation($flightValue);

                /* Подготавливаем SQL запрос */

                $SQL = $DBH->prepare("INSERT INTO `eventsQuery` 
                                            SET `fa_ident` = :fa_ident, 
                                                `faIdent` = :faIdent,
                                                `ident` = :ident, 
                                                `query` = :query, 
                                                `queryDateRange` = :queryDateRange,
                                                `airlineIata` = :airlineIata, 
                                                `flightNumber` = :flightNumber, 
                                                `fullFlightNumberIATA` = :fullFlightNumberIATA,
                                                `departuretime` = :departuretime, 
                                                `arrivaltime` = :arrivaltime, 
                                                `origin` = :origin, 
                                                `destination` = :destination, 
                                                `aircrafttype` = :aircrafttype, 
                                                `meal_service` = :meal_service, 
                                                `telegramChatId` = :telegramChatId,
                                                `enroute` = :enroute,
                                                `error` = :error,
                                                `queryAdded` = UNIX_TIMESTAMP()");

                $SQL->execute([
                    'fa_ident'          => 'api_'.strtolower($flightValue['ident']).uniqid(),
                    'faIdent'           => $flightValue['faIdent'] ?? null,
                    'ident'             => $flightValue['ident'],
                    'query'             => $this->flightText,
                    'queryDateRange'    => ($this->flightStartDateTime + $this->flightStopDateTime),
                    'airlineIata'       => $this->airlineData['icao'],
                    'flightNumber'      => $this->flightNumber,
                    'fullFlightNumberIATA' => $this->flightNumberFull,
                    'departuretime'     => $flightValue['departuretime'],
                    'arrivaltime'       => $flightValue['arrivaltime'],
                    'origin'            => $flightValue['origin'],
                    'destination'       => $flightValue['destination'],
                    'aircrafttype'      => $flightValue['aircrafttype'],
                    'meal_service'      => $flightValue['meal_service'],
                    'telegramChatId'    => $this->chatID,
                    'enroute'           => 0,
                    'error'             => ''
                ]);

                $SQLerror = $SQL->errorInfo();

                if($SQLerror[0] != '00000') {
                    $this->debug('SQL query Error: '.json_encode($SQLerror));
                    $this->debug('SQL Debug: '.json_encode($SQL->debugDumpParams()));
                }
                else $this->debug('SQL Query OK');

            } else { /* fa_ident Found */
                $this->debug('fa_ident: '.$flightValue['fa_ident']);

                $fawareDataDetailed = $this->getFlightInformationByFaIdent($flightValue['fa_ident']);

                $text.= 'Flight *'.$this->flightNumberFull.'* departure from '.$this->getAirportName($flightValue['origin']).' at *'.$fawareDataDetailed['filed_departure_time']['time'].' '.$fawareDataDetailed['filed_departure_time']['date'].' '.$fawareDataDetailed['filed_departure_time']['tz'].'* and arrives to '.$this->getAirportName($flightValue['destination']).' at *'.$fawareDataDetailed['filed_arrival_time']['time'].' '.$fawareDataDetailed['filed_arrival_time']['date'].' '.$fawareDataDetailed['filed_arrival_time']['tz'].'*.'.PHP_EOL.PHP_EOL;

                $text.= $this->getRouteInformation($fawareDataDetailed);

                $text.= $this->getPlaneInformation($flightValue);
                $text.= $this->getMealInformation($flightValue);

                $this->debug('Flight Status: '.$fawareDataDetailed['status']);

                /* Может рейс отменен ? */
                if($fawareDataDetailed['status'] == 'Cancelled') {
                    $secondText = '⚠️ Flight *'.$this->flightNumberFull.'* canceled.';
                }

                /* Рейс прибыл */
                if( $fawareDataDetailed['progress_percent'] == 100 ) {

                    $secondText = 'ℹ️ Flight *'.$this->flightNumberFull.'* arrival to '.$this->getAirportName($flightValue['destination']).' at *'.$fawareDataDetailed['actual_arrival_time']['time'].' '.$fawareDataDetailed['actual_arrival_time']['date'].' '.$fawareDataDetailed['actual_arrival_time']['tz'].'*';

                    $this->debug($secondText);

                    $inline_keyboard = new InlineKeyboard([
                        ['text' => '📝 My subscriptions', 'url' => 'https://avia.world']
                    ]);

                    $this->sendTlgMsg($secondText, $inline_keyboard);

                    return;

                    /* Enroute */
                } else if( $fawareDataDetailed['progress_percent'] > 0 ) {

                    $secondText = 'Flight *'.$this->flightNumberFull.'* departure to '.$this->getAirportName($flightValue['destination']).' at *'.$fawareDataDetailed['actual_departure_time']['time'].' '.$fawareDataDetailed['actual_departure_time']['date'].' '.$fawareDataDetailed['actual_departure_time']['tz'].'*'.PHP_EOL.PHP_EOL;
                    $secondText.= '🕘️ Estimated arrival time: *'.$fawareDataDetailed['estimated_arrival_time']['time'].' '.$fawareDataDetailed['estimated_arrival_time']['date'].'* '.$fawareDataDetailed['estimated_arrival_time']['tz'].PHP_EOL.PHP_EOL;
                    $secondText.= 'Current flight status: *'.$fawareDataDetailed['status'].'*'.PHP_EOL.PHP_EOL;

                    $enroute = 1;

                }

                /* Подготавливаем SQL запрос */

                $SQL = $DBH->prepare("INSERT INTO `eventsQuery` 
                                            SET `fa_ident` = :fa_ident,
                                                `faIdent` = :faIdent, 
                                                `ident` = :ident, 
                                                `query` = :query, 
                                                `queryDateRange` = :queryDateRange,
                                                `airlineIata` = :airlineIata, 
                                                `flightNumber` = :flightNumber, 
                                                `fullFlightNumberIATA` = :fullFlightNumberIATA,
                                                `departuretime` = :departuretime, 
                                                `arrivaltime` = :arrivaltime, 
                                                `origin` = :origin, 
                                                `destination` = :destination, 
                                                `aircrafttype` = :aircrafttype, 
                                                `meal_service` = :meal_service, 
                                                `telegramChatId` = :telegramChatId,
                                                `enroute` = :enroute,
                                                `error` = :error,
                                                `queryAdded` = UNIX_TIMESTAMP()");

                $SQL->execute([
                    'fa_ident'          => 'api_'.strtolower($flightValue['ident']).uniqid(),
                    'faIdent'           => $flightValue['fa_ident'] ?? null,
                    'ident'             => $flightValue['ident'],
                    'query'             => $this->flightText,
                    'queryDateRange'    => ($this->flightStartDateTime + $this->flightStopDateTime),
                    'airlineIata'       => $this->airlineData['icao'],
                    'flightNumber'      => $this->flightNumber,
                    'fullFlightNumberIATA' => $this->flightNumberFull,
                    'departuretime'     => $flightValue['departuretime'],
                    'arrivaltime'       => $flightValue['arrivaltime'],
                    'origin'            => $flightValue['origin'],
                    'destination'       => $flightValue['destination'],
                    'aircrafttype'      => $flightValue['aircrafttype'],
                    'meal_service'      => $flightValue['meal_service'],
                    'telegramChatId'    => $this->chatID,
                    'enroute'           => $enroute,
                    'error'             => ''
                ]);

                $SQLerror = $SQL->errorInfo();

                if($SQLerror[0] != '00000') {
                    $this->debug('SQL query Error: '.json_encode($SQLerror));
                    $this->debug('SQL Debug: '.json_encode($SQL->debugDumpParams()));
                }
                else $this->debug('SQL Query OK');
            }

            $segments++;
        }


        $inline_keyboard = new InlineKeyboard([
            ['text' => '📝 My subscriptions', 'url' => 'https://avia.world/']
        ]);

        $this->sendTlgMsg($text);

        if($secondText) $this->sendTlgMsg($secondText);

        if($enroute) {
            /* Image */
            $tmpfname = tempnam("/tmp", 'IMG').'.gif';

            $this->debug('Generate MAP image. FA_ident: '.$flightValue['fa_ident']);

            $params =   [
                'faFlightID' => $flightValue['fa_ident'],
                'mapHeight' => 480,
                'mapWidth' => 640,
                'show_data_blocks' => true,
                'layer_on' => array(),
                'layer_off' => array(),
                'show_airports' => true,
                'latlon_box' => array(),
                'airports_expand_view' => true
            ];

            $Data = $Soap->MapFlightEx($params);
            $this->debug('SOAP debug: '.json_encode($Data));

            if(isset($Data->MapFlightExResult)) {

                $this->debug('Save and send flight map picture. tmp gif location: '.$tmpfname);

                file_put_contents($tmpfname, base64_decode($Data->MapFlightExResult));

                if(file_exists($tmpfname)) {

                    $this->sendTlgMsg(false,false,$tmpfname);

                    unlink($tmpfname);
                }
            }
        }
    }

    private function getRouteInformation($routeData) {

        if(!isset($routeData['filed_ete'])) return;

        $text = '_Route information:_ '.PHP_EOL;
        $text.= 'Filed flight duration: *'.$this->TrySecondsToHours($routeData['filed_ete']).'*'.PHP_EOL;

        if(isset($routeData['display_filed_altitude'])) $text.= 'Filed altitude: *'.$routeData['display_filed_altitude'].'*'.PHP_EOL;
        if(isset($routeData['filed_airspeed_kts'])) $text.= 'Filed airspeed: *'.$routeData['filed_airspeed_kts'].'* knots / *'.(round($routeData['filed_airspeed_kts'] * 1.151,0)).' mph *'.PHP_EOL;
        if(isset($routeData['distance_filed'])) $text.= 'Filed distance: *'.$routeData['distance_filed'].'* miles / *'.round($routeData['distance_filed'] * 1.609, 0). '* km'.PHP_EOL;

        $text.= PHP_EOL;

        return $text;
    }

    private function TrySecondsToHours($seconds) {
        $H = floor($seconds / 3600);
        $i = ($seconds / 60) % 60;
        $s = $seconds % 60;

        return sprintf("%02d h. %02d m.", $H, $i, $s);
    }

    private function getFlightInformationByFaIdent($fa_ident) {
        /* Запросим информацию у FlightAware */
        $queryParams = array(
            'ident' => $fa_ident,
            'howMany' => 1
        );

        $this->debug('Try query FlightAware. API method FlightInfoStatus. Query: '.implode(', ', $queryParams));

        $APIQueryResult = $this->executeCurlRequest('FlightInfoStatus', $queryParams);

        if($APIQueryResult === false) {
            $this->debug('FlightInfoStatus Answer BAD.');
            return false;
        }

        $this->debug('FlightAware API answer: '.$APIQueryResult);

        $jsonData = json_decode($APIQueryResult, true);

        if(isset($jsonData['FlightInfoStatusResult']['flights'][0])) {
            $this->debug('Found Flight Information from response FA');

            return $jsonData['FlightInfoStatusResult']['flights'][0];
        }
    }

    private function getMealInformation($mealData) {
        if( isset($mealData['meal_service']) && !empty($mealData['meal_service']) ) {
            $out = PHP_EOL.'_Meal information:_ '.PHP_EOL;

            foreach(explode('/', $mealData['meal_service']) as $k => $v) {
                $out.= '🍽️ '.trim($v).PHP_EOL;
            }

            return $out;
        }

        return;
    }

    private function getPlaneInformation($plane) {
        global $DBH;

        $SQL = $DBH->prepare("SELECT * FROM `aircraft_types` WHERE `icao` = :plane_model LIMIT 1");
        $SQL->execute(['plane_model' => $plane['aircrafttype']]);

        if($SQL->rowCount() === 0) return;

        $planeData = $SQL->fetch();

        $out = '_Plane Information_:'.PHP_EOL;
        $out.= '✈️ Flight is accomplished by the plane '.$planeData['name'].' ('.$plane['aircrafttype'].').'.PHP_EOL.PHP_EOL;

        if(isset($plane['seats_cabin_business']) || isset($plane['seats_cabin_coach']) || isset($plane['seats_cabin_first'])) {
            $out.= '_Cabin seat configuration:_'.PHP_EOL;

            if($plane['seats_cabin_first'] > 0) $out.= '💺 First class seats: *'.$plane['seats_cabin_first'].'*'.PHP_EOL;
            if($plane['seats_cabin_business'] > 0) $out.= '💺 Business class seats: *'.$plane['seats_cabin_business'].'*'.PHP_EOL;
            if($plane['seats_cabin_coach'] > 0) $out.= '💺 Coach class seats: *'.$plane['seats_cabin_coach'].'*'.PHP_EOL;
        }

        return $out;

    }

    private function getAirportName($icao) {
        if(isset($this->airportsList[$icao])) {
            return $this->airportsList[$icao]['name'].' ('.(!empty($this->airportsList[$icao]['iata']) ? $this->airportsList[$icao]['iata'] : $icao).')';
        }

        return $icao;
    }

    private function getFlightAwareAirlineFlightSchedules() {
        $queryParams = array(
            'flightno'      => $this->flightNumber,
            'airline'       => str_replace(['N4','ZF'], ['NWS', 'KTK'], $this->airlineData['icao']),
            'start_date'    => $this->flightStartDateTime,
            'end_date'      => $this->flightStopDateTime
        );

        $this->debug('Try query FlightAware. API method AirlineFlightSchedules. Query: '.implode(', ', $queryParams));

        $APIQueryResult = $this->executeCurlRequest('AirlineFlightSchedules', $queryParams);

        if($APIQueryResult === false) {
            $this->sendTlgMsg('Bot is having problems. Please try again later.');
            $this->debug('FlightAware Answer BAD.');
            return false;
        }

        $this->debug('FlightAware API answer: '.$APIQueryResult);

        return json_decode($APIQueryResult, true);
    }

    private function executeCurlRequest($endpoint, $queryParams) {
        global $config;

        $username   = $config['FAv3']['username'];
        $apiKey     = $config['FAv3']['apiKey'];
        $fxmlUrl    = "https://flightxml.flightaware.com/json/FlightXML3/";

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

    private function getAirlineData() {
        global $DBH;

        $SQL = $DBH->prepare("SELECT * FROM `apiWorld`.`airlines` WHERE `iata` = :iata OR `icao` = :iata LIMIT 1");
        $SQL->execute(['iata' => $this->flightNumberAirlineIATA]);


        if($SQL->rowCount() == 1) {
            $airlineData = $SQL->fetch();
            $this->airlineData = $airlineData;
            $this->flightNumberAirlineICAO = $airlineData['icao'].$this->flightNumber;
            $this->debug('Found Airline by IATA code: '.$this->flightNumberAirlineIATA.'. Airline name: '.$airlineData['name']);

            return;
        }

        $this->debug('Airline not found by IATA code: '.$this->flightNumberAirlineIATA.'.');

        return;
    }

    private function getAirportsList() {
        global $DBH;

        $SQL = $DBH->query("SELECT `name`, `iata`, `icao`, `timeZoneRegionName` FROM `airports`");

        while($row = $SQL->fetch()) {
            if(empty($row['icao'])) $airportsList[$row['iata']] = $row;
            else $airportsList[$row['icao']] = $row;
        }

        $this->airportsList = $airportsList;

        return;
    }

    private function getUserSubscribes() {
        global $DBH;
        global $Soap;

        $SQL = $DBH->prepare("SELECT * FROM `eventsQuery` WHERE `flightNumber` = :flightNumber AND `queryDateRange` = :queryDateRange AND `telegramChatId` = :telegramChatId LIMIT 1");
        $SQL->execute([
            'flightNumber'      => $this->flightNumber,
            'queryDateRange'    => ($this->flightStartDateTime + $this->flightStopDateTime),
            'telegramChatId'    => $this->chatID
        ]);

        $SQLerror = $SQL->errorInfo();

        if($SQLerror[0] != '00000') $this->debug('SQL query Error: '.json_encode($SQLerror));

        if($SQL->rowCount() > 0) {
            $userSubscribeData = $SQL->fetch();

            if(!empty($userSubscribeData['error'])) {

                $text = 'Unfortunately we could not find this flight.';
                $this->sendTlgMsg($text);
                $this->debug('User Subscribe found. Error found: *'.$userSubscribeData['error'].'*');

                return false;
            }

            /* Юзер подписан, пошлем сообщение, и проверим, может рейс летит*/

            if($userSubscribeData['enroute'] > 0 && $userSubscribeData['enroute'] < 7) {
                $text = '🛬 Flight * '.$this->flightNumberFull.'* enroute.'.PHP_EOL;
                //$text.= 'You can see flight on map by command */map*';
                $this->sendTlgMsg($text);

                /* Image */
                $tmpfname = tempnam("/tmp", 'IMG').'.gif';
                $this->debug('Generate MAP image. FA_ident: '.$userSubscribeData['fa_ident']);

                $params =   [
                    'faFlightID' => $userSubscribeData['faIdent'],
                    'mapHeight' => 480,
                    'mapWidth' => 640,
                    'show_data_blocks' => true,
                    'layer_on' => array(),
                    'layer_off' => array(),
                    'show_airports' => true,
                    'latlon_box' => array(),
                    'airports_expand_view' => true
                ];

                $Data = $Soap->MapFlightEx($params);
                $this->debug('SOAP debug: '.json_encode($Data));

                if(isset($Data->MapFlightExResult)) {
                    $this->debug('Save and send flight map picture. tmp gif location: '.$tmpfname);
                    file_put_contents($tmpfname, base64_decode($Data->MapFlightExResult));

                    if(file_exists($tmpfname)) {
                        $this->sendTlgMsg(false,false,$tmpfname);
                        unlink($tmpfname);
                    }
                }


                $this->debug('User Subscribe found. Flight ENROUTE');

                return false;
            } else {
                $text = 'You already subscribed to flight *'.$this->flightNumberFull.'* notifications.'.PHP_EOL;
                $text.= 'You can see your subscriptions by command /subscriptions';
                $this->sendTlgMsg($text);
                $this->debug('User already subscribed to flight notifications. '.$this->flightNumberFull);
            }

            return false;
            /*

            * Flight Inforation get from FA

            */

        }

        return true;
    }

    private function prepareFlightDateTime() {

        if(empty($this->queryArray[3])) {
            $startDate  = date('Y-m-d').' 00:00:00';
            $stopDate   = date('Y-m-d').' 23:59:59';

            $this->flightStartDateTime = date('U', strtotime($startDate));
            $this->flightStopDateTime = date('U', strtotime($stopDate));

            $this->debug('No Departure date set. Set start date: *'.$startDate.'*, end date: *'.$stopDate.'*');

            return;
        }

        if(strtotime($this->queryArray[3])) {
            $startDate  = date('Y-m-d', strtotime($this->queryArray[3])).' 00:00:00';
            $stopDate   = date('Y-m-d', strtotime($this->queryArray[3])).' 23:59:59';

            $this->flightStartDateTime = date('U', strtotime($startDate));
            $this->flightStopDateTime = date('U', strtotime($stopDate));

            $this->debug('Departure date is seted. Set start date: *'.$startDate.'*, end date: *'.$stopDate.'*');

            return;
        } else {

            $text = 'Invalid date format. Example correct date format *DD.MM.YYYY* (20.03.2018)'.PHP_EOL;
            $text.= '*DD* - Day'.PHP_EOL;
            $text.= '*MM* - Month'.PHP_EOL;
            $text.= '*YYYY* - Year';

            $this->debug('Invalid date format. Query Date: *'.$this->queryArray[3].'*');
            $this->sendTlgMsg($text);
            return;
        }
    }

    private function getAirportTZbyICAO($icao, $time) {

        if(isset($this->airportsList[$icao])) {

            date_default_timezone_set($this->airportsList[$icao]['timeZoneRegionName']);

            return date('H:i d.m.Y', $time);

        }

        return false;

    }

    private function getOriginTimezoneByFlightID() {
        //global $DBH;

        /* Пытаемся найти рейс в нашем БД */
        /*
        $SQL = $DBH->prepare("SELECT `origin_iata`, `origin_icao` FROM `flights` WHERE `ident` = :ident OR `identIata` = :ident LIMIT 1");
        $SQL->execute(['ident' => $this->flightNumberFull]);

        /* Нашли рейс, нашли АП вылета */
        /*
        if($SQL->rowCount() === 1) {
            $tzDataOrigin = $SQL->fetch();

            $this->debug('Found Origin Airport. IATA: '.$tzDataOrigin['origin_iata'].' ICAO: '.$tzDataOrigin['origin_icao']);

            /* Пробуем найти аэропорт вылта */
        /*
            $SQL = $DBH->prepare("SELECT `name`, `timeZoneRegionName` FROM `airports` WHERE `timeZoneRegionName` != '' AND (`icao` = :origin_icao OR `iata` = :origin_iata) LIMIT 1");
            $SQL->execute(['origin_iata' => $tzDataOrigin['origin_iata'], 'origin_icao' => $tzDataOrigin['origin_icao']]);

            if($SQL->rowCount() === 1) {
                $tzData = $SQL->fetch();

                $this->debug('Found TZ for airport *'.$tzData['name'].'* Timezone: *'.$tzData['timeZoneRegionName'].'*');

                return $tzData['timeZoneRegionName'];
            }
        }

        $this->debug('No timezone found for Origin airport');
        */
        return 'GMT';
    }

    private function sendTlgMsgDebug($message) {

        //Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => '⚙️ '.$message]);
    }

    private function sendTlgMsg($message, $inline_keyboard = false, $photo = false) {

        if($inline_keyboard) {
            Request::sendMessage(['chat_id' => $this->chatID, 'parse_mode' => 'MARKDOWN', 'text' => $message, 'reply_markup' => $inline_keyboard]);
            //Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $message, 'reply_markup' => $inline_keyboard]);
        } else if($photo) {
            $this->debug('MSG: send photo');
            Request::sendPhoto(['chat_id' => $this->chatID, 'photo' => Request::encodeFile($photo)]);
            //Request::sendPhoto(['chat_id' => 'XXXX', 'photo' => Request::encodeFile($photo)]);
        } else {
            Request::sendMessage(['chat_id' => $this->chatID, 'parse_mode' => 'MARKDOWN', 'text' => $message]);
            //Request::sendMessage(['chat_id' => 'XXXX', 'parse_mode' => 'MARKDOWN', 'text' => $message]);
        }

        return;
    }

    private function parseQueryText($query) {
        $query = trim($query);
        $query = strtoupper($query);
        $query = str_replace(['-','_'], '', $query);

        preg_match('/^([A-Z0-9]{2})\s*(\d+)\s*(.*?)$/i', $query, $parsedQuery);

        if(count($parsedQuery) != 4) {
            preg_match('/^([A-Z]{3})\s*(\d+)\s*(.*?)$/i', $query, $parsedQuery);

            return array_map('trim', $parsedQuery);
        }

        return array_map('trim', $parsedQuery);
    }

    private function debug($log) {
        $log = '['.date('d.m.Y H:i:s').'] - '.$this->qID.' '.$log.PHP_EOL;

        if($this->isChannel) $log = '[CHANNEL] - '.$log;

        file_put_contents($this->debugFile, $log, FILE_APPEND);
    }

    private function secondsToHours($seconds) {
        $H = floor($seconds / 3600);
        $i = ($seconds / 60) % 60;
        $s = $seconds % 60;

        return sprintf("%02d h. %02d m.", $H, $i, $s);
    }
}
