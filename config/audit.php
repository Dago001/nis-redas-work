<?php

return [
    'channel' => env('AUDIT_LOG_CHANNEL', 'audit'),
    'retention_days' => max(90, (int) env('AUDIT_RETENTION_DAYS', 2555)),
    'auto_prune' => filter_var(env('AUDIT_AUTO_PRUNE', false), FILTER_VALIDATE_BOOLEAN),
];
