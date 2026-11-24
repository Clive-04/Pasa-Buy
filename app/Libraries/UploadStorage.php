<?php

namespace App\Libraries;

use App\Services\ServiceException;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Validates and stores user uploads in writable/uploads (outside the public
 * document root). Files are only reachable through controllers that check
 * who is asking (see Media::avatar and Message::attachment).
 *
 * Validation does not trust the browser: the extension must be allowed AND
 * the content type detected from the file bytes (finfo) must match it.
 * Stored names are random, so client file names never touch the filesystem.
 */
final class UploadStorage
{
    public const PROFILE_PHOTOS = 'profile_photos';
    public const MESSAGE_FILES  = 'message_attachments';
    public const RECEIPTS       = 'receipts';

    /** @var array<string, list<string>> */
    public const IMAGE_TYPES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /** @var array<string, list<string>> */
    public const DOCUMENT_TYPES = [
        'pdf'  => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    /** Receipt evidence for R5: a photo or a PDF. */
    public const RECEIPT_TYPES = self::IMAGE_TYPES + ['pdf' => ['application/pdf']];

    public const RECEIPT_MAX_BYTES = 5 * 1024 * 1024;
    public const PROFILE_MAX_BYTES = 2 * 1024 * 1024;
    public const MESSAGE_MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_IMAGE_SIDE    = 5000;

    /**
     * @param array<string, list<string>> $allowed
     *
     * @return array{extension: string, mime: string, isImage: bool}
     */
    public static function validate(UploadedFile $file, array $allowed, int $maxBytes): array
    {
        if (! $file->isValid()) {
            throw new ServiceException('Upload failed: ' . $file->getErrorString());
        }

        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw new ServiceException('The uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            throw new ServiceException('The file must not exceed ' . intdiv($maxBytes, 1024 * 1024) . ' MB.');
        }

        $extension = strtolower((string) $file->getClientExtension());
        $labels    = strtoupper(implode(', ', array_keys($allowed)));

        if (! isset($allowed[$extension])) {
            throw new ServiceException('Unsupported file type. Allowed: ' . $labels . '.');
        }

        $mime = (string) $file->getMimeType(); // finfo on the temp file

        if (! in_array($mime, $allowed[$extension], true)) {
            throw new ServiceException('The file content does not match its .' . $extension . ' extension.');
        }

        $isImage = isset(self::IMAGE_TYPES[$extension]);

        if ($isImage) {
            $info = @getimagesize($file->getTempName());

            if ($info === false || $info[0] < 1 || $info[1] < 1) {
                throw new ServiceException('The image could not be read.');
            }

            if ($info[0] > self::MAX_IMAGE_SIDE || $info[1] > self::MAX_IMAGE_SIDE) {
                throw new ServiceException('Images must be at most ' . self::MAX_IMAGE_SIDE . ' pixels on each side.');
            }
        }

        return ['extension' => $extension, 'mime' => $mime, 'isImage' => $isImage];
    }

    /** Moves a validated upload into private storage; returns the stored name. */
    public static function store(UploadedFile $file, string $directory, string $extension): string
    {
        $target = self::directory($directory);

        if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
            throw new ServiceException('Upload storage is unavailable.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;

        if (! $file->move($target, $name)) {
            throw new ServiceException('The file could not be saved.');
        }

        return $name;
    }

    /** Absolute path for a stored file, or null if missing/invalid. */
    public static function path(string $directory, ?string $name): ?string
    {
        if ($name === null || ! preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9]{1,5}$/', $name)) {
            return null;
        }

        $path = self::directory($directory) . $name;

        return is_file($path) ? $path : null;
    }

    public static function delete(string $directory, ?string $name): void
    {
        $path = self::path($directory, $name);

        if ($path !== null) {
            @unlink($path);
        }
    }

    public static function directory(string $directory): string
    {
        if (! in_array($directory, [self::PROFILE_PHOTOS, self::MESSAGE_FILES, self::RECEIPTS], true)) {
            throw new \InvalidArgumentException('Unknown upload directory.');
        }

        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR;
    }

    /** Content type for serving a stored file, derived from its extension. */
    public static function contentType(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return (self::IMAGE_TYPES + self::DOCUMENT_TYPES)[$extension][0] ?? 'application/octet-stream';
    }
}
