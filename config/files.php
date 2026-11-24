<?php

return [
    'scanner' => env('MALWARE_SCANNER', 'none'), // none | clamav
    'clamav_socket' => env('CLAMAV_SOCKET'),
    'archive_retention_days' => max(30, (int) env('ARCHIVE_RETENTION_DAYS', 2555)),
    'max_upload_kb' => 20480,
];
