<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
header('Access-Control-Allow-Origin: ' . $CORS_ALLOW_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require __DIR__.'/db.php';
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$code = $in['result_code'] ?? null;
if (!$code) { http_response_code(400); echo json_encode(['error'=>'no code']); exit; }

$stmt = $pdo->prepare("SELECT id FROM sessions WHERE result_code=?");
$stmt->execute([$code]);
$sess = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sess) { http_response_code(404); echo json_encode(['error'=>'session not found']); exit; }

$payload = [
  'session_id' => (int)$sess['id'],
  'text_id'    => $in['textId']   ?? null,
  'gap_id'     => $in['gapId']    ?? null,
  'topic'      => isset($in['topic']) ? (int)$in['topic'] : null,
  'choice'     => $in['choice']   ?? null,
  'correct'    => $in['correct']  ?? null,
  'is_correct' => !empty($in['isCorrect']) ? 1 : 0,
  'ts_ms'      => isset($in['timestamp']) ? (int)$in['timestamp'] : null
];

if ($DB_DRIVER === 'mysql') {
  $sql = "INSERT INTO answers (session_id,text_id,gap_id,topic,choice,correct,is_correct,ts_ms)
          VALUES (:session_id,:text_id,:gap_id,:topic,:choice,:correct,:is_correct,:ts_ms)
          ON DUPLICATE KEY UPDATE
            topic=VALUES(topic),
            choice=VALUES(choice),
            correct=VALUES(correct),
            is_correct=VALUES(is_correct),
            ts_ms=VALUES(ts_ms),
            created_at=CURRENT_TIMESTAMP";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($payload);
} else {
  $sql = "INSERT INTO answers (session_id,text_id,gap_id,topic,choice,correct,is_correct,ts_ms)
          VALUES (:session_id,:text_id,:gap_id,:topic,:choice,:correct,:is_correct,:ts_ms)
          ON CONFLICT(session_id, gap_id) DO UPDATE SET
            topic=excluded.topic,
            choice=excluded.choice,
            correct=excluded.correct,
            is_correct=excluded.is_correct,
            ts_ms=excluded.ts_ms,
            created_at=CURRENT_TIMESTAMP";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($payload);
}

echo json_encode(['ok'=>true]);
