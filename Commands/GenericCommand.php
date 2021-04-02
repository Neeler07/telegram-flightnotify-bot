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
/**
 * Generic command
 *
 * Gets executed for generic commands, when no other appropriate one is found.
 */
class GenericCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'generic';
    /**
     * @var string
     */
    protected $description = 'Handles generic commands or is executed by default when a command is not found';
    /**
     * @var string
     */
    protected $version = '1.1.0';
    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        
        $text = 'Hello! I\'m a flight tracking bot.'.PHP_EOL.PHP_EOL;
        $text.= 'I can track flight status and notify if flight status changes'.PHP_EOL.PHP_EOL;
        $text.= 'How to subscribe:'.PHP_EOL;
        $text.= ' - Subscribe to flight status for the current day /flight *Flight Number* (Example /flight AA123)'.PHP_EOL;
        $text.= ' - Subscribe to flight status on other days /flight *Flight Number Departure date* (Example /flight AA123 15.03.2018)'.PHP_EOL.PHP_EOL;
        $text.= '*It is very important that you enter the flight number and date correctly.*'.PHP_EOL.PHP_EOL;
        $text.= 'Commands:'.PHP_EOL;
        $text.= '*/help* - Show help'.PHP_EOL;
        $text.= '*/map* - Show enroute flight on map'.PHP_EOL;
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