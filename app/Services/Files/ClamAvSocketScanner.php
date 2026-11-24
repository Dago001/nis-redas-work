<?php

namespace App\Services\Files;

use Illuminate\Support\Facades\Log;

class ClamAvSocketScanner implements MalwareScanner
{
    public function __construct(
        private readonly string $socket
    ) {}

    public function scan(string $absolutePath): string
    {
        if (! is_file($absolutePath) || $this->socket === '') {
            return 'skipped';
        }

        $fp = @stream_socket_client($this->socket, $errno, $errstr, 5);
        if (! $fp) {
            Log::warning('ClamAV scanner unavailable', ['errno' => $errno, 'error' => $errstr]);

            return 'skipped';
        }

        try {
            fwrite($fp, "zINSTREAM\0");
            $handle = fopen($absolutePath, 'rb');
            if ($handle) {
                while (! feof($handle)) {
                    $chunk = fread($handle, 8192);
                    if ($chunk !== false && $chunk !== '') {
                        fwrite($fp, pack('N', strlen($chunk)).$chunk);
                    }
                }
                fclose($handle);
            }
            fwrite($fp, pack('N', 0));
            $reply = (string) fgets($fp, 4096);

            if (str_contains($reply, 'FOUND')) {
                return 'infected';
            }

            return str_contains($reply, 'OK') ? 'clean' : 'skipped';
        } finally {
            fclose($fp);
        }
    }
}
