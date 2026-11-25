<?php

return [
    'shipping_fee' => (float) env('ORDER_SHIPPING_FEE', 25_000),
    'payment_ttl_hours' => (int) env('ORDER_PAYMENT_TTL_HOURS', 24),
    'qris_max_amount' => (float) env('ORDER_QRIS_MAX_AMOUNT', 10_000_000),
    'credit_card_service_fee_percent' => (float) env('ORDER_CC_SERVICE_FEE_PERCENT', 2.9),
];
