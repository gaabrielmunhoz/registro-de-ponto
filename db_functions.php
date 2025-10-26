<?php
// db_functions.php 
// Biblioteca CRUD simples para MySQL

require_once __DIR__ . '/vendor/autoload.php';

// carrega .env apenas se existir

$envPath = __DIR__ ;
if (file_exists($envPath.'/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv ->load();
}

function envOrFail($key) {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        die("Config ausente: {$key}");
    }
    return $val;
}

function db_connect() {
    $host = envOrFail('DB_HOST');
    $user = envOrFail('DB_USER');
    $pass = envOrFail('DB_PASS');
    $name = envOrFail('DB_NAME');
    $port = (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

    $conn = new mysqli($host, $user, $pass, $name, $port);
    if ($conn->connect_error) {
        error_log("Erro de conexão MYSQL: " . $conn->connect_error);
        die("Erro de conexão: " . $conn->connect_error);
    }

    // Define charset para evitar problemas com acentos
    $conn->set_charset("utf8mb4");

    return $conn;
}

/**
 * SELECT genérico
 * @param string $table - nome da tabela
 * @param array $where - condições [coluna => valor]
 * @param string $columns - colunas a selecionar (default = "*")
 */
function db_select($table, $where = [], $columns = "*") {
    $conn = db_connect();

    $sql = "SELECT $columns FROM $table";
    $params = [];
    $types = "";

    if (!empty($where)) {
        $clauses = [];
        foreach ($where as $col => $val) {
            $clauses[] = "$col = ?";
            $params[] = $val;
            $types .= "s";
        }
        $sql .= " WHERE " . implode(" AND ", $clauses);
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $conn->close();

    return $data;
}

/**
 * INSERT genérico
 * @param string $table - nome da tabela
 * @param array $data - dados [coluna => valor]
 */
function db_insert($table, $data) {
    $conn = db_connect();

    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));
    $types = str_repeat("s", count($data));

    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...array_values($data));

    $ok = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $ok;
}

/**
 * UPDATE genérico
 * @param string $table - nome da tabela
 * @param array $data - dados para atualizar [coluna => valor]
 * @param array $where - condições [coluna => valor]
 */
function db_update($table, $data, $where) {
    $conn = db_connect();

    $set = [];
    $params = [];
    $types = "";

    foreach ($data as $col => $val) {
        $set[] = "$col = ?";
        $params[] = $val;
        $types .= "s";
    }

    $clauses = [];
    foreach ($where as $col => $val) {
        $clauses[] = "$col = ?";
        $params[] = $val;
        $types .= "s";
    }

    $sql = "UPDATE $table SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $clauses);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    $ok = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $ok;
}

/**
 * DELETE genérico
 * @param string $table - nome da tabela
 * @param array $where - condições [coluna => valor]
 */
function db_delete($table, $where) {
    $conn = db_connect();

    $clauses = [];
    $params = [];
    $types = "";

    foreach ($where as $col => $val) {
        $clauses[] = "$col = ?";
        $params[] = $val;
        $types .= "s";
    }

    $sql = "DELETE FROM $table WHERE " . implode(" AND ", $clauses);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    $ok = $stmt->execute();

    $stmt->close();
    $conn->close();

    return $ok;
}
