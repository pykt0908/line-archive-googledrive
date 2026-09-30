<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleDriveService
{
    protected bool $enabled;
    protected ?string $serviceAccountJson;
    protected ?string $sharedDriveId;
    protected ?string $rootFolderId;
    protected ?GoogleDrive $driveService = null;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->enabled = config('services.google_drive.enabled', true);
        $this->serviceAccountJson = config('services.google_drive.service_account_json');
        $this->sharedDriveId = config('services.google_drive.shared_drive_id');
        $this->rootFolderId = config('services.google_drive.root_folder_id');

        $this->initializeClient();
    }

    protected function initializeClient(): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!empty($this->serviceAccountJson)) {
            try {
                $client = new GoogleClient();
                $client->addScope(GoogleDrive::DRIVE);

                if (file_exists($this->serviceAccountJson)) {
                    $client->setAuthConfig($this->serviceAccountJson);
                    $this->driveService = new GoogleDrive($client);
                    $this->isConfigured = true;
                } elseif (json_validate($this->serviceAccountJson)) {
                    $authConfig = json_decode($this->serviceAccountJson, true);
                    $client->setAuthConfig($authConfig);
                    $this->driveService = new GoogleDrive($client);
                    $this->isConfigured = true;
                }
            } catch (\Exception $e) {
                Log::error('[Google Drive] Client initialization failed: ' . $e->getMessage());
                $this->isConfigured = false;
            }
        }
    }

    /**
     * Upload file content to Google Drive (or local simulation fallback).
     *
     * @return array [
     *    'file_id' => string,
     *    'web_view_link' => string,
     *    'stored_filename' => string,
     *    'is_simulation' => bool
     * ]
     */
    public function uploadFile(
        string $fileContent,
        string $originalFilename,
        string $mimeType,
        string $groupName = 'General',
        ?string $customFolderId = null
    ): array {
        // Sanitize filename to avoid path traversal and invalid characters (spec Section 27)
        $cleanOriginalName = basename($originalFilename);
        $cleanOriginalName = preg_replace('/[^\p{L}\p{N}_\-\.\s\(\)]/u', '_', $cleanOriginalName);
        $timestamp = now()->format('Ymd_His');
        $randomPart = Str::random(6);
        $storedFilename = "{$timestamp}_{$randomPart}_{$cleanOriginalName}";

        $year = now()->format('Y');
        $month = now()->format('m');
        $cleanGroupName = preg_replace('/[^\p{L}\p{N}_\-\s]/u', '_', $groupName);

        // If real Google Drive is configured
        if ($this->isConfigured && $this->driveService) {
            try {
                $targetFolderId = $customFolderId ?? $this->getOrCreateFolderHierarchy($cleanGroupName, $year, $month);

                $fileMetadata = new DriveFile([
                    'name' => $cleanOriginalName,
                    'parents' => [$targetFolderId],
                    'description' => "Uploaded from LINE Group: {$groupName}",
                ]);

                $optParams = [
                    'data' => $fileContent,
                    'mimeType' => $mimeType,
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink, webContentLink',
                    'supportsAllDrives' => true,
                ];

                $driveFile = $this->driveService->files->create($fileMetadata, $optParams);

                return [
                    'file_id' => $driveFile->id,
                    'web_view_link' => $driveFile->webViewLink ?? "https://drive.google.com/file/d/{$driveFile->id}/view",
                    'stored_filename' => $storedFilename,
                    'is_simulation' => false,
                ];
            } catch (\Exception $e) {
                Log::error("[Google Drive] Upload failed: " . $e->getMessage() . ". Falling back to local archive.");
            }
        }

        // Local Simulation Mode (Spec: Seamless development / fallback)
        $relativePath = "drive_simulation/{$cleanGroupName}/{$year}/{$month}/{$storedFilename}";
        Storage::disk('public')->put($relativePath, $fileContent);

        $simulatedId = "sim_" . md5($storedFilename);
        $simulatedUrl = url("storage/" . $relativePath);

        return [
            'file_id' => $simulatedId,
            'web_view_link' => $simulatedUrl,
            'stored_filename' => $storedFilename,
            'relative_path' => $relativePath,
            'is_simulation' => true,
        ];
    }

    /**
     * Create or retrieve folder hierarchy: Root / Group / Year / Month
     */
    protected function getOrCreateFolderHierarchy(string $groupName, string $year, string $month): string
    {
        $parentFolderId = $this->rootFolderId ?? 'root';

        // 1. Group folder
        $groupFolderId = $this->getOrCreateFolder($groupName, $parentFolderId);

        // 2. Year folder
        $yearFolderId = $this->getOrCreateFolder($year, $groupFolderId);

        // 3. Month folder
        return $this->getOrCreateFolder($month, $yearFolderId);
    }

    protected function getOrCreateFolder(string $folderName, string $parentId): string
    {
        $query = "name = '{$folderName}' and '{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        $optParams = [
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ];

        $results = $this->driveService->files->listFiles($optParams);

        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }

        $folderMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        $folder = $this->driveService->files->create($folderMetadata, [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        return $folder->id;
    }

    /**
     * Download or retrieve file stream
     */
    public function getFileContent(string $fileId, ?string $storedFilename = null, ?string $groupName = null): ?string
    {
        if ($this->isConfigured && $this->driveService && !str_starts_with($fileId, 'sim_') && !str_starts_with($fileId, 'mock_')) {
            try {
                $response = $this->driveService->files->get($fileId, [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                return $response->getBody()->getContents();
            } catch (\Exception $e) {
                Log::error("[Google Drive] Get content failed: " . $e->getMessage());
            }
        }

        // Local simulation / fallback search
        if ($storedFilename) {
            $files = Storage::disk('public')->allFiles('drive_simulation');
            foreach ($files as $file) {
                if (basename($file) === $storedFilename) {
                    return Storage::disk('public')->get($file);
                }
            }
        }

        return null;
    }

    /**
     * Delete file from Google Drive or local storage simulation
     */
    public function deleteFile(string $fileId, ?string $storedFilename = null): bool
    {
        if ($this->isConfigured && $this->driveService && !str_starts_with($fileId, 'sim_') && !str_starts_with($fileId, 'mock_')) {
            try {
                $this->driveService->files->delete($fileId, ['supportsAllDrives' => true]);
                return true;
            } catch (\Exception $e) {
                Log::warning("[Google Drive] Delete failed: " . $e->getMessage());
            }
        }

        if ($storedFilename) {
            $files = Storage::disk('public')->allFiles('drive_simulation');
            foreach ($files as $file) {
                if (basename($file) === $storedFilename) {
                    Storage::disk('public')->delete($file);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Health status check for Admin Dashboard
     */
    public function checkStatus(): array
    {
        return [
            'configured' => $this->isConfigured,
            'driver' => $this->isConfigured ? 'Google Drive v3 API' : 'Local Drive Simulation Mode',
            'shared_drive_id' => $this->sharedDriveId ?: 'Not set (Root/My Drive)',
            'root_folder_id' => $this->rootFolderId ?: 'Default Root',
        ];
    }
}
