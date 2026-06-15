<?php
require_once __DIR__ . '/../../middleware/auth.php';
setCORSHeaders();
$student = requireAuth('student');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$quiz_id = intval($data['quiz_id'] ?? 0);
$answers = $data['answers'] ?? [];

if (!$quiz_id || empty($answers)) {
    http_response_code(400); echo json_encode(['error' => 'quiz_id and answers are required']); exit();
}

// Check already attempted
$checkStmt = $db->prepare("SELECT id FROM quiz_results WHERE student_id=? AND quiz_id=?");
$checkStmt->bind_param('ii', $student['id'], $quiz_id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    http_response_code(409); echo json_encode(['error' => 'Quiz already submitted']); exit();
}

// Get correct answers
$qStmt = $db->prepare("SELECT id, correct_option FROM questions WHERE quiz_id=?");
$qStmt->bind_param('i', $quiz_id);
$qStmt->execute();
$result = $qStmt->get_result();
$correctMap = [];
while ($row = $result->fetch_assoc()) $correctMap[$row['id']] = $row['correct_option'];

$score = 0;
$total = count($correctMap);
$answerDetails = [];

foreach ($answers as $question_id => $selected) {
    $qid = intval($question_id);
    $isCorrect = isset($correctMap[$qid]) && strtoupper($selected) === $correctMap[$qid];
    if ($isCorrect) $score++;
    $answerDetails[] = [
        'question_id' => $qid,
        'selected' => strtoupper($selected),
        'correct' => $correctMap[$qid] ?? null,
        'is_correct' => $isCorrect
    ];
}

$answersJson = json_encode($answerDetails);
$stmt = $db->prepare("INSERT INTO quiz_results (student_id, quiz_id, score, total_questions, answers) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('iiiss', $student['id'], $quiz_id, $score, $total, $answersJson);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Quiz submitted successfully',
        'result' => [
            'score' => $score,
            'total' => $total,
            'percentage' => $total > 0 ? round(($score/$total)*100, 1) : 0,
            'answers' => $answerDetails
        ]
    ]);
} else {
    http_response_code(500); echo json_encode(['error' => 'Failed to save result']);
}
$db->close();
