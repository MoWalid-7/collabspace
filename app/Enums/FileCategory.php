<?php

namespace App\Enums;

enum FileCategory: string
{
    case Image = 'image';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Archive = 'archive';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            self::Document => [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            self::Spreadsheet => [
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            self::Archive => ['application/zip', 'application/x-7z-compressed', 'application/x-rar', 'application/vnd.rar'],
            self::Other => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function classifiedMimeTypes(): array
    {
        return collect(self::cases())
            ->reject(fn (self $category): bool => $category === self::Other)
            ->flatMap(fn (self $category): array => $category->mimeTypes())
            ->unique()
            ->values()
            ->all();
    }
}
