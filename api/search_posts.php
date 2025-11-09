<?php
// File: /api/search_posts.php

header('Content-Type: application/json');
require_once '../config/database.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit();
}

$suggestions = [];
$likeTerm = "%" . $term . "%";
$stmt = $conn->prepare("SELECT title FROM blogpost WHERE title LIKE ? ORDER BY title ASC LIMIT 5");
$stmt->bind_param("s", $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row['title'];
}

echo json_encode($suggestions);
?>