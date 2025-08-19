<?php
require __DIR__.'/_common.php'; require_auth(); $pdo=pdo();
$from=$_GET['from']??''; $to=$_GET['to']??''; $topic=$_GET['topic']??''; $page=max(1,intval($_GET['page']??1));
$per=25; $offset=($page-1)*$per;
$where=[]; $params=[];
if($from){$where[]="s.created_at >= :from"; $params[':from']=$from." 00:00:00";}
if($to){$where[]="s.created_at <= :to"; $params[':to']=$to." 23:59:59";}
if($topic!==''){$where[]="EXISTS(SELECT 1 FROM answers a2 WHERE a2.session_id=s.id AND a2.topic=:topic)"; $params[':topic']=intval($topic);}
$where_sql=$where?("WHERE ".implode(" AND ",$where)):"";
$cnt=$pdo->prepare("SELECT COUNT(*) c FROM sessions s $where_sql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$sql="SELECT s.id,s.result_code,s.telegram_id,s.created_at,COALESCE(SUM(a.is_correct),0) correct_count,COUNT(a.id) answers_count,CASE WHEN COUNT(a.id)>0 THEN ROUND(100.0*SUM(a.is_correct)/COUNT(a.id),1) ELSE NULL END accuracy FROM sessions s LEFT JOIN answers a ON a.session_id=s.id $where_sql GROUP BY s.id ORDER BY s.created_at DESC LIMIT :per OFFSET :offset";
$stmt=$pdo->prepare($sql); foreach($params as $k=>$v){$stmt->bindValue($k,$v);} $stmt->bindValue(':per',$per,PDO::PARAM_INT); $stmt->bindValue(':offset',$offset,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
$topics=$pdo->query("SELECT DISTINCT topic FROM answers WHERE topic IS NOT NULL ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html><meta charset="utf-8"><title>Админка — результаты</title><style>body{font-family:system-ui,Arial;margin:20px}table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f6f6f6}.controls input,.controls select{padding:.4rem;margin-right:.4rem}a.btn{display:inline-block;padding:.4rem .6rem;border:1px solid #444;text-decoration:none;border-radius:4px}.pagination a{margin-right:.4rem}.badge{display:inline-block;padding:.1rem .4rem;border-radius:.3rem;background:#eef;border:1px solid #99f}</style>
<h2>Результаты тестов</h2>
<form class="controls" method="get">С даты: <input type="date" name="from" value="<?=htmlspecialchars($from)?>"> По дату: <input type="date" name="to" value="<?=htmlspecialchars($to)?>"> Тема: <select name="topic"><option value="">(все)</option><?php foreach($topics as $t): ?><option value="<?=$t?>" <?=($topic!==''&&(int)$topic===(int)$t)?'selected':''?>><?=$t?></option><?php endforeach; ?></select> <button>Фильтровать</button> <a class="btn" href="export.php?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&topic=<?=urlencode($topic)?>">Экспорт CSV</a> <a class="btn" href="index.php">Сброс</a></form>
<p>Всего попыток: <b><?=$total?></b></p>
<table><thead><tr><th>#</th><th>Код</th><th>Telegram ID</th><th>Создано</th><th>Ответов</th><th>Верных</th><th>Точность</th><th>Детали</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=$r['id']?></td><td><span class="badge"><?=$r['result_code']?></span></td><td><?=htmlspecialchars($r['telegram_id'] ?? '')?></td><td><?=$r['created_at']?></td><td><?=$r['answers_count']?></td><td><?=$r['correct_count']?></td><td><?=is_null($r['accuracy'])?'—':($r['accuracy'].'%')?></td><td><a href="session.php?id=<?=$r['id']?>">Открыть</a></td></tr><?php endforeach; ?></tbody></table>
<div class="pagination"><?php $pages=max(1,ceil($total/$per)); for($i=1;$i<=$pages;$i++){ $q=http_build_query(['from'=>$from,'to'=>$to,'topic'=>$topic,'page'=>$i]); echo $i==$page?"<b>$i</b> ":"<a href='?$q'>$i</a> "; } ?></div>
