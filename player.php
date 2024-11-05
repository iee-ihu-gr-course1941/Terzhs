<?php
// player.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'register') {
        // Register a new player
        $playerToken = bin2hex(random_bytes(16)); // Generate a unique token
        $stmt = $db->prepare("INSERT INTO players (player_token) VALUES (:player_token)");
        $stmt->execute([':player_token' => $playerToken]);
        echo json_encode(['player_token' => $playerToken]);
        
    } elseif ($action == 'login') {
        // Login a player using their token
        $playerToken = $_POST['player_token'];
        $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :player_token");
        $stmt->execute([':player_token' => $playerToken]);
        $player = $stmt->fetch();
        
        if ($player) {
            echo json_encode(['status' => 'success', 'player_id' => $player['id']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        }
    }
}
?>