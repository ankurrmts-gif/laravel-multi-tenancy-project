<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ModuleFileStructureService
{
    /**
     * Create directory structure for a module
     * 
     * Creates:
     * - storage/app/attachments/{module_slug}
     * - storage/app/attachments/{module_slug}/photos
     * - public/attachments/{module_slug}
     * - public/attachments/{module_slug}/photos
     */
    public function createModuleDirectories($moduleSlug)
    {
        try {
            // Create storage directories
            $attachmentsDirStorage = storage_path("app/attachments/{$moduleSlug}");
            $photoDirStorage = storage_path("app/attachments/{$moduleSlug}/photos");

            if (!File::exists($attachmentsDirStorage)) {
                File::makeDirectory($attachmentsDirStorage, 0755, true);
            }

            if (!File::exists($photoDirStorage)) {
                File::makeDirectory($photoDirStorage, 0755, true);
            }

            // Create public directories
            $attachmentsDirPublic = public_path("attachments/{$moduleSlug}");
            $photoDirPublic = public_path("attachments/{$moduleSlug}/photos");

            if (!File::exists($attachmentsDirPublic)) {
                File::makeDirectory($attachmentsDirPublic, 0755, true);
            }

            if (!File::exists($photoDirPublic)) {
                File::makeDirectory($photoDirPublic, 0755, true);
            }

            return [
                'success' => true,
                'message' => "Directories created for module: {$moduleSlug}",
                'paths' => [
                    'storage_attachments' => $attachmentsDirStorage,
                    'storage_photos' => $photoDirStorage,
                    'public_attachments' => $attachmentsDirPublic,
                    'public_photos' => $photoDirPublic
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating directories: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create .gitkeep files in directories to preserve them in git
     */
    public function createGitkeepFiles($moduleSlug)
    {
        try {
            $directories = [
                storage_path("app/attachments/{$moduleSlug}"),
                storage_path("app/attachments/{$moduleSlug}/photos"),
                public_path("attachments/{$moduleSlug}"),
                public_path("attachments/{$moduleSlug}/photos")
            ];

            foreach ($directories as $dir) {
                if (File::exists($dir)) {
                    $gitkeepPath = $dir . '/.gitkeep';
                    if (!File::exists($gitkeepPath)) {
                        File::put($gitkeepPath, '');
                    }
                }
            }

            return [
                'success' => true,
                'message' => '.gitkeep files created'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating .gitkeep files: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete module directory structure
     * 
     * Recursively removes all directories and files for a module
     */
    public function deleteModuleDirectories($moduleSlug)
    {
        try {
            $storagePath = storage_path("app/attachments/{$moduleSlug}");
            $publicPath = public_path("attachments/{$moduleSlug}");

            if (File::exists($storagePath)) {
                File::deleteDirectory($storagePath);
            }

            if (File::exists($publicPath)) {
                File::deleteDirectory($publicPath);
            }

            return [
                'success' => true,
                'message' => "Directories deleted for module: {$moduleSlug}"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting directories: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get the full directory structure for a module
     */
    public function getModuleDirectoryStructure($moduleSlug)
    {
        return [
            'storage' => [
                'attachments' => storage_path("app/attachments/{$moduleSlug}"),
                'photos' => storage_path("app/attachments/{$moduleSlug}/photos")
            ],
            'public' => [
                'attachments' => public_path("attachments/{$moduleSlug}"),
                'photos' => public_path("attachments/{$moduleSlug}/photos")
            ]
        ];
    }

    /**
     * Get the total size of all files in a module's directories
     */
    public function getModuleStorageSize($moduleSlug)
    {
        try {
            $storagePath = storage_path("app/attachments/{$moduleSlug}");
            $publicPath = public_path("attachments/{$moduleSlug}");

            $totalSize = 0;

            if (File::exists($storagePath)) {
                $totalSize += $this->getDirectorySize($storagePath);
            }

            if (File::exists($publicPath)) {
                $totalSize += $this->getDirectorySize($publicPath);
            }

            return [
                'success' => true,
                'size_bytes' => $totalSize,
                'size_mb' => round($totalSize / (1024 * 1024), 2),
                'size_gb' => round($totalSize / (1024 * 1024 * 1024), 2)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error calculating size: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Helper method to recursively calculate directory size
     */
    private function getDirectorySize($path)
    {
        $size = 0;
        $files = File::allFiles($path);

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Archive module files into a zip backup
     */
    public function archiveModuleFiles($moduleSlug)
    {
        try {
            $storagePath = storage_path("app/attachments/{$moduleSlug}");
            $publicPath = public_path("attachments/{$moduleSlug}");
            $backupPath = storage_path("backups");

            // Create backups directory if it doesn't exist
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            $zipPath = $backupPath . "/{$moduleSlug}_" . date('Y-m-d_His') . ".zip";

            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE);

            // Add storage files
            if (File::exists($storagePath)) {
                $files = File::allFiles($storagePath);
                foreach ($files as $file) {
                    $relativePath = str_replace($storagePath, '', $file->getPathname());
                    $zip->addFile($file->getPathname(), $relativePath);
                }
            }

            // Add public files
            if (File::exists($publicPath)) {
                $files = File::allFiles($publicPath);
                foreach ($files as $file) {
                    $relativePath = str_replace($publicPath, '', $file->getPathname());
                    $zip->addFile($file->getPathname(), $relativePath);
                }
            }

            $zip->close();

            return [
                'success' => true,
                'message' => "Archive created: {$zipPath}",
                'zip_path' => $zipPath,
                'file_size' => filesize($zipPath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating archive: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if module directories exist
     */
    public function moduleDirectoriesExist($moduleSlug)
    {
        $storagePath = storage_path("app/attachments/{$moduleSlug}");
        $publicPath = public_path("attachments/{$moduleSlug}");

        return [
            'storage_exists' => File::exists($storagePath),
            'public_exists' => File::exists($publicPath),
            'all_exist' => File::exists($storagePath) && File::exists($publicPath)
        ];
    }
}
