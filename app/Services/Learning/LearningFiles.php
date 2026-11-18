<?php

namespace App\Services\Learning;

use App\Enums\MaterialKind;
use App\Models\AssignmentFile;
use App\Models\LessonMaterial;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private storage for lesson materials and assignment files.
 *
 * Files are written under lms/{school}/… with random names on the configured disk and
 * only ever reach a browser through a short-lived signed link whose controller checks
 * access again. Local files are streamed with HTTP range support, so video and audio can
 * seek; S3-style disks hand out a temporary URL instead.
 */
class LearningFiles
{
    public function __construct(private SettingsService $settings) {}

    public function diskName(): string
    {
        return (string) config('lms.disk', 'local');
    }

    /**
     * @return array{disk: string, path: string, original_name: string, mime_type: string, size: int, kind: MaterialKind}
     *
     * @throws ValidationException when the school's storage quota would be exceeded
     */
    public function store(UploadedFile $file, int $schoolId, string $folder, string $field = 'file'): array
    {
        $this->ensureQuota($schoolId, (int) $file->getSize(), $field);

        $extension = Str::lower($file->getClientOriginalExtension());
        $path = $file->storeAs("lms/{$schoolId}/{$folder}", Str::uuid().'.'.$extension, $this->diskName());

        if ($path === false) {
            throw ValidationException::withMessages([$field => __('The file could not be saved. Try again.')]);
        }

        return [
            'disk' => $this->diskName(),
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'mime_type' => Str::limit((string) $file->getMimeType(), 150, ''),
            'size' => (int) $file->getSize(),
            'kind' => self::kindFor($extension),
        ];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if ($disk !== null && $path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }

    public static function kindFor(string $extension): MaterialKind
    {
        return MaterialKind::tryFrom((string) (config('lms.extensions')[Str::lower($extension)] ?? '')) ?? MaterialKind::Document;
    }

    /**
     * Bytes used by the school's learning files.
     */
    public function usage(int $schoolId): int
    {
        return (int) LessonMaterial::query()->withoutGlobalScopes()->where('school_id', $schoolId)->sum('size')
            + (int) AssignmentFile::query()->withoutGlobalScopes()->where('school_id', $schoolId)->sum('size');
    }

    /**
     * The quota in bytes, or null when unlimited.
     */
    public function quota(): ?int
    {
        $megabytes = (int) $this->settings->current('lms.storage_quota_mb');

        return $megabytes > 0 ? $megabytes * 1024 * 1024 : null;
    }

    public function maxUploadKilobytes(): int
    {
        return max(1, (int) $this->settings->current('lms.max_upload_mb')) * 1024;
    }

    public function ensureQuota(int $schoolId, int $incomingBytes, string $field = 'file'): void
    {
        $quota = $this->quota();

        if ($quota !== null && $this->usage($schoolId) + $incomingBytes > $quota) {
            Log::warning('Learning storage quota reached.', ['school_id' => $schoolId, 'quota' => $quota]);

            throw ValidationException::withMessages([$field => __('The school’s learning storage is full. Delete old materials or ask an administrator to raise the quota.')]);
        }
    }

    /**
     * A short-lived signed link to view ("inline") or download a lesson material.
     */
    public function materialUrl(LessonMaterial $material, string $mode = 'inline'): string
    {
        return URL::temporarySignedRoute('learning.files.material', now()->addMinutes((int) config('lms.signed_url_minutes')), [
            'material' => $material->id,
            'mode' => $mode,
        ], false);
    }

    public function assignmentFileUrl(AssignmentFile $file): string
    {
        return URL::temporarySignedRoute('learning.files.assignment', now()->addMinutes((int) config('lms.signed_url_minutes')), [
            'file' => $file->id,
        ], false);
    }

    /**
     * Serve a stored file. Inline only for kinds a browser can safely render.
     */
    public function respond(string $disk, string $path, string $name, string $mime, bool $inline): Response
    {
        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);

        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes(Str::ascii($name), '"\\').'"';

        if (! $this->isLocal($disk) && $storage->providesTemporaryUrls()) {
            return redirect()->away($storage->temporaryUrl($path, now()->addMinutes(10), [
                'ResponseContentDisposition' => $disposition,
                'ResponseContentType' => $inline ? $mime : 'application/octet-stream',
            ]));
        }

        return response()->file($storage->path($path), [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function isLocal(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }
}
