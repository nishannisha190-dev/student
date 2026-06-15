<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$admin = requireAuth('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    if ($search) {
        $like = "%$search%";
        $stmt = $db->prepare("SELECT id, name, email, roll_number, course, phone, created_at FROM students WHERE name LIKE ? OR email LIKE ? OR roll_number LIKE ? ORDER BY created_at DESC");
        $stmt->bind_param('sss', $like, $like, $like);
    } else {
        $stmt = $db->prepare("SELECT id, name, email, roll_number, course, phone, created_at FROM students ORDER BY created_at DESC");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) $students[] = $row;
    echo json_encode(['success' => true, 'data' => $students]);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $roll = trim($data['roll_number'] ?? '');
    $course = trim($data['course'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if (!$name || !$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, email and password are required']);
        exit();
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO students (name, email, password, roll_number, course, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssss', $name, $email, $hashed, $roll, $course, $phone);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student added successfully', 'id' => $db->insert_id]);
    } else {
        http_response_code(409);
        echo json_encode(['error' => 'Email or Roll Number already exists']);
    }

} elseif ($method === 'PUT') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Student ID required']); exit(); }
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $roll = trim($data['roll_number'] ?? '');
    $course = trim($data['course'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if (!empty($data['password'])) {
        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE students SET name=?, email=?, password=?, roll_number=?, course=?, phone=? WHERE id=?");
        $stmt->bind_param('ssssssi', $name, $email, $hashed, $roll, $course, $phone, $id);
    } else {
        $stmt = $db->prepare("UPDATE students SET name=?, email=?, roll_number=?, course=?, phone=? WHERE id=?");
        $stmt->bind_param('sssssi', $name, $email, $roll, $course, $phone, $id);
    }
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update student']);
    }

} elseif ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Student ID required']); exit(); }
    $stmt = $db->prepare("DELETE FROM students WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete student']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

$db->close();
