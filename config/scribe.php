<?php

use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls;

use function Knuckles\Scribe\Config\removeStrategies;

// Наследуем настройки установленной версии Scribe.
$config = require base_path('vendor/knuckleswtf/scribe/config/scribe.php');
$config['title'] = 'Referral API';
$config['description'] = 'X-Master-Id — заглушка идентификации мастера.';
$config['intro_text'] = 'Для GET используйте X-Master-Id: 1 (Маша), для первой привязки — 2 (Лена). Ping доступен без заголовка.';
$config['base_url'] = config('app.url');
$config['laravel']['add_routes'] = false;
$config['postman']['enabled'] = true;
$config['openapi']['enabled'] = true;
// Используем явные примеры без вызовов API и изменения БД.
$config['strategies']['responses'] = removeStrategies(Defaults::RESPONSES_STRATEGIES, [ResponseCalls::class]);

return $config;
