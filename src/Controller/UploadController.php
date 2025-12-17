<?php
namespace App\Controller;
use App\Models\CloudinaryModel;
use Exception;
class UploadController {
    private $cloudinaryModel;
    public function __construct(CloudinaryModel $cloudinaryModel) {
        $this->cloudinaryModel = $cloudinaryModel;
    }


    public function upload($tempFile, $folder = 'uploads') {
        try {
            if (!file_exists($tempFile)) {
                throw new Exception('Temporary file not found');
            }
            
            $result = $this->cloudinaryModel->uploadFile($tempFile, $folder);
            
            if (!$result || !isset($result['secure_url'])) {
                throw new Exception('Invalid upload response from Cloudinary');
            }
            
            return [
                'success' => true,
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'] ?? null,
                'size' => $result['bytes'] ?? null,
                'type' => $result['type'] ?? null
            ];
        } catch (Exception $e) {
            throw new Exception('Upload error: ' . $e->getMessage());
        }
    }


    // Get all files (not implemented)
    public function getAllFiles() {
        try {
            return $this->cloudinaryModel->getAllFiles();
        } catch (Exception $e) {
            throw new Exception('Fetch error: ' . $e->getMessage());
        }
    }

    // Delete a file by public ID
    public function delete($publicId) {
        try {
            if (empty($publicId)) {
                throw new Exception('Public ID is required for delete');
            }

            $result = $this->cloudinaryModel->deleteFile($publicId);

            // Cloudinary returns 'ok' on successful deletion, 'not_found' if already deleted or doesn't exist
            // Both cases are acceptable (goal is to ensure file is gone)
            if (is_array($result) && isset($result['result'])) {
                if ($result['result'] === 'ok' || $result['result'] === 'not_found') {
                    return ['success' => true, 'result' => $result];
                }
            }

            return ['success' => false, 'result' => $result];
        } catch (Exception $e) {
            throw new Exception('Delete error: ' . $e->getMessage());
        }
    }

    
}
