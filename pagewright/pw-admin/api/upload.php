<?php
declare(strict_types=1);

require_once __DIR__ . '/../load.php';

// Set JSON response header
header('Content-Type: application/json');

// Start session and validate authentication
Session::start();

if (!Session::validate()) {
    http_response_code(401);
    Logger::warning('Upload attempted without valid session', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Ensure we have a file upload
    if (empty($_FILES['file'])) {
        Logger::warning('Upload with no file', [
            'post' => $_POST,
            'files' => array_keys($_FILES)
        ]);
        throw new RuntimeException('No file uploaded');
    }
    
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];
        throw new RuntimeException($errors[$file['error']] ?? 'Unknown upload error');
    }
    
    // Validate file size (10MB max)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File too large. Maximum size is 10MB');
    }
    
    // Validate file type
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Invalid file type. Allowed: JPEG, PNG, GIF, WebP, SVG, PDF');
    }
    
    $extension = $allowedTypes[$mimeType];
    
    // Generate safe filename
    $id = bin2hex(random_bytes(8));
    $timestamp = time();
    $safeFilename = $id . '_' . $timestamp . '.' . $extension;
    
    // Define paths
    $uploadDir = dirname(__DIR__, 2) . '/pw-public/uploads/';
    $targetPath = $uploadDir . $safeFilename;
    $thumbPath = null;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Failed to save uploaded file');
    }
    
    // Generate thumbnail for images
    $isImage = strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml';
    
    if ($isImage && function_exists('imagecreatefromjpeg')) {
        $thumbFilename = 'thumb_' . $safeFilename;
        $thumbPath = $uploadDir . 'thumbs/' . $thumbFilename;
        
        try {
            createThumbnail($targetPath, $thumbPath, 300, 300);
        } catch (Exception $e) {
            Logger::warning('Failed to generate thumbnail', [
                'file' => $safeFilename,
                'error' => $e->getMessage(),
            ]);
            // Continue without thumbnail
            $thumbPath = null;
        }
    } elseif ($isImage && !function_exists('imagecreatefromjpeg')) {
        Logger::warning('GD extension not available - thumbnail not created', [
            'file' => $safeFilename,
        ]);
    }
    
    // Build file metadata
    $baseUrl = Http::baseUrl();
    $baseUrl = preg_replace('#/pw-admin$#', '', $baseUrl);
    
    $fileData = [
        'id' => $id,
        'filename' => $file['name'],
        'url' => $baseUrl . '/pw-public/uploads/' . $safeFilename,
        'thumb_url' => $thumbPath ? $baseUrl . '/pw-public/uploads/thumbs/' . $thumbFilename : null,
        'type' => $mimeType,
        'size' => $file['size'],
        'uploaded_at' => gmdate('Y-m-d H:i:s'),
    ];
    
    // Save to media metadata
    Storage::addMediaFile($fileData);
    
    // Log the upload
    Logger::info('File uploaded', [
        'filename' => $file['name'],
        'id' => $id,
        'size' => $file['size'],
    ]);
    
    echo json_encode([
        'success' => true,
        'file' => $fileData,
    ]);
    
} catch (Throwable $e) {
    http_response_code(400);
    $errorMessage = Logger::sanitizeException($e, 'Upload failed');
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
    ]);
}

/**
 * Create a thumbnail for an image
 * @param string $sourcePath Source image path
 * @param string $destPath Destination thumbnail path
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @throws RuntimeException if thumbnail creation fails
 */
function createThumbnail(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): void
{
    // Get image info
    $imageInfo = @getimagesize($sourcePath);
    if ($imageInfo === false) {
        throw new RuntimeException('Failed to read image');
    }
    
    [$width, $height, $type] = $imageInfo;
    
    // Load source image based on type
    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $source = @imagecreatefromwebp($sourcePath);
            break;
        default:
            throw new RuntimeException('Unsupported image type');
    }
    
    if ($source === false) {
        throw new RuntimeException('Failed to load image');
    }
    
    // Calculate thumbnail dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $thumbWidth = (int)($width * $ratio);
    $thumbHeight = (int)($height * $ratio);
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
    
    if ($thumb === false) {
        imagedestroy($source);
        throw new RuntimeException('Failed to create thumbnail');
    }
    
    // Preserve transparency for PNG and GIF
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }
    
    // Resize
    $success = imagecopyresampled(
        $thumb, $source,
        0, 0, 0, 0,
        $thumbWidth, $thumbHeight,
        $width, $height
    );
    
    if (!$success) {
        imagedestroy($source);
        imagedestroy($thumb);
        throw new RuntimeException('Failed to resize image');
    }
    
    // Save thumbnail
    $saved = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $saved = imagejpeg($thumb, $destPath, 85);
            break;
        case IMAGETYPE_PNG:
            $saved = imagepng($thumb, $destPath, 6);
            break;
        case IMAGETYPE_GIF:
            $saved = imagegif($thumb, $destPath);
            break;
        case IMAGETYPE_WEBP:
            $saved = imagewebp($thumb, $destPath, 85);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($thumb);
    
    if (!$saved) {
        throw new RuntimeException('Failed to save thumbnail');
    }
}
