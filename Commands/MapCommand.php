<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Longman\TelegramBot\Commands\UserCommands;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
/**
 * User "/help" command
 *
 * Command that lists all available commands and displays them in User and Admin sections.
 */
class MapCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'map';
    /**
     * @var string
     */
    protected $description = 'Show online map';
    /**
     * @var string
     */
    protected $usage = '/map or /map <command>';
    /**
     * @var string
     */
    protected $version = '1.3.0';
    /**
     * @inheritdoc
     */
    public function execute()
    {
        global $DBH;
        global $Soap;

        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();


        $SQL = $DBH->prepare("SELECT * FROM `eventsQuery` WHERE `telegramChatId` = :telegramChatId AND `enroute` > 0 AND `enroute` < 7");
        $SQL->execute(['telegramChatId' => $chat_id]);

        if($SQL->rowCount() == 0) return;

        /* Image */
        $tmpfname = tempnam("/tmp", 'IMG').'.gif';

        while($row = $SQL->fetch()) {

            $params =   [
                            'faFlightID' => $row['fa_ident'],
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

            if(isset($Data->MapFlightExResult)) {
                file_put_contents($tmpfname, base64_decode($Data->MapFlightExResult));
                if(file_exists($tmpfname)) {
                    $result = Request::sendPhoto(['chat_id' => $chat_id, 'photo' => Request::encodeFile($tmpfname)]);
                
                    unlink($tmpfname); 
                }
            }            
        }
    }
}