<?php
session_start();

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $uploads_dir = '../uploads/';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($_FILES['image']['name']);
    $target_file = $uploads_dir . $file_name;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
        // Return relative URL to insert into editor
        echo '../uploads/' . $file_name;
    } else {
        http_response_code(500);
        echo "Failed to upload image";
    }
} else {
    http_response_code(400);
    echo "No file uploaded";
}
?>
