<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['imageId'])) {
        throw new Exception('Image ID is required');
    }

    $imageId = (int)$_GET['imageId'];
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT x, y, width, height FROM annotations WHERE image_id = ?");
    $stmt->bind_param("i", $imageId);
    $stmt->execute();
    $result = $stmt->get_result();

    $annotations = [];
    while ($row = $result->fetch_assoc()) {
        $annotations[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'annotations' => $annotations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
