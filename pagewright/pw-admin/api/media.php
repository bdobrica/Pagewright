<?php
declare(strict_types=1);

require_once __DIR__ . '/../load.php';

// Set JSON response header
header('Content-Type: application/json');

// Start session and validate authentication
Session::start();

if (!Session::validate()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'delete':
            $id = $input['id'] ?? '';
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing file ID']);
                exit;
            }
            
            $deleted = Storage::deleteMediaFile($id);
            
            if ($deleted) {
                Logger::info('Media file deleted', ['id' => $id]);
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'File not found']);
            }
            break;
            
        case 'list':
            $media = Storage::loadMedia();
            echo json_encode([
                'success' => true,
                'files' => $media['files'] ?? []
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    $errorMessage = Logger::sanitizeException($e, 'Media operation failed');
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
    ]);
}
