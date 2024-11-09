<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $player_id = $player['id'];

        // Check for an open game to join
        $stmt = $db->prepare("SELECT id FROM games WHERE status = 'waiting' LIMIT 1");
        $stmt->execute();
        $game = $stmt->fetch();

        if ($game) {
            // Join an existing game
            $game_id = $game['id'];
            $stmt = $db->prepare("UPDATE games SET player2_id = :player_id, status = 'in_progress' WHERE id = :game_id");
            $stmt->execute([':player_id' => $player_id, ':game_id' => $game_id]);

            echo json_encode(['status' => 'success', 'game_id' => $game_id, 'game_status' => 'in_progress']);
        } else {
            // Create a new game
            $stmt = $db->prepare("INSERT INTO games (player1_id, current_turn_player, status) VALUES (:player1_id, :current_turn_player, 'waiting')");
            $stmt->execute([':player1_id' => $player_id, ':current_turn_player' => $player_id]);
            $game_id = $db->lastInsertId();

            echo json_encode(['status' => 'success', 'game_id' => $game_id, 'game_status' => 'waiting']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
