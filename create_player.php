<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_token = bin2hex(random_bytes(16));
    $stmt = $db->prepare("INSERT INTO players (player_token) VALUES (:player_token)");
    $stmt->execute([':player_token' => $player_token]);

    echo json_encode(['status' => 'success', 'player_token' => $player_token]);
}
?>
