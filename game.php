<?php
// game.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'create') {
        // Create a new game session
        $player1_id = $_POST['player_id'];
        $stmt = $db->prepare("INSERT INTO games (player1_id, current_turn_player, status) VALUES (:player1_id, :current_turn_player, 'waiting')");
        $stmt->execute([':player1_id' => $player1_id, ':current_turn_player' => $player1_id]);
        echo json_encode(['game_id' => $db->lastInsertId()]);
        
    } elseif ($action == 'join') {
        // Join an existing game in waiting status
        $player2_id = $_POST['player_id'];
        $stmt = $db->prepare("SELECT id FROM games WHERE status = 'waiting' LIMIT 1");
        $stmt->execute();
        $game = $stmt->fetch();
        
        if ($game) {
            $stmt = $db->prepare("UPDATE games SET player2_id = :player2_id, status = 'in_progress' WHERE id = :game_id");
            $stmt->execute([':player2_id' => $player2_id, ':game_id' => $game['id']]);
            echo json_encode(['game_id' => $game['id']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No game available to join']);
        }
    }
}
?>
