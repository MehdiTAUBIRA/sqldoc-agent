<?php

return [
    'storage_path' => env('SQLINFO_STORAGE_PATH', storage_path()),
    'database_path' => env('SQLINFO_DATABASE_PATH', database_path('database.sqlite')),
];