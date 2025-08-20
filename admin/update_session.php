<?php
require __DIR__.'/_common.php'; require_auth();
header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = pdo();
  $data = json_decode(file_get_contents('php://input'), true) ?? [];
  $id = isset($data['id']) ? (int)$data['id'] : 0;
  $tel = trim((string)($data['telegram_id'] ?? ''));

  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

  // Нормализация: пустое => NULL
  $tel = ($tel === '') ? null : $tel;

  $stmt = $pdo->prepare("UPDATE sessions SET telegram_id = :tel WHERE id = :id");
  $stmt->execute([':tel'=>$tel, ':id'=>$id]);

  echo json_encode(['ok'=>true, 'id'=>$id, 'telegram_id'=>$tel]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
