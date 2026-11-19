<?php

namespace App\Services\People;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Profile photos for students, guardians, staff and governing-body members.
 *
 * Every upload is re-encoded with GD as a 600×600 JPEG cropped to the centre, which
 * strips EXIF data (such as phone GPS tags) and keeps ID cards consistent. Photos are
 * kept on the private disk and only served to signed-in users allowed to see the person.
 */
class PhotoStorage
{
    public const string DISK = 'local';

    public const int SIZE = 600;

    public function store(UploadedFile $file, int $schoolId, string $folder, ?string $previous = null): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['photo' => __('This image could not be read. Use a JPG, PNG or WebP photo.')]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($canvas, null, 86);
        $jpeg = (string) ob_get_clean();

        $path = "photos/{$schoolId}/{$folder}/".Str::uuid().'.jpg';
        Storage::disk(self::DISK)->put($path, $jpeg);
        $this->delete($previous);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function respond(string $path): BinaryFileResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return response()->file(Storage::disk(self::DISK)->path($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
