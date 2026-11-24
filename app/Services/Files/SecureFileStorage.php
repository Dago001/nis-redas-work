<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureFileStorage
{
    public const ALLOWED_PREFIXES = [
        'supporting-documents/',
        'archive/',
        'reports/',
    ];

    public const DEFAULT_ALLOWED_MAP = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    private const INLINE_SAFE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Validate, store with a randomized UUID filename on the private local disk,
     * hash, and scan an uploaded file.
     *
     * @param  array<string, list<string>>|null  $allowedMap
     * @return array{path:string,original_name:string,mime_type:string,size_bytes:int,sha256:string,scan_status:string}
     */
    public static function store(UploadedFile $file, string $directory, ?array $allowedMap = null, ?int $maxBytes = null): array
    {
        $map = $allowedMap ?? self::DEFAULT_ALLOWED_MAP;
        $limit = $maxBytes ?? ((int) config('files.max_upload_kb', 20480) * 1024);

        if (! $file->isValid()) {
            throw ValidationException::withMessages(['documents' => ['Uploaded file is invalid or corrupted.']]);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || ! array_key_exists($ext, $map)) {
            throw ValidationException::withMessages(['documents' => ["File extension [.{$ext}] is not permitted."]]);
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $limit) {
            throw ValidationException::withMessages(['documents' => ['Uploaded file exceeds the permitted size limit.']]);
        }

        $detectedMime = strtolower((string) ($file->getMimeType() ?: ''));
        if ($detectedMime === '' || ! in_array($detectedMime, $map[$ext], true)) {
            throw ValidationException::withMessages(['documents' => ["File content MIME type [{$detectedMime}] does not match .{$ext}."]]);
        }

        $cleanDir = trim(str_replace('\\', '/', $directory), '/');
        $filename = (string) Str::uuid().'.'.$ext;
        $disk = Storage::disk('local');
        $storedPath = $disk->putFileAs($cleanDir, $file, $filename);

        if (! is_string($storedPath) || $storedPath === '') {
            throw ValidationException::withMessages(['documents' => ['Unable to persist uploaded file to private storage.']]);
        }

        $absolutePath = $disk->path($storedPath);
        $sha256 = is_file($absolutePath)
            ? (string) hash_file('sha256', $absolutePath)
            : hash('sha256', (string) $disk->get($storedPath));

        /** @var MalwareScanner $scanner */
        $scanner = app(MalwareScanner::class);
        $scanStatus = $scanner->scan($absolutePath);

        if ($scanStatus === 'infected') {
            $disk->delete($storedPath);
            throw ValidationException::withMessages(['documents' => ['Uploaded file failed malware inspection and was rejected.']]);
        }

        return [
            'path' => $storedPath,
            'original_name' => basename((string) $file->getClientOriginalName()),
            'mime_type' => $detectedMime,
            'size_bytes' => $size,
            'sha256' => $sha256,
            'scan_status' => $scanStatus,
        ];
    }

    /**
     * Validate a relative storage path against traversal, absolute paths, and allowed prefixes.
     */
    public static function safePath(?string $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            return null;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Stream a private file with X-Content-Type-Options: nosniff.
     * Only PDF and JPEG/PNG images may be served inline; all other files force attachment.
     */
    public static function streamResponse(string $path, ?string $downloadName = null, bool $forceDownload = false): StreamedResponse
    {
        $safe = self::safePath($path);
        abort_if($safe === null, 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($safe), 404);

        $mime = strtolower((string) ($disk->mimeType($safe) ?: 'application/octet-stream'));
        $name = $downloadName ? basename($downloadName) : basename($safe);
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($forceDownload || ! in_array($mime, self::INLINE_SAFE_MIMES, true)) {
            return $disk->download($safe, $name, $headers);
        }

        return $disk->response($safe, $name, $headers);
    }
}
