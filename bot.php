<?php
include __DIR__.'/config.php';
include __DIR__.'/botcore.class.php';

/* Загружаем BotCore */
$botCore = new BotCore($config);

/* Обрабатываем новые подписки */
$botCore->processNewSubscriptions();

/* Обрабатываем колбеки */
$botCore->processCallabacks();

/* Обрабатываем рейсы в пути */
$botCore->processEnroute();
?>
