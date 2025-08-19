<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/config.php';
header('Access-Control-Allow-Origin: ' . $CORS_ALLOW_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require __DIR__.'/db.php';
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$code = $in['result_code'] ?? null;
$action = $in['action'] ?? null;

if (!$code) { http_response_code(400); echo json_encode(['error'=>'no code']); exit; }

if ($action === 'start') {
  if ($DB_DRIVER === 'mysql') {
    $stmt = $pdo->prepare("INSERT INTO sessions (result_code) VALUES (?) 
                           ON DUPLICATE KEY UPDATE result_code = VALUES(result_code)");
    $stmt->execute([$code]);
  } else {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO sessions(result_code) VALUES(?)");
    $stmt->execute([$code]);
  }
  echo json_encode(['ok'=>true]); exit;
}

if ($action === 'link_telegram') {
  $tg = $in['telegram_id'] ?? null;
  if (!$tg) { http_response_code(400); echo json_encode(['error'=>'no telegram_id']); exit; }
  $stmt = $pdo->prepare("UPDATE sessions SET telegram_id=? WHERE result_code=?");
  $stmt->execute([$tg, $code]);
  echo json_encode(['ok'=>true]); exit;
}

http_response_code(400); echo json_encode(['error'=>'bad action']);
