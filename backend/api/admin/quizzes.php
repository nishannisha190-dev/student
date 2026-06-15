<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$admin = requireAuth('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare("SELECT q.*, a.name as created_by_name, (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) as question_count FROM quizzes q JOIN admins a ON q.created_by=a.id ORDER BY q.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $quizzes = [];
    while ($row = $result->fetch_assoc()) $quizzes[] = $row;
    echo json_encode(['success' => true, 'data' => $quizzes]);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $title = trim($data['title'] ?? '');
    $desc = trim($data['description'] ?? '');
    $time_limit = intval($data['time_limit'] ?? 10);
    $is_published = intval($data['is_published'] ?? 0);

    if (!$title) { http_response_code(400); echo json_encode(['error' => 'Quiz title is required']); exit(); }

    $stmt = $db->prepare("INSERT INTO quizzes (title, description, time_limit, is_published, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiii', $title, $desc, $time_limit, $is_published, $admin['id']);
    if ($stmt->execute()) {
        $quiz_id = $db->insert_id;
        // Insert questions if provided
        if (!empty($data['questions'])) {
            $qStmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['questions'] as $q) {
                $qStmt->bind_param('issssss', $quiz_id, $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option']);
                $qStmt->execute();
            }
            $qStmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Quiz created successfully', 'id' => $quiz_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create quiz']);
    }

} elseif ($method === 'PUT') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Quiz ID required']); exit(); }
    $data = json_decode(file_get_contents('php://input'), true);

    // Toggle publish only
    if (isset($data['is_published']) && count($data) === 1) {
        $pub = intval($data['is_published']);
        $stmt = $db->prepare("UPDATE quizzes SET is_published=? WHERE id=?");
        $stmt->bind_param('ii', $pub, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Quiz status updated']);
        exit();
    }

    $title = trim($data['title'] ?? '');
    $desc = trim($data['description'] ?? '');
    $time_limit = intval($data['time_limit'] ?? 10);
    $is_published = intval($data['is_published'] ?? 0);

    $stmt = $db->prepare("UPDATE quizzes SET title=?, description=?, time_limit=?, is_published=? WHERE id=?");
    $stmt->bind_param('ssiii', $title, $desc, $time_limit, $is_published, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Quiz updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update quiz']);
    }

} elseif ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Quiz ID required']); exit(); }
    $stmt = $db->prepare("DELETE FROM quizzes WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Quiz deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete quiz']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
$db->close();
