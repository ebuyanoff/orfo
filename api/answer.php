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
  'session_id'     => (int)$sess['id'],
  'text_id'        => $in['textId']        ?? null,
  'gap_id'         => $in['gapId']         ?? null,
  'topic'          => isset($in['topic']) ? (int)$in['topic'] : null,
  'topic_title'    => $in['topicTitle']    ?? null,
  'topic_rule_url' => $in['topicRuleUrl']  ?? null,
  'choice'         => $in['choice']        ?? null,
  'correct'        => $in['correct']       ?? null,
  'is_correct'     => !empty($in['isCorrect']) ? 1 : 0,
  'ts_ms'          => isset($in['timestamp']) ? (int)$in['timestamp'] : null
];

if ($DB_DRIVER === 'mysql') {
  $sql = "INSERT INTO answers (session_id,text_id,gap_id,topic,topic_title,topic_rule_url,choice,correct,is_correct,ts_ms)
          VALUES (:session_id,:text_id,:gap_id,:topic,:topic_title,:topic_rule_url,:choice,:correct,:is_correct,:ts_ms)
          ON DUPLICATE KEY UPDATE
            topic=VALUES(topic),
            topic_title=VALUES(topic_title),
            topic_rule_url=VALUES(topic_rule_url),
            choice=VALUES(choice),
            correct=VALUES(correct),
            is_correct=VALUES(is_correct),
            ts_ms=VALUES(ts_ms),
            created_at=CURRENT_TIMESTAMP";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($payload);
} else {
  // SQLite
  $sql = "INSERT INTO answers (session_id,text_id,gap_id,topic,topic_title,topic_rule_url,choice,correct,is_correct,ts_ms)
          VALUES (:session_id,:text_id,:gap_id,:topic,:topic_title,:topic_rule_url,:choice,:correct,:is_correct,:ts_ms)
          ON CONFLICT(session_id, gap_id) DO UPDATE SET
            topic=excluded.topic,
            topic_title=excluded.topic_title,
            topic_rule_url=excluded.topic_rule_url,
            choice=excluded.choice,
            correct=excluded.correct,
            is_correct=excluded.is_correct,
            ts_ms=excluded.ts_ms,
            created_at=CURRENT_TIMESTAMP";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($payload);
}

// --- Агрегируем проценты по темам и сохраняем в sessions.topic_results_json ---
try {
  $agg = $pdo->prepare("
    SELECT
      topic,
      MAX(NULLIF(TRIM(topic_title), '')) AS title,
      MAX(NULLIF(TRIM(topic_rule_url), '')) AS rule_url,
      SUM(CASE WHEN choice <> 'честно не знаю' THEN 1 ELSE 0 END) AS total,
      SUM(CASE WHEN is_correct = 1 AND choice <> 'честно не знаю' THEN 1 ELSE 0 END) AS ok
    FROM answers
    WHERE session_id = :sid AND topic IS NOT NULL
    GROUP BY topic
    ORDER BY topic
  ");
  $agg->execute([':sid' => $sess['id']]);
  $rows = $agg->fetchAll(PDO::FETCH_ASSOC);

  $topics = [];
  foreach ($rows as $r) {
    $t      = (int)$r['topic'];
    $total  = (int)$r['total'];
    $ok     = (int)$r['ok'];
    $pct    = $total > 0 ? (int)round(($ok / $total) * 100) : 0;
    $title  = $r['title'] ?: ('Тема ' . $t);
    $rule   = $r['rule_url'] ?: ("https://orfo.club/rules/topic-{$t}.html");

    $topics[] = [
      'topic'    => $t,
      'title'    => $title,
      'rule_url' => $rule,
      'pct'      => $pct
    ];
  }

  // На всякий случай пытаемся добавить колонку (если старая БД)
  try {
    if ($DB_DRIVER === 'mysql') {
      $pdo->exec("ALTER TABLE sessions ADD COLUMN topic_results_json TEXT");
    } else {
      $pdo->exec("ALTER TABLE sessions ADD COLUMN topic_results_json TEXT");
    }
  } catch (Throwable $e) { /* ignore if exists */ }

  $upd = $pdo->prepare("UPDATE sessions SET topic_results_json = :json WHERE id = :sid");
  $upd->execute([':json' => json_encode($topics, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ':sid' => $sess['id']]);

  echo json_encode(['ok'=>true, 'topics'=>$topics], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  echo json_encode(['ok'=>true]);
}
