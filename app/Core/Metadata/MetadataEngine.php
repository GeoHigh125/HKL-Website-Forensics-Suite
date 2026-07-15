<?php

declare(strict_types=1);

namespace HKL\Forensics\Core\Metadata;

use DateTimeImmutable;
use RuntimeException;

final class MetadataEngine
{
    /**
     * Leest metadata van een bestand.
     *
     * @return array<string,mixed>
     */
    public function analyze(string $filename): array
    {
        if (!is_file($filename)) {
            throw new RuntimeException(
                'Bestand bestaat niet: ' . $filename
            );
        }

        clearstatcache(true, $filename);

        $size = filesize($filename);
        $modified = filemtime($filename);
        $created = filectime($filename);

        return [

            'filename' => basename($filename),

            'path' => realpath($filename),

            'extension' => strtolower(
                pathinfo($filename, PATHINFO_EXTENSION)
            ),

            'size' => $size,

            'size_human' => $this->humanSize($size),

            'created' => $created,

            'created_iso' => $this->formatDate($created),

            'modified' => $modified,

            'modified_iso' => $this->formatDate($modified),

            'readable' => is_readable($filename),

            'writable' => is_writable($filename),

            'executable' => is_executable($filename),

            'owner' => function_exists('fileowner')
                ? @fileowner($filename)
                : null,

            'group' => function_exists('filegroup')
                ? @filegroup($filename)
                : null,

            'permissions' => substr(
                sprintf('%o', fileperms($filename)),
                -4
            ),

            'mime' => $this->mimeType($filename),
        ];
    }

    private function mimeType(string $filename): string
    {
        if (function_exists('mime_content_type')) {

            $mime = @mime_content_type($filename);

            if ($mime !== false) {
                return $mime;
            }
        }

        return 'unknown';
    }

    private function formatDate(?int $timestamp): ?string
    {
        if ($timestamp === null || $timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())
            ->setTimestamp($timestamp)
            ->format('Y-m-d H:i:s');
    }

    private function humanSize(int|false $bytes): string
    {
        if ($bytes === false) {
            return 'Onbekend';
        }

        $units = ['B','KB','MB','GB','TB'];

        $size = (float)$bytes;

        $i = 0;

        while ($size >= 1024 && $i < count($units)-1) {

            $size /= 1024;

            $i++;
        }

        return number_format($size,2,',','.')
            .' '.$units[$i];
    }
}