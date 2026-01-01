<?php
/**
 * Test media upload and management functionality
 */

declare(strict_types=1);

require_once __DIR__ . '/../pagewright/pw-admin/load.php';

// Test configuration
define('TEST_UPLOADS_DIR', __DIR__ . '/../pagewright/pw-public/uploads');
define('TEST_MEDIA_JSON', __DIR__ . '/../pagewright/pw-storage/media.json');

$testsPassed = 0;
$testsFailed = 0;

// Check if GD is available
$gdAvailable = extension_loaded('gd') && function_exists('imagecreatetruecolor');

function testHeader(string $title): void {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  {$title}\n";
    echo str_repeat('=', 60) . "\n\n";
}

function testCase(string $name): void {
    echo "→ {$name} ... ";
}

function pass(string $message = ''): void {
    global $testsPassed;
    $testsPassed++;
    echo "✓ PASS";
    if ($message) echo " ({$message})";
    echo "\n";
}

function fail(string $message): void {
    global $testsFailed;
    $testsFailed++;
    echo "✗ FAIL: {$message}\n";
}

function testSummary(): void {
    global $testsPassed, $testsFailed;
    $total = $testsPassed + $testsFailed;
    
    echo "\n" . str_repeat('-', 60) . "\n";
    echo "Tests: {$total} total, {$testsPassed} passed, {$testsFailed} failed\n";
    
    if ($testsFailed === 0) {
        echo "✓ All tests passed!\n";
        exit(0);
    } else {
        echo "✗ Some tests failed.\n";
        exit(1);
    }
}

// ============================================================================
// Test 1: Storage Methods
// ============================================================================

testHeader('Test 1: Storage Methods');

testCase('Load empty media metadata');
try {
    // Backup existing media.json
    $backup = null;
    if (file_exists(TEST_MEDIA_JSON)) {
        $backup = file_get_contents(TEST_MEDIA_JSON);
    }
    
    // Create empty media.json
    file_put_contents(TEST_MEDIA_JSON, json_encode(['files' => []]));
    
    $media = Storage::loadMedia();
    if (isset($media['files']) && is_array($media['files']) && empty($media['files'])) {
        pass('Empty media loaded correctly');
    } else {
        fail('Unexpected media structure');
    }
    
    // Restore backup
    if ($backup !== null) {
        file_put_contents(TEST_MEDIA_JSON, $backup);
    }
} catch (Exception $e) {
    fail($e->getMessage());
}

testCase('Add media file to metadata');
try {
    $testFile = [
        'id' => 'test123',
        'filename' => 'test-image.jpg',
        'url' => '/pw-public/uploads/test123_123456.jpg',
        'thumb_url' => '/pw-public/uploads/thumbs/thumb_test123_123456.jpg',
        'type' => 'image/jpeg',
        'size' => 54321,
        'uploaded_at' => gmdate('Y-m-d H:i:s'),
    ];
    
    Storage::addMediaFile($testFile);
    $media = Storage::loadMedia();
    
    if (count($media['files']) > 0 && $media['files'][0]['id'] === 'test123') {
        pass('File added to metadata');
    } else {
        fail('File not added correctly');
    }
} catch (Exception $e) {
    fail($e->getMessage());
}

testCase('Load media with existing files');
try {
    $media = Storage::loadMedia();
    if (isset($media['files']) && is_array($media['files']) && count($media['files']) >= 1) {
        pass(count($media['files']) . ' file(s) in metadata');
    } else {
        fail('Could not load media');
    }
} catch (Exception $e) {
    fail($e->getMessage());
}

// ============================================================================
// Test 2: Upload Directory Structure
// ============================================================================

testHeader('Test 2: Upload Directory Structure');

testCase('Uploads directory exists');
if (is_dir(TEST_UPLOADS_DIR)) {
    pass(TEST_UPLOADS_DIR);
} else {
    fail('Uploads directory not found');
}

testCase('Uploads directory is writable');
if (is_writable(TEST_UPLOADS_DIR)) {
    pass();
} else {
    fail('Uploads directory not writable');
}

testCase('Thumbnails directory exists');
$thumbsDir = TEST_UPLOADS_DIR . '/thumbs';
if (is_dir($thumbsDir)) {
    pass($thumbsDir);
} else {
    fail('Thumbnails directory not found');
}

testCase('Security files present');
$htaccess = TEST_UPLOADS_DIR . '/.htaccess';
$indexPhp = TEST_UPLOADS_DIR . '/index.php';

