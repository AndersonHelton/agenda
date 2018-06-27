<?php

return [
    'database' => [
        'driver' => 'sqlite',
        'database' => str_replace('\\', '/', __DIR__) . '/database/database.sqlite',
    ],
];
