<?php

namespace Nfgarching\Files\Enums;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;

enum FileType: string implements Castable
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Video = 'video';
    case Html = 'html';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Bild',
            self::Pdf => 'PDF',
            self::Video => 'Video',
            self::Html => 'HTML',
            self::Other => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'bi bi-file-image',
            self::Pdf => 'bi bi-file-pdf',
            self::Video => 'bi bi-film',
            self::Html => 'bi bi-filetype-html',
            self::Other => 'bi bi-file',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Image => 'success',
            self::Pdf => 'danger',
            self::Video => 'primary',
            self::Html => 'warning',
            self::Other => 'secondary',
        };
    }

    public static function fromExtension(string $extension): self
    {
        $extension = strtolower($extension);

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff', 'tif', 'ico', 'heic', 'heif', 'image'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpeg', 'mpg', '3gp'];

        if (in_array($extension, $imageExtensions, strict: true)) {
            return self::Image;
        }

        if ($extension === 'pdf') {
            return self::Pdf;
        }

        if (in_array($extension, $videoExtensions, strict: true)) {
            return self::Video;
        }

        if (in_array($extension, ['html', 'htm'], strict: true)) {
            return self::Html;
        }

        return self::Other;
    }

    public static function fromMimeType(string $mimeType): self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }

        if ($mimeType === 'application/pdf') {
            return self::Pdf;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }

        if ($mimeType === 'text/html') {
            return self::Html;
        }

        return self::Other;
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes, SerializesCastableAttributes
        {
            public function get($model, string $key, mixed $value, array $attributes): FileType
            {
                return FileType::tryFrom((string) $value) ?? FileType::Other;
            }

            public function set($model, string $key, mixed $value, array $attributes): ?string
            {
                return $value instanceof FileType ? $value->value : $value;
            }

            public function serialize($model, string $key, mixed $value, array $attributes): string
            {
                return $value instanceof FileType ? $value->value : (string) $value;
            }
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ], self::cases()),
            'label',
            'value'
        );
    }
}
