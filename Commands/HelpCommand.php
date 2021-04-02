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
class HelpCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'help';
    /**
     * @var string
     */
    protected $description = 'Show bot commands help';
    /**
     * @var string
     */
    protected $usage = '/help or /help <command>';
    /**
     * @var string
     */
    protected $version = '1.3.0';
    /**
     * @inheritdoc
     */
    public function execute()
    {
        $message = $this->getMessage() ?: $this->getChannelPost();

        $chat_id = $message->getChat()->getId();
        
        $text = 'Hello! I\'m a flight tracking bot.'.PHP_EOL.PHP_EOL;
        $text.= 'I can track flight status and notify if flight status changes'.PHP_EOL.PHP_EOL;
        $text.= 'How to subscribe:'.PHP_EOL;
        $text.= ' - Subscribe to flight status for the current day /flight *Flight Number* (Example /flight AA123)'.PHP_EOL;
        $text.= ' - Subscribe to flight status on other days /flight *Flight Number Departure date* (Example /flight AA123 15.03.2018)'.PHP_EOL.PHP_EOL;
        $text.= '*It is very important that you enter the flight number and date correctly.*'.PHP_EOL.PHP_EOL;
        $text.= 'Commands:'.PHP_EOL;
        $text.= '*/help* - Show help'.PHP_EOL;
        $text.= '*/metar Airport code (IATA or ICAO)* - Return the Weather Conditions (METAR). Example: /metar FRA'.PHP_EOL;
        $text.= '*/delay Airport code (IATA or ICAO)* - Return airport delays information. Example /delay FRA'.PHP_EOL.PHP_EOL;
        $text.= '*/map* - Show enroute flights on map'.PHP_EOL;
        $text.= '*/subscriptions* - Show your subscriptions'.PHP_EOL.PHP_EOL;
        $text.= '*Service works in test mode, there may be problems with finding flights*.'.PHP_EOL.PHP_EOL;
        
        $data = [
            'chat_id' => $chat_id,
            'parse_mode' => 'MARKDOWN',
            'text'    => $text,
        ];
        return Request::sendMessage($data);
    }
}