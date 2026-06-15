<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$student = requireAuth('student');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $quiz_id = intval($_GET['quiz_id'] ?? 0);

    if ($quiz_id) {
        // Check if already attempted
        $checkStmt = $db->prepare("SELECT id FROM quiz_results WHERE student_id=? AND quiz_id=?");
        $checkStmt->bind_param('ii', $student['id'], $quiz_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'You have already attempted this quiz']);
            exit();
        }

        // Get quiz + questions (hide correct answer)
        $stmt = $db->prepare("SELECT id, title, description, time_limit FROM quizzes WHERE id=? AND is_published=1");
        $stmt->bind_param('i', $quiz_id);
        $stmt->execute();
        $quiz = $stmt->get_result()->fetch_assoc();
        if (!$quiz) { http_response_code(404); echo json_encode(['error' => 'Quiz not found']); exit(); }

        $qStmt = $db->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE quiz_id=? ORDER BY id ASC");
        $qStmt->bind_param('i', $quiz_id);
        $qStmt->execute();
        $questions = [];
        while ($row = $qStmt->get_result()->fetch_assoc()) $questions[] = $row;
        $quiz['questions'] = $questions;
        echo json_encode(['success' => true, 'data' => $quiz]);
    } else {
        // List all published quizzes with attempt status
        $stmt = $db->prepare("
            SELECT q.id, q.title, q.description, q.time_limit, q.created_at,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) as question_count,
                   (SELECT id FROM quiz_results WHERE student_id=? AND quiz_id=q.id) as attempted
            FROM quizzes q WHERE q.is_published=1 ORDER BY q.created_at DESC
        ");
        $stmt->bind_param('i', $student['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $quizzes = [];
        while ($row = $result->fetch_assoc()) {
            $row['attempted'] = $row['attempted'] ? true : false;
            $quizzes[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $quizzes]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
$db->close();
