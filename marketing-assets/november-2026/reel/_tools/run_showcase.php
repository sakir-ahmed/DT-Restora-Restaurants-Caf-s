<?php
// Boots the Laravel app (honours DB_DATABASE from the environment) and runs MarketingShowcaseSeeder.
$web = realpath(__DIR__.'/../../../../web-app');
require $web.'/vendor/autoload.php';
$app = require $web.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/MarketingShowcaseSeeder.php';
