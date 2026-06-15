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
$stmt = $db->prepare("SELECT id, name, email, password FROM admins WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin || !password_verify($password, $admin['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit();
}

$token = generateJWT([
    'id' => $admin['id'],
    'name' => $admin['name'],
    'email' => $admin['email'],
    'role' => 'admin'
]);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => 'admin'
    ]
]);

$stmt->close();
$db->close();
