<?php

return [
    // Legacy feature tests exercise financial posting without opening a business day.
    // Real deployments enforce opening before financial writes.
    'require_open_for_posting' => env('BUSINESS_DAY_REQUIRE_OPEN', env('APP_ENV') !== 'testing'),
];
