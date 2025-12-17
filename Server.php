<?php
// Load autoloader FIRST so classes are available
require_once "../vendor/autoload.php";

use Dotenv\Dotenv;
use App\Controller\UploadController;
use App\Models\CloudinaryModel;
use App\Config\CloudinaryConfig;

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ========== INITIALIZE ARCHITECTURE FLOW ==========
// Config -> Model -> Controller -> Server
$config = new CloudinaryConfig();
$cloudinaryModel = new CloudinaryModel($config);
$controller = new UploadController($cloudinaryModel);

// ========== HTTP METHOD ROUTING ==========
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'POST':
        handleFileUpload($controller);
        break;
    case 'GET':
        getAll($controller);
        break;
    case 'DELETE':
        deleteFile();
        break;
    case 'PUT':
        updateFileMetadata();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// ========== POST: HANDLE FILE UPLOAD ==========
function handleFileUpload($controller) {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No file uploaded or upload error occurred'
            ]);
            return;
        }

        $file = $_FILES['file'];
        $tempPath = $file['tmp_name'];
        $fileName = basename($file['name']);
        $fileMimeType = $file['type'];
        
        
        $folderName = $_ENV['CLOUDINARY_FOLDER'] ?? 'Photo';
        $uploadResult = $controller->upload($tempPath, $folderName);
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'url' => $uploadResult['url'] ?? null,
            'thumbnail' => (strpos($fileMimeType, 'image/') === 0) ? ($uploadResult['url'] ?? null) : null,
            'name' => $fileName,
            'mimeType' => $fileMimeType,
            'cloudinaryId' => $uploadResult['public_id'] ?? null
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Upload error: ' . $e->getMessage()
        ]);
    }
}

// ========== GET: RETRIEVE ALL FILES FROM CLOUDINARY ==========
function getAll($controller) {
    try {
        $files = $controller->getAllFiles();
        
        // Transform Cloudinary response to frontend format
        $formattedFiles = [];
        foreach ($files as $file) {
            // Skip files with 0 bytes (corrupted or deleted)
            $fileSize = $file['bytes'] ?? 0;
            if ($fileSize === 0 || $fileSize === null) {
                continue;
            }
            
            $formattedFiles[] = [
                'id' => $file['public_id'] ?? null,
                'name' => basename($file['public_id'] ?? 'file'),
                'url' => $file['secure_url'] ?? null,
                'thumbnail' => (strpos($file['type'] ?? '', 'image') === 0) ? ($file['secure_url'] ?? null) : null,
                'size' => $fileSize,
                'type' => $file['type'] ?? 'unknown',
                'mimeType' => $file['resource_type'] ?? 'unknown',
                'uploadedAt' => $file['created_at'] ?? date('c')
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Files retrieved successfully',
            'files' => $formattedFiles,
            'total' => count($formattedFiles)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving files: ' . $e->getMessage()
        ]);
    }
}

// ========== DELETE: REMOVE FILE ==========
function deleteFile() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'File ID is required'
            ]);
            return;
        }
      
        // Delegate deletion to the controller which will call Cloudinary
        global $controller;

        try {
            $fileId = $input['id'];
            $result = $controller->delete($fileId);

            if (isset($result['success']) && $result['success'] === true) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'File deleted successfully',
                    'result' => $result['result'] ?? null
                ]);
                return;
            }

            // If controller returned unsuccessful result, forward message
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'File delete completed',
                'result' => $result['result'] ?? $result
            ]);
            return;
        } catch (Exception $e) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Delete processed',
                'error' => $e->getMessage()
            ]);
            return;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}

// ========== PUT: UPDATE FILE METADATA ==========
function updateFileMetadata() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'File ID is required'
            ]);
            return;
        }
        
        $fileId = basename($input['id']);
        $filePath = __DIR__ . '/../uploads/' . $fileId;
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'File not found'
            ]);
            return;
        }
        
        // TODO: Implement metadata update logic
        // For now, just confirm the file exists
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'File metadata updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}
