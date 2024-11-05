<?php
require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $conn = getDBConnection();
    $sql = "SELECT images.id, images.filename AS original, 
                   GROUP_CONCAT(annotations.id) AS annotation_ids
            FROM images 
            LEFT JOIN annotations ON images.id = annotations.image_id 
            GROUP BY images.id 
            ORDER BY images.upload_date DESC";
    
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Annotated Images</title>
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    border: 1px solid #ccc;
                    padding: 10px;
                    text-align: center;
                }
                img {
                    max-width: 100px; /* Adjust image size as needed */
                }
            </style>
            <script>
                function viewAnnotatedImage(imageId) {
                    fetch(`get_annotations.php?imageId=${imageId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const img = new Image();
                                img.src = `annotated_images/${imageId}.png`; // Adjust according to your naming convention
                                img.onload = function() {
                                    const canvas = document.createElement("canvas");
                                    const ctx = canvas.getContext("2d");
                                    canvas.width = img.width;
                                    canvas.height = img.height;
                                    ctx.drawImage(img, 0, 0);
                                    
                                    // Draw annotations
                                    data.annotations.forEach(annotation => {
                                        ctx.strokeStyle = "red";
                                        ctx.lineWidth = 2;
                                        ctx.strokeRect(
                                            annotation.x,
                                            annotation.y,
                                            annotation.width,
                                            annotation.height
                                        );
                                    });

                                    // Create a link to download the canvas
                                    const link = document.createElement("a");
                                    link.download = `annotated_${imageId}.png`;
                                    link.href = canvas.toDataURL();
                                    link.click();
                                };
                            } else {
                                alert("Error loading annotations.");
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                        });
                }

                function downloadImage(filename) {
                    const link = document.createElement("a");
                    link.href = `uploads/${filename}`; // Corrected quotes
                    link.download = filename;
                    link.click();
                }
            </script>
        </head>
        <body>
            <h1>Annotated Images</h1>
            <table>
                <tr>
                    <th>Original Image</th>
                    <th>Annotated Image</th>
                    <th>Actions</th>
                </tr>';
        
        while ($row = $result->fetch_assoc()) {
            $originalImage = htmlspecialchars($row['original'] ?? '');
            $imageId = $row['id'];
            $annotationIds = htmlspecialchars($row['annotation_ids'] ?? '');
            $annotatedImagePath = "annotated_images/{$imageId}.png"; // Adjust according to your naming convention
            
            echo '<tr>
                    <td>
                        <img src="uploads/' . $originalImage . '" alt="Original Image">
                        <br>
                        <button onclick="downloadImage(\'' . $originalImage . '\')">Download Original</button>
                    </td>
                    <td>';
            
            // Debugging: Check if there are annotation IDs and if the annotated image file exists
            echo "<!-- Debug: Annotation IDs for image {$imageId}: {$annotationIds} -->";
            echo "<!-- Debug: Annotated Image Path: {$annotatedImagePath} -->";
            
            if ($annotationIds) {
                if (file_exists($annotatedImagePath)) {
                    echo '<img src="' . $annotatedImagePath . '" alt="Annotated Image">
                          <br>
                          <button onclick="viewAnnotatedImage(' . $imageId . ')">View Annotated</button>';
                } else {
                    echo '<span style="color: red;">No annotated image available (File does not exist)</span>';
                }
            } else {
                echo '<span style="color: orange;">No annotations found</span>';
            }
            
            echo '</td>
                  </tr>';
        }

        echo '</table>
            </body>
            </html>';
    } else {
        echo '<h2>No images found.</h2>';
    }

    $conn->close();
} catch (Exception $e) {
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}
?>
