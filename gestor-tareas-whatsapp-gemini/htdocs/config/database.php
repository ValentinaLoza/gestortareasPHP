<?php

$host = "sql103.infinityfree.com";
$dbname = "if0_42594080_gestortareas";
$username = "if0_42594080";
$password = "qNOUwZyWmbs6P";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}