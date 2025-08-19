<?php
require __DIR__.'/_common.php'; require_auth(); $pdo=pdo();
$from=$_GET['from']??''; $to=$_GET['to']??''; $topic=$_GET['topic']??'';
$where=[]; $params=[];
if($from){$where[]="s.created_at >= :from"; $params[':from']=$from." 00:00:00";}
if($to){$where[]="s.created_at <= :to"; $params[':to']=$to." 23:59:59";}
if($topic!==''){ $where[]="a.topic=:topic"; $params[':topic']=intval($topic); }
$where_sql=$where?("WHERE ".implode(" AND ",$where)):"";
$sql="SELECT s.id as session_id, s.result_code, s.telegram_id, s.created_at as session_created, a.id as answer_id, a.text_id, a.gap_id, a.topic, a.choice, a.correct, a.is_correct, a.ts_ms, a.created_at as answer_created FROM sessions s LEFT JOIN answers a ON a.session_id=s.id $where_sql ORDER BY s.created_at DESC, a.id ASC";
$stmt=$pdo->prepare($sql); $stmt->execute($params);
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="export.csv"');
$out=fopen('php://output','w'); fputcsv($out,['session_id','result_code','telegram_id','session_created','answer_id','text_id','gap_id','topic','choice','correct','is_correct','ts_ms','answer_created']);
while($row=$stmt->fetch(PDO::FETCH_ASSOC)){ fputcsv($out,$row); } fclose($out);
