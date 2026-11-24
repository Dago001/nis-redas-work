<?php

return [
    'ttl' => max(60, (int) env('OTP_TTL_SECONDS', 300)),
    'max_attempts' => max(1, (int) env('OTP_MAX_ATTEMPTS', 5)),
    'max_resends' => max(0, (int) env('OTP_MAX_RESENDS', 3)),
    'resend_interval' => max(5, (int) env('OTP_RESEND_INTERVAL', 30)),
    'digits' => max(6, (int) env('OTP_DIGITS', 6)),
];
