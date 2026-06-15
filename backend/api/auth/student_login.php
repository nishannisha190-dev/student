<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit();
}

$db = getDB();
$stmt = $db->prepare("SELECT id, name, email, password, roll_number, course, phone FROM students WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student || !password_verify($password, $student['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit();
}

$token = generateJWT([
    'id' => $student['id'],
    'name' => $student['name'],
    'email' => $student['email'],
    'role' => 'student'
]);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => $student['id'],
        'name' => $student['name'],
        'email' => $student['email'],
        'roll_number' => $student['roll_number'],
        'course' => $student['course'],
        'phone' => $student['phone'],
        'role' => 'student'
    ]
]);

$stmt->close();
$db->close();
