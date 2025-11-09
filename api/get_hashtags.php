<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode(['ok'=>true, 'items'=>[]]);
    exit();
}

try {
    $likeTerm = $q . '%';
    $stmt = $conn->prepare("SELECT name FROM hashtags WHERE name LIKE ? ORDER BY name ASC LIMIT 10");
    $stmt->bind_param("s", $likeTerm);
    $stmt->execute();
    $res = $stmt->get_result();

    $names = [];
    while ($row = $res->fetch_assoc()) {
        $names[] = $row['name'];
    }

    echo json_encode(['ok'=>true, 'items'=>$names]);

    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'items'=>[]]);
    exit();
}
?>
