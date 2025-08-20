<?php
require __DIR__.'/_common.php'; require_auth();
$pdo = pdo();

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per  = 25;
$offset = ($page - 1) * $per;

$where = []; $params = [];
if ($from) { $where[] = "s.created_at >= :from"; $params[':from'] = $from . " 00:00:00"; }
if ($to)   { $where[] = "s.created_at <= :to";   $params[':to']   = $to   . " 23:59:59"; }
$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$cnt = $pdo->prepare("SELECT COUNT(*) FROM sessions s $where_sql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$sql = "SELECT s.id, s.result_code, s.telegram_id, s.created_at,
               COALESCE(SUM(a.is_correct),0) AS correct_cnt,
               COUNT(a.id) AS answer_cnt,
               s.topic_results_json
        FROM sessions s
        LEFT JOIN answers a ON a.session_id = s.id
        $where_sql
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT :per OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':per', $per, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function render_topics($json){
  if (!$json) return '';
  $arr = json_decode($json, true);
  if (!is_array($arr) || empty($arr)) return '';
  $out = [];
  foreach ($arr as $t) {
    $topic = intval($t['topic'] ?? 0);
    $pct   = intval($t['pct'] ?? 0);
    $url   = htmlspecialchars($t['rule_url'] ?? ("https://orfo.club/rules/topic-".$topic.".html"));
    $out[] = "<div>Тема {$topic}: <a href='{$url}' target='_blank' rel='noopener'>правило</a> — <b>{$pct}%</b></div>";
    if (count($out) >= 5) break;
  }
  return implode('', $out);
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Админка — результаты</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:1100px;margin:20px auto;padding:0 16px}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{border:1px solid #ddd;padding:8px;vertical-align:top}
  th{background:#f7f7f7;text-align:left}
  .controls{margin:10px 0;display:flex;gap:8px;align-items:center}
  .badge{display:inline-block;background:#eef;border:1px solid #99f;padding:2px 6px;border-radius:4px}
  .pagination a, .pagination b{margin-right:6px}
  .topics div{white-space:nowrap}
  a.btn{display:inline-block;padding:.4rem .6rem;border:1px solid #444;text-decoration:none;border-radius:4px}
</style>

<h2>Результаты тестов</h2>

<form class="controls" method="get">
  С даты: <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
  По дату: <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
  <button type="submit">Фильтровать</button>
  <a class="btn" href="index.php">Сброс</a>
</form>

<p>Всего попыток: <b><?=$total?></b></p>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Код</th>
      <th>Создано</th>
      <th>Telegram ID</th>
      <th>Ответов</th>
      <th>Верных</th>
      <th>Точность</th>
      <th>Итоги по темам</th>
      <th>Детали</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r):
      $acc = $r['answer_cnt'] ? round(($r['correct_cnt']/$r['answer_cnt'])*100) : 0;
    ?>
    <tr>
      <td><?=$r['id']?></td>
      <td><span class="badge"><?=htmlspecialchars($r['result_code'])?></span></td>
      <td><?=htmlspecialchars($r['created_at'])?></td>
      <td><?=htmlspecialchars($r['telegram_id'] ?? '')?></td>
      <td><?=$r['answer_cnt']?></td>
      <td><?=$r['correct_cnt']?></td>
      <td><?=$acc?>%</td>
      <td class="topics"><?=render_topics($r['topic_results_json'])?></td>
      <td><a href="session.php?id=<?=$r['id']?>">Открыть</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="pagination">
<?php
  $pages = max(1, ceil($total / $per));
  $qs = $_GET; unset($qs['page']);
  for ($i=1; $i<=$pages; $i++) {
    $qs['page'] = $i;
    $q = http_build_query($qs);
    if ($i == $page) echo "<b>$i</b> ";
    else echo "<a href=\"?$q\">$i</a> ";
  }
?>
</div>
