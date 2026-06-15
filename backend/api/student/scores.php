<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$student = requireAuth('student');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT qr.id, qr.score, qr.total_questions, qr.submitted_at,
               q.title as quiz_title, q.description,
               ROUND((qr.score / qr.total_questions) * 100, 1) as percentage
        FROM quiz_results qr
        JOIN quizzes q ON qr.quiz_id = q.id
        WHERE qr.student_id = ?
        ORDER BY qr.submitted_at DESC
    ");
    $stmt->bind_param('i', $student['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $scores = [];
    while ($row = $result->fetch_assoc()) $scores[] = $row;
    echo json_encode(['success' => true, 'data' => $scores]);
} else {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
}
$db->close();
