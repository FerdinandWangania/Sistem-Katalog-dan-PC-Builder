<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'snap_url' => env('MIDTRANS_SNAP_URL'),
    'snap_script_url' => env('MIDTRANS_SNAP_SCRIPT_URL'),
    'finish_url' => env('MIDTRANS_FINISH_URL'),
];
