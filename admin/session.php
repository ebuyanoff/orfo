<?php
require __DIR__.'/_common.php'; require_auth(); $pdo=pdo();
$id=intval($_GET['id']??0); if(!$id){http_response_code(400); echo "No id"; exit;}
$s=$pdo->prepare("SELECT * FROM sessions WHERE id=?"); $s->execute([$id]); $session=$s->fetch(); if(!$session){echo "Session not found"; exit;}
$a=$pdo->prepare("SELECT * FROM answers WHERE session_id=? ORDER BY id ASC"); $a->execute([$id]); $answers=$a->fetchAll();
?>
<!doctype html><meta charset="utf-8"><title>Сессия #<?=$session['id']?></title><style>body{font-family:system-ui,Arial;margin:20px}table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f6f6f6}.badge{display:inline-block;padding:.1rem .4rem;border-radius:.3rem;background:#eef;border:1px solid #99f}</style>
<p><a href="index.php">← Назад</a></p>
<h2>Сессия #<?=$session['id']?> / <span class="badge"><?=$session['result_code']?></span></h2>
<p>Создано: <?=$session['created_at']?> | Telegram ID: <?=htmlspecialchars($session['telegram_id'] ?? '')?></p>
<table><thead><tr><th>#</th><th>text_id</th><th>gap_id</th><th>topic</th><th>choice</th><th>correct</th><th>верно?</th><th>time(ms)</th><th>создано</th></tr></thead><tbody>
<?php foreach($answers as $r): ?><tr><td><?=$r['id']?></td><td><?=htmlspecialchars($r['text_id'])?></td><td><?=htmlspecialchars($r['gap_id'])?></td><td><?=$r['topic']?></td><td><?=htmlspecialchars($r['choice'])?></td><td><?=htmlspecialchars($r['correct'])?></td><td><?=$r['is_correct']?'✅':'❌'?></td><td><?=$r['ts_ms']?></td><td><?=$r['created_at']?></td></tr><?php endforeach; ?></tbody></table>
