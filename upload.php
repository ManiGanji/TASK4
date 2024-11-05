<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('No image uploaded');
    }

    $file = $_FILES['image'];
    
    // Validate file type
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }
    
    // Generate unique filename
    $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($file['name']));
    $uploadPath = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        error_log('Failed to move uploaded file to: ' . $uploadPath);
    }
    

    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO images (filename) VALUES (?)");
    $stmt->bind_param("s", $fileName);
    
    if (!$stmt->execute()) {
        unlink($uploadPath); // Delete the uploaded file if database insert fails
        throw new Exception('Failed to save to database');
    }
    
    $imageId = $conn->insert_id;
    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'imageId' => $imageId,
        'filename' => $fileName
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}