<?php
namespace App\Models;

use App\Config\CloudinaryConfig;
use Exception;
class CloudinaryModel {
    private $cloudinary;
    
    public function __construct(CloudinaryConfig $config) {
        $this->cloudinary = $config->getCloudinary();
    }
    public function uploadFile($tmpFile, $folder = 'uploads') {
        try {
            return $this->cloudinary->uploadApi()->upload($tmpFile, [
                'folder' => $folder
            ]);
        } catch (Exception $e) {
            throw new Exception('Cloudinary upload failed: ' . $e->getMessage());
        }
    }

    // get All files (not implemented)

    public function getAllFiles() {
        try {
            // Fetch all resource types (image, video, raw, etc.)
            $resources = $this->cloudinary->adminApi()->assets([
                'max_results' => 100,
                'resource_type' => 'image'
            ]);
            
            $allAssets = $resources['resources'] ?? [];
            
            // Try to fetch other resource types as well
            $resourceTypes = ['video', 'raw'];
            foreach ($resourceTypes as $type) {
                try {
                    $typeResources = $this->cloudinary->adminApi()->assets([
                        'max_results' => 100,
                        'resource_type' => $type
                    ]);
                    $allAssets = array_merge($allAssets, $typeResources['resources'] ?? []);
                } catch (Exception $e) {
                    // Continue if one type fails
                    continue;
                }
            }
            
            // Filter out deleted files (state = 'deleted')
            $activeAssets = array_filter($allAssets, function($asset) {
                // Keep only assets that are not marked as deleted
                return !isset($asset['state']) || $asset['state'] !== 'deleted';
            });
            
            return array_values($activeAssets); // Re-index array
        } catch (Exception $e) {
            throw new Exception('Cloudinary fetch failed: ' . $e->getMessage());
        }
    }

    // Delete a file by its Cloudinary public ID
    public function deleteFile($publicId) {
        // Try destroying the resource using likely resource types.
        $typesToTry = ['image', 'video', 'raw'];
        $lastResult = null;
        $lastException = null;

        foreach ($typesToTry as $type) {
            try {
                $result = $this->cloudinary->uploadApi()->destroy($publicId, [
                    'resource_type' => $type,
                    'invalidate' => true
                ]);

                $lastResult = $result;

                // If Cloudinary returns result => 'ok', deletion succeeded
                if (is_array($result) && isset($result['result']) && $result['result'] === 'ok') {
                    return $result;
                }

                // If result says 'not_found', continue trying other types
                if (is_array($result) && isset($result['result']) && $result['result'] === 'not_found') {
                    continue;
                }

                // For any other result, keep trying
                continue;
            } catch (Exception $e) {
                // Save exception and try next resource type
                $lastException = $e;
                continue;
            }
        }

        // If we tried all types and got 'not_found' on the last one, consider it success
        // (file might not exist, but the goal is achieved)
        if (is_array($lastResult) && isset($lastResult['result']) && $lastResult['result'] === 'not_found') {
            return ['result' => 'not_found', 'message' => 'File not found in Cloudinary'];
        }

        // If we have a result, return it
        if ($lastResult) {
            return $lastResult;
        }

        // If we get here, all attempts failed
        if ($lastException) {
            throw new Exception('Cloudinary delete failed: ' . $lastException->getMessage());
        }

        throw new Exception('Cloudinary delete failed: unknown error');
    }
}
