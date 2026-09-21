<?php
// ============================================================
// Database Connection (PDO singleton)
// ============================================================

class Database {
  private static $instance = null;

  public static function conn(): PDO {
    if (self::$instance === null) {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
      try {
        self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]);
      } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed']));
      }
    }
    return self::$instance;
  }

  public static function query(string $sql, array $params = []): PDOStatement {
    $stmt = self::conn()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
  }

  public static function fetch(string $sql, array $params = []): ?array {
    $stmt = self::query($sql, $params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
  }

  public static function fetchAll(string $sql, array $params = []): array {
    $stmt = self::query($sql, $params);
    return $stmt->fetchAll();
  }

  public static function insert(string $table, array $data): int {
    $cols = array_keys($data);
    $placeholders = array_map(fn($c) => ':' . $c, $cols);
    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")";
    self::query($sql, $data);
    return (int) self::conn()->lastInsertId();
  }

  public static function update(string $table, array $data, string $where, array $whereParams = []): int {
    $set = [];
    foreach (array_keys($data) as $col) {
      $set[] = "`{$col}` = :{$col}";
    }
    $sql = "UPDATE `{$table}` SET " . implode(',', $set) . " WHERE {$where}";
    self::query($sql, array_merge($data, $whereParams));
    return self::query("SELECT ROW_COUNT()")->fetchColumn();
  }

  public static function delete(string $table, string $where, array $params = []): int {
    self::query("DELETE FROM `{$table}` WHERE {$where}", $params);
    return self::query("SELECT ROW_COUNT()")->fetchColumn();
  }

  public static function scalar(string $sql, array $params = []) {
    $stmt = self::query($sql, $params);
    return $stmt->fetchColumn();
  }
}
