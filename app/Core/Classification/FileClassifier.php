<?php

declare(strict_types=1);

namespace HKL\Forensics\Core\Classification;

final class FileClassifier
{
    public const TYPE_PHP = 'php';
    public const TYPE_HTML = 'html';
    public const TYPE_CSS = 'css';
    public const TYPE_JS = 'javascript';
    public const TYPE_JSON = 'json';
    public const TYPE_XML = 'xml';
    public const TYPE_SQL = 'sql';
    public const TYPE_TEXT = 'text';
    public const TYPE_MARKDOWN = 'markdown';
    public const TYPE_IMAGE = 'image';
    public const TYPE_ARCHIVE = 'archive';
    public const TYPE_BINARY = 'binary';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Classificeert een bestand op basis van extensie.
     *
     * @return array<string,mixed>
     */
    public function classify(string $filename): array
    {
        $extension = strtolower(
            pathinfo($filename, PATHINFO_EXTENSION)
        );

        return [
            'filename' => basename($filename),
            'extension' => $extension,
            'type' => $this->detectType($extension),
            'mime_group' => $this->mimeGroup($extension),
            'is_text' => $this->isTextFile($extension),
            'is_executable' => $this->isExecutable($extension),
        ];
    }

    private function detectType(string $extension): string
    {
        return match ($extension) {

            'php',
            'php5',
            'php7',
            'php8',
            'phtml',
            'phar'
                => self::TYPE_PHP,

            'html',
            'htm'
                => self::TYPE_HTML,

            'css'
                => self::TYPE_CSS,

            'js',
            'mjs'
                => self::TYPE_JS,

            'json'
                => self::TYPE_JSON,

            'xml'
                => self::TYPE_XML,

            'sql'
                => self::TYPE_SQL,

            'txt',
            'log',
            'ini',
            'conf',
            'cfg',
            'yaml',
            'yml'
                => self::TYPE_TEXT,

            'md'
                => self::TYPE_MARKDOWN,

            'png',
            'jpg',
            'jpeg',
            'gif',
            'bmp',
            'svg',
            'webp',
            'ico'
                => self::TYPE_IMAGE,

            'zip',
            'gz',
            'tar',
            'tgz',
            'rar',
            '7z'
                => self::TYPE_ARCHIVE,

            default
                => self::TYPE_UNKNOWN,
        };
    }

    private function mimeGroup(string $extension): string
    {
        return match ($this->detectType($extension)) {

            self::TYPE_PHP => 'application',

            self::TYPE_HTML => 'document',

            self::TYPE_CSS,
            self::TYPE_JS
                => 'frontend',

            self::TYPE_JSON,
            self::TYPE_XML
                => 'structured',

            self::TYPE_SQL
                => 'database',

            self::TYPE_TEXT,
            self::TYPE_MARKDOWN
                => 'text',

            self::TYPE_IMAGE
                => 'image',

            self::TYPE_ARCHIVE
                => 'archive',

            default
                => 'unknown',
        };
    }

    private function isExecutable(string $extension): bool
    {
        return in_array(
            strtolower($extension),
            [
                'php',
                'php5',
                'php7',
                'php8',
                'phtml',
                'phar',
                'cgi',
                'pl',
                'py',
                'sh',
                'bat',
                'cmd',
            ],
            true
        );
    }

    private function isTextFile(string $extension): bool
    {
        return !in_array(
            $this->detectType($extension),
            [
                self::TYPE_IMAGE,
                self::TYPE_ARCHIVE,
                self::TYPE_BINARY,
            ],
            true
        );
    }
}