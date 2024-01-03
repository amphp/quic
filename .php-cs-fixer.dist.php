<?php

$config = new Amp\CodeStyle\Config;

$config->getFinder()
    ->in(__DIR__ . '/examples')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/test')
    ->notName('quiche.php');

$config->setCacheFile(__DIR__ . '/.php_cs.cache');

return $config;
