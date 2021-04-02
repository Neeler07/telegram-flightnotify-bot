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
/**
 * Channel post command
 */
class ChannelpostCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'channelpost';
    /**
     * @var string
     */
    protected $description = 'Handle channel post';
    /**
     * @var string
     */
    protected $version = '1.0.0';
    /**
     * Execute command
     *
     * @return mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */

    /* Debug file */
    private $debugFile = __DIR__.'/../cmd_flight.log';

    public function execute()
    {
        $this->debug('====> Start Callback Query from GROUP <====');
        $cmd = $this->getChannelPost()->getCommand();

        if($cmd == 'test' || $cmd == 'flight' || $cmd == 'start' || $cmd == 'help') $this->getTelegram()->executeCommand($cmd);

        return parent::execute();
    }

    private function debug($log) {   
        $log = '['.date('d.m.Y H:i:s').'] - [Callback] - '.$log.PHP_EOL;

        file_put_contents($this->debugFile, $log, FILE_APPEND);
    }

}