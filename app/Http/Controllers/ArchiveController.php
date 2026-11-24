<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ArchiveDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Files\SecureFileStorage;
use App\Services\ReportAccess;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    public const ALLOWED_DOC_TYPES = [
        'monthly_return',
        'quarterly_return',
        'annual_return',
        'special_report',
        'nominal_roll',
        'supporting_doc',
        'other',
    ];

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $search = trim((string) $request->query('q', ''));

        $completedReturns = ReportAccess::applyVisibility(
            Application::query()->with('user')->where('status', 'approved'),
            $user
        )
            ->latest('updated_at')
            ->limit(50)
            ->get();

        $documentsQuery = ArchiveDocument::query()->with('user:id,name');
        $this->applyDocumentVisibility($documentsQuery, $user);

        if ($search !== '') {
            $documentsQuery->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('doc_type', 'like', "%{$search}%")
                    ->orWhere('report_period', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        $archiveDocuments = $documentsQuery->latest()->paginate(20)->withQueryString();

        $archiveView = view()->exists('user.archive.index') ? 'user.archive.index' : 'user.states.archive';

        return view($archiveView, [
            'completedReturns' => $completedReturns,
            'archiveDocuments' => $archiveDocuments,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $archiveView = view()->exists('user.archive.upload') ? 'user.archive.upload' : 'user.states.archive';

        return view($archiveView, [
            'completedReturns' => collect(),
            'archiveDocuments' => collect(),
            'search' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'doc_type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_DOC_TYPES)],
            'period' => ['nullable', 'date_format:Y-m'],
            'description' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
            'data_consent' => ['required', 'accepted'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $category = SubmissionWorkflow::categoryForUser($user);
        $scopeCode = SubmissionWorkflow::scopeCodeForApprover($user) ?? SubmissionWorkflow::scopeCodeForUser($user);
        $zonalCode = $user->assigned_zonal_command_code
            ?: ($user->user_category === 'zonal_commander' ? $user->primary_location_code : null);
        $retentionDays = (int) config('files.archive_retention_days', 2555);
        $directory = 'archive/'.now()->format('Y/m');

        $storedPaths = [];
        $createdIds = [];

        try {
            DB::transaction(function () use (
                $request,
                $validated,
                $user,
                $category,
                $scopeCode,
                $zonalCode,
                $retentionDays,
                $directory,
                &$storedPaths,
                &$createdIds
            ): void {
                foreach ((array) $request->file('documents', []) as $file) {
                    $meta = SecureFileStorage::store($file, $directory);
                    $storedPaths[] = $meta['path'];

                    $doc = ArchiveDocument::create([
                        'uuid' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'title' => $validated['title'],
                        'doc_type' => $validated['doc_type'],
                        'description' => $validated['description'] ?? null,
                        'report_period' => $validated['period'] ?? null,
                        'category' => $category,
                        'scope_code' => $scopeCode,
                        'zonal_code' => $zonalCode,
                        'original_name' => $meta['original_name'],
                        'stored_path' => $meta['path'],
                        'mime_type' => $meta['mime_type'],
                        'size_bytes' => $meta['size_bytes'],
                        'sha256' => $meta['sha256'],
                        'scan_status' => $meta['scan_status'],
                        'retention_until' => now()->addDays($retentionDays)->toDateString(),
                    ]);

                    $createdIds[] = $doc->id;
                }

                AuditLogger::log(
                    user: $user,
                    action: 'archive.uploaded',
                    status: 'success',
                    entityType: 'archive_document',
                    entityId: implode(',', $createdIds),
                    details: [
                        'count' => count($createdIds),
                        'doc_type' => $validated['doc_type'],
                        'report_period' => $validated['period'] ?? null,
                        'scope_code' => $scopeCode,
                    ]
                );
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                try {
                    Storage::disk('local')->delete($path);
                } catch (\Throwable) {
                }
            }

            throw $e;
        }

        return redirect()
            ->route('user.archive')
            ->with('status', count($createdIds).' document(s) uploaded to archive successfully.');
    }

    public function download(ArchiveDocument $document): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->canAccessDocument($document, $user), 403, 'You are not authorized to access this archive document.');

        if ($document->scan_status === 'infected') {
            abort(403, 'This document is quarantined.');
        }

        $safePath = SecureFileStorage::safePath($document->stored_path);
        abort_if($safePath === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($safePath), 404);

        $absolutePath = $disk->path($safePath);
        $currentHash = is_file($absolutePath)
            ? (string) hash_file('sha256', $absolutePath)
            : hash('sha256', (string) $disk->get($safePath));

        if (! hash_equals((string) $document->sha256, $currentHash)) {
            AuditLogger::log(
                user: $user,
                action: 'archive.integrity_failed',
                status: 'failure',
                entityType: 'archive_document',
                entityId: $document->id,
                details: ['uuid' => $document->uuid]
            );

            abort(409, 'Document integrity check failed (checksum mismatch).');
        }

        AuditLogger::log(
            user: $user,
            action: 'archive.downloaded',
            status: 'success',
            entityType: 'archive_document',
            entityId: $document->id,
            details: ['uuid' => $document->uuid, 'doc_type' => $document->doc_type]
        );

        return SecureFileStorage::streamResponse($safePath, $document->original_name, forceDownload: true);
    }

    public function destroy(ArchiveDocument $document): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($document->user_id === $user->id, 403);

        $document->delete();

        AuditLogger::log(
            user: $user,
            action: 'archive.deleted',
            status: 'success',
            entityType: 'archive_document',
            entityId: $document->id,
            details: ['uuid' => $document->uuid]
        );

        return redirect()
            ->route('user.archive')
            ->with('status', 'Archive document removed.');
    }

    private function applyDocumentVisibility($query, User $user): void
    {
        if (ReportAccess::isNationalViewer($user) || ReportAccess::isPrsViewer($user)) {
            return;
        }

        $scope = ReportAccess::scopeFor($user);
        if ($scope === null) {
            $query->where('user_id', $user->id);

            return;
        }

        [$type, $value] = $scope;
        if ($type === 'national') {
            return;
        }

        $query->where(function ($q) use ($user, $type, $value): void {
            $q->where('user_id', $user->id);

            if ($type === 'zone' && is_string($value)) {
                $formations = ReportAccess::formationCodesForZone($value);
                $q->orWhere('zonal_code', $value);
                if ($formations !== []) {
                    $q->orWhereIn('scope_code', $formations);
                }
            } elseif (is_string($value)) {
                $q->orWhere('scope_code', $value);
            }
        });
    }

    private function canAccessDocument(ArchiveDocument $document, User $user): bool
    {
        if ($document->user_id === $user->id) {
            return true;
        }

        if (ReportAccess::isNationalViewer($user) || ReportAccess::isPrsViewer($user)) {
            return true;
        }

        $scope = ReportAccess::scopeFor($user);
        if ($scope === null) {
            return false;
        }

        [$type, $value] = $scope;
        if ($type === 'national') {
            return true;
        }

        if ($type === 'zone' && is_string($value)) {
            return $document->zonal_code === $value
                || in_array($document->scope_code, ReportAccess::formationCodesForZone($value), true);
        }

        return is_string($value) && $document->scope_code === $value;
    }
}
