<?php
require __DIR__.'/config.php';

try {
  if ($DB_DRIVER === 'mysql') {
    $dsn = "mysql:host={$MYSQL_HOST};dbname={$MYSQL_DB};charset={$MYSQL_CHARSET}";
    $pdo = new PDO($dsn, $MYSQL_USER, $MYSQL_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET NAMES {$MYSQL_CHARSET}");
  } else {
    $pdo = new PDO('sqlite:' . $SQLITE_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>'db_connect_failed','message'=>$e->getMessage()]);
  exit;
}

/* === MIGRATIONS === */
if ($DB_DRIVER === 'mysql') {
  // sessions
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS sessions (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      result_code VARCHAR(32) NOT NULL UNIQUE,
      telegram_id VARCHAR(32) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      topic_results_json TEXT NULL,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // answers (+ колонки topic_title, topic_rule_url)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS answers (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_id INT UNSIGNED NOT NULL,
      text_id VARCHAR(255) NULL,
      gap_id  VARCHAR(255) NULL,
      topic   INT NULL,
      topic_title VARCHAR(255) NULL,
      topic_rule_url VARCHAR(255) NULL,
      choice  VARCHAR(255) NULL,
      correct VARCHAR(255) NULL,
      is_correct TINYINT(1) NULL,
      ts_ms   BIGINT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      INDEX idx_answers_session (session_id),
      CONSTRAINT fk_answers_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  // уникальность (session_id, gap_id)
  try { $pdo->exec("ALTER TABLE answers ADD UNIQUE KEY uniq_session_gap (session_id, gap_id)"); } catch (Throwable $e) {}
  // догоняем недостающие колонки на старой БД
  foreach (['topic_title VARCHAR(255) NULL','topic_rule_url VARCHAR(255) NULL'] as $ddl) {
    try { $pdo->exec("ALTER TABLE answers ADD COLUMN $ddl"); } catch (Throwable $e) {}
  }
  try { $pdo->exec("ALTER TABLE sessions ADD COLUMN topic_results_json TEXT"); } catch (Throwable $e) {}
} else {
  // SQLite
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS sessions(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      result_code TEXT UNIQUE NOT NULL,
      telegram_id TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      topic_results_json TEXT
    );
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS answers(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      session_id INTEGER NOT NULL,
      text_id TEXT, gap_id TEXT, topic INTEGER,
      topic_title TEXT, topic_rule_url TEXT,
      choice TEXT, correct TEXT, is_correct INTEGER, ts_ms INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(session_id) REFERENCES sessions(id) ON DELETE CASCADE
    );
  ");
  $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uniq_session_gap ON answers(session_id, gap_id)");
  // догоняем колонки
  foreach (['ALTER TABLE answers ADD COLUMN topic_title TEXT',
            'ALTER TABLE answers ADD COLUMN topic_rule_url TEXT',
            'ALTER TABLE sessions ADD COLUMN topic_results_json TEXT'] as $ddl) {
    try { $pdo->exec($ddl); } catch (Throwable $e) {}
  }
}
