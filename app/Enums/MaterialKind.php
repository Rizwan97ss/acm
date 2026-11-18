<?php

namespace App\Enums;

/**
 * How a lesson material is shown to students.
 */
enum MaterialKind: string
{
    case Pdf = 'pdf';
    case Document = 'document';
    case Presentation = 'presentation';
    case Spreadsheet = 'spreadsheet';
    case Audio = 'audio';
    case Video = 'video';
    case Image = 'image';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Document => 'Document',
            self::Presentation => 'Presentation',
            self::Spreadsheet => 'Spreadsheet',
            self::Audio => 'Audio',
            self::Video => 'Video',
            self::Image => 'Image',
            self::Link => 'Link',
        };
    }

    /**
     * Kinds the browser can show without downloading the file.
     */
    public function isPreviewable(): bool
    {
        return in_array($this, [self::Pdf, self::Audio, self::Video, self::Image], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
