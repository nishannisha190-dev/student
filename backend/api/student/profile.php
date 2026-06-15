<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$student = requireAuth('student');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare("SELECT id, name, email, roll_number, course, phone, created_at, updated_at FROM students WHERE id=?");
    $stmt->bind_param('i', $student['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $profile]);

} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name  = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $course = trim($data['course'] ?? '');

    if (!empty($data['password'])) {
        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE students SET name=?, phone=?, course=?, password=? WHERE id=?");
        $stmt->bind_param('ssssi', $name, $phone, $course, $hashed, $student['id']);
    } else {
        $stmt = $db->prepare("UPDATE students SET name=?, phone=?, course=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $phone, $course, $student['id']);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
$db->close();