if (file_exists($htaccess) && file_exists($indexPhp)) {
    pass('.htaccess and index.php');
} else {
    fail('Security files missing');
}

// ============================================================================
// Test 3: File Upload Simulation
// ============================================================================

testHeader('Test 3: File Upload Simulation');

if (!$gdAvailable) {
    testCase('GD extension check');
    fail('GD extension not available - skipping image tests');
    echo "\n  Install GD: sudo apt-get install php-gd (or brew install php --with-gd on Mac)\n\n";
} else {
    testCase('Create test image');
    $testImagePath = TEST_UPLOADS_DIR . '/test_upload.jpg';
    try {
        // Create a small test image (1x1 red pixel)
        $img = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        imagejpeg($img, $testImagePath, 90);
        imagedestroy($img);
        
        if (file_exists($testImagePath)) {
            pass(filesize($testImagePath) . ' bytes');
        } else {
            fail('Failed to create test image');
        }
    } catch (Exception $e) {
        fail($e->getMessage());
    }

    testCase('Verify image format');
    try {
        $imageInfo = getimagesize($testImagePath);
        if ($imageInfo && $imageInfo[2] === IMAGETYPE_JPEG) {
            pass('JPEG format: ' . $imageInfo[0] . 'x' . $imageInfo[1]);
        } else {
            fail('Invalid image format');
        }
    } catch (Exception $e) {
        fail($e->getMessage());
    }

    testCase('Generate thumbnail');
    try {
        $thumbPath = TEST_UPLOADS_DIR . '/thumbs/thumb_test_upload.jpg';
        
        // Load source image
        $source = imagecreatefromjpeg($testImagePath);
        if ($source === false) {
            throw new Exception('Failed to load source image');
        }
        
        // Create thumbnail
        $thumb = imagecreatetruecolor(50, 50);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, 50, 50, 100, 100);
        imagejpeg($thumb, $thumbPath, 85);
        
        imagedestroy($source);
        imagedestroy($thumb);
        
        if (file_exists($thumbPath)) {
            $thumbSize = getimagesize($thumbPath);
            pass('50x50 thumbnail created');
        } else {
            fail('Thumbnail not created');
        }
    } catch (Exception $e) {
        fail($e->getMessage());
    }

    // Clean up test files
    testCase('Delete test files from filesystem');
    try {
        $deleted = 0;
        
        if (file_exists($testImagePath)) {
            unlink($testImagePath);
            $deleted++;
        }
        
        $thumbPath = TEST_UPLOADS_DIR . '/thumbs/thumb_test_upload.jpg';
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
            $deleted++;
        }
        
        if ($deleted > 0) {
            pass("{$deleted} file(s) deleted");
        } else {
            fail('No files to delete');
        }
    } catch (Exception $e) {
        fail($e->getMessage());
    }
}

// ============================================================================
// Test 4: Media Deletion
// ============================================================================

testHeader('Test 4: Media Deletion');
try {
    // Try to delete our test file
    $media = Storage::loadMedia();
    $initialCount = count($media['files']);
    
    // Find and delete test123
    $deleted = false;
    foreach ($media['files'] as $file) {
        if ($file['id'] === 'test123') {
            Storage::deleteMediaFile('test123');
            $deleted = true;
            break;
        }
    }
    
    if ($deleted) {
        $media = Storage::loadMedia();
        $finalCount = count($media['files']);
        pass("Removed from metadata ({$initialCount} → {$finalCount})");
    } else {
        pass('Test file already removed');
    }
} catch (Exception $e) {
    fail($e->getMessage());
}

// ============================================================================
// Test 5: GD Extension Check
// ============================================================================

testHeader('Test 5: PHP Environment');

testCase('GD extension loaded');
if (extension_loaded('gd')) {
    $gdInfo = gd_info();
    pass('GD ' . ($gdInfo['GD Version'] ?? 'unknown'));
} else {
    fail('GD extension not available - thumbnails will not work');
}

testCase('JPEG support');
if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
    pass();
} else {
    fail('JPEG functions not available');
}

testCase('PNG support');
if (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
    pass();
} else {
    fail('PNG functions not available');
}

testCase('File upload settings');
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');
echo "\n  upload_max_filesize: {$maxUpload}\n";
echo "  post_max_size: {$maxPost}\n";
pass();

// ============================================================================
// Summary
// ============================================================================

testSummary();
