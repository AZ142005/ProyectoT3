<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'condominio_cobranzas';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

function executeQuery($sql, $params = [], $types = "") {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function getRecord($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getRecords($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function executeNonQuery($sql, $params = [], $types = "") {
    $stmt = executeQuery($sql, $params, $types);
    return $stmt->affected_rows;
}
?>