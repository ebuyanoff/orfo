<?php
require __DIR__.'/_common.php'; require_auth();
$pdo = pdo();

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$code = trim($_GET['code'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per  = 200; // пагинация по 200 строк
$offset = ($page - 1) * $per;

$where = []; $params = [];
if ($from) { $where[] = "s.created_at >= :from"; $params[':from'] = $from . " 00:00:00"; }
if ($to)   { $where[] = "s.created_at <= :to";   $params[':to']   = $to   . " 23:59:59"; }

// Поиск по коду (LIKE с поддержкой * и ?)
if ($code !== '') {
  $like = strtr($code, ['*'=>'%','?'=>'_']);
  if (strpos($like,'%')===false && strpos($like,'_')===false) { $like = '%'.$like.'%'; }
  $where[] = "s.result_code LIKE :code";
  $params[':code'] = $like;
}

$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Счётчик
$cnt = $pdo->prepare("SELECT COUNT(*) FROM sessions s $where_sql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

// Данные
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

/** ===== helpers ===== */

function normalize_host_path($url, $topic){
  $fallback = "orfo.club/rules/topic-{$topic}.html";
  if (!$url) return $fallback;
  if (strpos($url, 'http://')===0 || strpos($url, 'https://')===0) {
    $u = parse_url($url);
    if (!$u) return $fallback;
    $host = $u['host'] ?? '';
    $path = $u['path'] ?? '';
    if (!$host || !$path) return $fallback;
    return $host . $path;
  }
  if ($url[0] === '/') return 'orfo.club' . $url;
  return $fallback;
}

// Рендер тем в таблице: «<a>Название</a>: 80%.»
function render_topics_html($json){
  if (!$json) return '';
  $arr = json_decode($json, true);
  if (!is_array($arr) || empty($arr)) return '';
  usort($arr, fn($a,$b)=>($a['pct']??0)<=>($b['pct']??0));
  $out = [];
  foreach ($arr as $t) {
    $topic = intval($t['topic'] ?? 0);
    $pct   = intval($t['pct'] ?? 0);
    $title = htmlspecialchars($t['title'] ?? ('Тема '.$topic));
    $url   = htmlspecialchars($t['rule_url'] ?? ("https://orfo.club/rules/topic-".$topic.".html"));
    $out[] = "<p><a href='{$url}' target='_blank' rel='noopener'>{$title}</a>: <b>{$pct}%</b>.</p>";
  }
  return implode('', $out);
}

// Markdown для копирования в ЛС
function build_markdown($row){
  $arr = json_decode($row['topic_results_json'] ?? '[]', true) ?: [];
  usort($arr, fn($a,$b)=>($a['pct']??0)<=>($b['pct']??0));
  $lines = [];
  $lines[] = "Здравствуйте!";
  $lines[] = "";
  $lines[] = "Вот ваши результаты теста по русскому языку (код ".$row['result_code']."):";
  $lines[] = "";
  foreach ($arr as $t) {
    $topic = intval($t['topic'] ?? 0);
    $title = $t['title'] ?? ('Тема '.$topic);
    $pct   = intval($t['pct'] ?? 0);
    $plain = normalize_host_path($t['rule_url'] ?? '', $topic);
    $lines[] = "**{$title}:** {$pct}% — {$plain}";
  }
  $acc = ($row['answer_cnt'] ?? 0) ? round(($row['correct_cnt']/$row['answer_cnt'])*100) : 0;
  $lines[] = "";
  $lines[] = "Итоговая точность: **{$acc}%**";
  return implode("\n", $lines);
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Админка — результаты</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:1200px;margin:20px auto;padding:0 16px}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{border:1px solid #ddd;padding:8px;vertical-align:top}
  th{background:#f7f7f7;text-align:left}
  .controls{margin:10px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .badge{display:inline-block;background:#eef;border:1px solid #99f;padding:2px 6px;border-radius:4px}
  .pagination a, .pagination b{margin-right:6px}
  .topics p{margin:.2rem 0}
  input[type="text"], input[type="date"]{padding:.3rem .4rem;border:1px solid #bbb;border-radius:4px}
  .copy-md{position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0}
  .btn{display:inline-block;padding:.35rem .6rem;border:1px solid #444;text-decoration:none;border-radius:4px;background:#f7f7f7;cursor:pointer}
  .btn:disabled{opacity:.6;cursor:default}
  .hint{font-size:.85em;color:#666}
  .tg-input{min-width:180px;max-width:240px}
  .tg-input.saving{border-color:#888; background:#fafafa}
  .tg-input.saved{border-color:#2e7d32; box-shadow:0 0 0 2px rgba(46,125,50,.15)}
  .tg-input.error{border-color:#c62828; box-shadow:0 0 0 2px rgba(198,40,40,.15)}
  @media (max-width:900px){ .topics p{white-space:normal} }
</style>

<h2>Результаты тестов</h2>

<form id="filters" class="controls" method="get">
  С даты: <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
  По дату: <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
  Код: <input type="text" name="code" placeholder="например: A1B2C3D4 или G7K*" value="<?=htmlspecialchars($code)?>">
  <span class="hint">Автопоиск: даты по изменению, код — через 400 мс после ввода</span>
</form>

<p>Всего попыток: <b><?=$total?></b></p>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Код</th>
      <th>Создано</th>
      <th>Telegram</th>
      <th>Ответов</th>
      <th>Верных</th>
      <th>Точность</th>
      <th>Итоги по темам</th>
      <th>Скопировать результаты</th>
      <th>Детали</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r):
      $acc = $r['answer_cnt'] ? round(($r['correct_cnt']/$r['answer_cnt'])*100) : 0;
      $md  = build_markdown($r);
    ?>
    <tr>
      <td><?=$r['id']?></td>
      <td><span class="badge"><?=htmlspecialchars($r['result_code'])?></span></td>
      <td><?=htmlspecialchars($r['created_at'])?></td>
      <td>
        <input
          type="text"
          class="tg-input"
          data-id="<?=$r['id']?>"
          value="<?=htmlspecialchars($r['telegram_id'] ?? '')?>"
          placeholder="@username или numeric ID">
      </td>
      <td><?=$r['answer_cnt']?></td>
      <td><?=$r['correct_cnt']?></td>
      <td><?=$acc?>%</td>
      <td class="topics"><?=render_topics_html($r['topic_results_json'])?></td>
      <td>
        <?php $taId = 'md-'.$r['id']; ?>
        <textarea id="<?=$taId?>" class="copy-md" readonly><?=htmlspecialchars($md)?></textarea>
        <button type="button" class="btn copy-btn" data-target="<?=$taId?>">Скопировать результаты</button>
      </td>
      <td><a class="btn" href="session.php?id=<?=$r['id']?>">Открыть</a></td>
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

<script>
// Автофильтр: перезагрузка без кнопки
(function(){
  const form = document.getElementById('filters');
  if(!form) return;
  const submit = () => { form.requestSubmit ? form.requestSubmit() : form.submit(); };
  let t=null;

  // по датам — сразу
  form.querySelectorAll('input[type="date"]').forEach(el=>{
    el.addEventListener('change', submit);
  });

  // по коду — с debounce 400ms
  const code = form.querySelector('input[name="code"]');
  if (code) {
    code.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(submit, 400);
    });
  }
})();

// Копирование Markdown
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.copy-btn');
  if (!btn) return;
  const id = btn.dataset.target;
  const ta = document.getElementById(id);
  if (!ta) return;
  const txt = ta.value;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(txt).then(()=>{
      const old = btn.textContent; btn.textContent='Скопировано!'; setTimeout(()=>btn.textContent=old,1200);
    });
  } else {
    ta.focus(); ta.select(); ta.setSelectionRange(0, 1e6);
    try { document.execCommand('copy'); } catch(e){}
    const old = btn.textContent; btn.textContent='Скопировано!'; setTimeout(()=>btn.textContent=old,1200);
  }
});

// Инлайн-редактирование Telegram с автосохранением
(function(){
  const inputs = document.querySelectorAll('.tg-input');
  const save = async (id, value, el) => {
    try {
      el.classList.add('saving'); el.classList.remove('saved','error');
      const res = await fetch('update_session.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: id, telegram_id: value})
      });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'save_failed');
      el.classList.remove('saving'); el.classList.add('saved');
      setTimeout(()=>el.classList.remove('saved'), 1000);
    } catch (e) {
      el.classList.remove('saving'); el.classList.add('error');
    }
  };

  inputs.forEach(el=>{
    let timer=null;
    const id = parseInt(el.dataset.id, 10);
    const triggerSave = () => {
      clearTimeout(timer);
      timer = setTimeout(()=> save(id, el.value.trim(), el), 500); // debounce 500мс
    };
    el.addEventListener('input', triggerSave);
    el.addEventListener('change', triggerSave);
    el.addEventListener('keydown', (ev)=>{
      if (ev.key === 'Enter') { ev.preventDefault(); el.blur(); }
      if (ev.key === 'Escape') { ev.target.value = ev.target.defaultValue; ev.target.blur(); }
    });
    el.addEventListener('blur', ()=> {
      // финальный сейв на blur без задержки
      clearTimeout(timer);
      save(id, el.value.trim(), el);
    });
  });
})();
</script>
