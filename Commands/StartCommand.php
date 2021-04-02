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
use Longman\TelegramBot\Entities\InlineKeyboard;
/**
 * Start command
 *
 * Gets executed when a user first starts using the bot.
 */
class StartCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'start';
    /**
     * @var string
     */
    protected $description = 'Start command';
    /**
     * @var string
     */
    protected $usage = '/start';
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
    public function execute()
    {
        $message = $this->getMessage() ?: $this->getChannelPost();
        $chat_id = $message->getChat()->getId();
        
        $inline_keyboard = new InlineKeyboard([
        	['text' => 'Help', 'switch_inline_query' => '/help']
        ]);

        
        $text = 'Hello! I\'m a flight tracking bot.'.PHP_EOL.PHP_EOL;
        $text.= 'I can track flight status and notify if flight status changes'.PHP_EOL.PHP_EOL;
        $text.= 'It\'s very easy to control me:'.PHP_EOL;
        $text.= ' - Subscribe to flight status for the current day /flight Flight Number (IATA format) (Examle: /flight UA123)'.PHP_EOL;
        $text.= ' - Subscribe to flight status on the day you are interested /flight *Flight Number (IATA format)* *Departure date* (Example: /flight UA123 15.03.2018)'.PHP_EOL.PHP_EOL;
        $text.= '*It is very important that you enter the flight number and date correctly.*'.PHP_EOL.PHP_EOL;
        $text.= '_Does the bot support channels_?'.PHP_EOL;
        $text.= 'Yes, you can add bot in your channel.'.PHP_EOL.PHP_EOL;
        $text.= '_How can I see my subscriptions?_'.PHP_EOL;
        $text.= 'You can view your subscriptions by command /subscriptions'.PHP_EOL.PHP_EOL;
        $text.= 'Read about how I work - the team /help.'.PHP_EOL.PHP_EOL;
        $text.= '_Other features:_'.PHP_EOL;
        $text.= '/metar Airport Code - return the Weather Conditions (METAR)'.PHP_EOL;
        $text.= '/delay Airport Code - return airport delays information'.PHP_EOL.PHP_EOL;

        $inline_keyboard = new InlineKeyboard([
               ['text' => '💲 Help Bot', 'url' => 'https://ko-fi.com/flightbot']
        ]);
        
        $data = [
            'chat_id' => $chat_id,
            'parse_mode' => 'MARKDOWN',
            'reply_markup' => $inline_keyboard,
            'text'    => $text,
        ];
        return Request::sendMessage($data);
    }
}