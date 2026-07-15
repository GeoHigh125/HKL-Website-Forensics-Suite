<?php

declare(strict_types=1);

namespace HKL\Forensics\Core\Inventory;

use HKL\Forensics\Core\Classification\DirectoryClassifier;
use HKL\Forensics\Core\Classification\FileClassifier;
use HKL\Forensics\Core\Hashing\HashEngine;
use HKL\Forensics\Core\Metadata\MetadataEngine;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class FileInventory
{
    public function __construct(
        private readonly DirectoryClassifier $directoryClassifier = new DirectoryClassifier(),
        private readonly FileClassifier $fileClassifier = new FileClassifier(),
        private readonly MetadataEngine $metadataEngine = new MetadataEngine(),
        private readonly HashEngine $hashEngine = new HashEngine(),
    ) {
    }

    /**
     * Bouwt een complete inventarisatie van alle bestanden.
     *
     * @return array<string,mixed>
     */
    public function build(string $rootPath): array
    {
        $files = [];

        $statistics = [
            'total' => 0,
            'php' => 0,
            'images' => 0,
            'javascript' => 0,
            'css' => 0,
            'json' => 0,
            'xml' => 0,
            'archives' => 0,
            'unknown' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $rootPath,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $item) {

            if (!$item->isFile()) {
                continue;
            }

            $filename = $item->getPathname();

            $directory = dirname($filename);

            $directoryInfo = $this->directoryClassifier
                ->classify($rootPath, $directory);

            $fileInfo = $this->fileClassifier
                ->classify($filename);

            $metadata = $this->metadataEngine
                ->analyze($filename);

            $hashes = $this->hashEngine
                ->analyze($filename);

            $files[] = [

                'path' => $filename,

                'directory' => $directoryInfo,

                'file' => $fileInfo,

                'metadata' => $metadata,

                'hashes' => $hashes,
            ];

            $statistics['total']++;

            switch ($fileInfo['type']) {

                case FileClassifier::TYPE_PHP:
                    $statistics['php']++;
                    break;

                case FileClassifier::TYPE_IMAGE:
                    $statistics['images']++;
                    break;

                case FileClassifier::TYPE_JS:
                    $statistics['javascript']++;
                    break;

                case FileClassifier::TYPE_CSS:
                    $statistics['css']++;
                    break;

                case FileClassifier::TYPE_JSON:
                    $statistics['json']++;
                    break;

                case FileClassifier::TYPE_XML:
                    $statistics['xml']++;
                    break;

                case FileClassifier::TYPE_ARCHIVE:
                    $statistics['archives']++;
                    break;

                default:
                    $statistics['unknown']++;
                    break;
            }
        }

        return [

            'generated_at' => date(DATE_ATOM),

            'root' => $rootPath,

            'statistics' => $statistics,

            'files' => $files,
        ];
    }
}