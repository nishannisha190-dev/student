<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$admin = requireAuth('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $quiz_id = intval($_GET['quiz_id'] ?? 0);
    if (!$quiz_id) { http_response_code(400); echo json_encode(['error' => 'quiz_id required']); exit(); }
    $stmt = $db->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY id ASC");
    $stmt->bind_param('i', $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $questions = [];
    while ($row = $result->fetch_assoc()) $questions[] = $row;
    echo json_encode(['success' => true, 'data' => $questions]);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $quiz_id = intval($data['quiz_id'] ?? 0);
    $questions = $data['questions'] ?? [];
    if (!$quiz_id || empty($questions)) {
        http_response_code(400);
        echo json_encode(['error' => 'quiz_id and questions are required']);
        exit();
    }
    // Delete old questions and re-insert
    $db->query("DELETE FROM questions WHERE quiz_id=$quiz_id");
    $stmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($questions as $q) {
        $stmt->bind_param('issssss', $quiz_id, $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option']);
        $stmt->execute();
    }
    echo json_encode(['success' => true, 'message' => 'Questions saved successfully']);

} elseif ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Question ID required']); exit(); }
    $stmt = $db->prepare("DELETE FROM questions WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Question deleted']);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
$db->close();
