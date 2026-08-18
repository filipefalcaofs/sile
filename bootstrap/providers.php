<?php

use App\Providers\AiConfigServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\MailConfigServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    MailConfigServiceProvider::class,
    AiConfigServiceProvider::class,
];
