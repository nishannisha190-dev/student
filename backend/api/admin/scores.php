<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$admin = requireAuth('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $quiz_id = intval($_GET['quiz_id'] ?? 0);

    if ($quiz_id) {
        // Scores for a specific quiz
        $stmt = $db->prepare("
            SELECT qr.*, s.name as student_name, s.email, s.roll_number, s.course,
                   q.title as quiz_title, q.time_limit
            FROM quiz_results qr
            JOIN students s ON qr.student_id = s.id
            JOIN quizzes q ON qr.quiz_id = q.id
            WHERE qr.quiz_id = ?
            ORDER BY qr.score DESC, qr.submitted_at ASC
        ");
        $stmt->bind_param('i', $quiz_id);
    } else {
        // All scores
        $stmt = $db->prepare("
            SELECT qr.*, s.name as student_name, s.email, s.roll_number,
                   q.title as quiz_title
            FROM quiz_results qr
            JOIN students s ON qr.student_id = s.id
            JOIN quizzes q ON qr.quiz_id = q.id
            ORDER BY qr.submitted_at DESC
        ");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $scores = [];
    while ($row = $result->fetch_assoc()) {
        $row['percentage'] = $row['total_questions'] > 0
            ? round(($row['score'] / $row['total_questions']) * 100, 1)
            : 0;
        $scores[] = $row;
    }

    // Also return summary stats
    $statsResult = $db->query("SELECT COUNT(DISTINCT id) as total_students FROM students");
    $statsRow = $statsResult->fetch_assoc();
    $quizResult = $db->query("SELECT COUNT(*) as total_quizzes FROM quizzes");
    $quizRow = $quizResult->fetch_assoc();

    echo json_encode([
        'success' => true,
        'data' => $scores,
        'stats' => [
            'total_students' => $statsRow['total_students'],
            'total_quizzes' => $quizRow['total_quizzes'],
            'total_attempts' => count($scores)
        ]
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
$db->close();
