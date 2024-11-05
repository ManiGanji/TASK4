<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

header('Content-Type: application/json');

// Define the folder for saving annotated images
$annotatedImagesFolder = 'annotated_images';

// Ensure the folder exists and is writable
if (!file_exists($annotatedImagesFolder)) {
    mkdir($annotatedImagesFolder, 0777, true); // Create folder with write permissions
}

try {
    // Get POST data
    $jsonData = file_get_contents('php://input');
    error_log("Received JSON data: " . $jsonData);

    if (empty($jsonData)) {
        throw new Exception('No data received');
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received: ' . json_last_error_msg());
    }

    // Check for required fields
    if (empty($data['imageId'])) {
        error_log("imageId is missing");
        throw new Exception('Missing required data: imageId');
    }
    if (empty($data['annotations'])) {
        error_log("annotations are missing");
        throw new Exception('Missing required data: annotations');
    }
    if (empty($data['annotated_image'])) {
        error_log("annotated_image is missing");
        throw new Exception('Missing required data: annotated_image');
    }

    $imageId = (int)$data['imageId'];
    $annotations = $data['annotations'];
    $base64Image = $data['annotated_image'];

    $conn = getDBConnection();

    // Get original image filename
    $stmt = $conn->prepare("SELECT filename FROM images WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database preparation error: ' . $conn->error);
    }

    $stmt->bind_param("i", $imageId);
    if (!$stmt->execute()) {
        throw new Exception('Database execution error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $image = $result->fetch_assoc();
    $stmt->close();

    if (!$image) {
        throw new Exception('Image not found in database');
    }

    // Begin database transaction
    $conn->begin_transaction();

    try {
        // Insert each annotation
        $stmt = $conn->prepare("INSERT INTO annotations (image_id, x, y, width, height, description) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($annotations as $annotation) {
            if (!isset($annotation['coords']) || 
                !isset($annotation['coords']['x']) || 
                !isset($annotation['coords']['y']) || 
                !isset($annotation['coords']['width']) || 
                !isset($annotation['coords']['height'])) {
                throw new Exception('Invalid annotation format');
            }

            $x = (float)$annotation['coords']['x'];
            $y = (float)$annotation['coords']['y'];
            $width = (float)$annotation['coords']['width'];
            $height = (float)$annotation['coords']['height'];
            $description = isset($annotation['description']) ? $annotation['description'] : '';

            // Log the annotation details
            error_log("Inserting annotation: Image ID: $imageId, x: $x, y: $y, width: $width, height: $height, description: $description");

            // Bind and execute query
            $stmt->bind_param("idddds", $imageId, $x, $y, $width, $height, $description);
            if (!$stmt->execute()) {
                throw new Exception('Failed to save annotation to database: ' . $stmt->error);
            }
        }

        // Commit the transaction after successful annotation insertion
        $conn->commit();
        $stmt->close();

        // Save the annotated image to the folder
        $savePath = $annotatedImagesFolder . '/' . $imageId . '.png';

        // Check and decode the base64 image
        if (strpos($base64Image, 'base64,') !== false) {
            $base64Image = explode('base64,', $base64Image)[1];
        }

        // Decode the base64 image
        $decodedImage = base64_decode($base64Image);
        if ($decodedImage === false) {
            throw new Exception("Base64 decoding failed for annotated image.");
        }

        if (file_put_contents($savePath, $decodedImage)) {
            error_log("Annotated image saved at $savePath");
            $response = [
                'success' => true,
                'message' => 'Annotations and image saved successfully',
                'annotatedImage' => $savePath // Return path of annotated image
            ];
        } else {
            throw new Exception("Failed to save annotated image file.");
        }

        echo json_encode($response);

    } catch (Exception $e) {
        // Roll back the transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in save_annotations.php: " . $e->getMessage());
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    // Clear output buffer and send error response
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
ob_end_flush();
