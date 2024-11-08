<?php
// game.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $token = $_POST['token'];

    // Get player ID using the token
    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if (!$player) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    $player_id = $player['id'];

    if ($action == 'create') {
        // Start a new game
        $stmt = $db->prepare("INSERT INTO games (player1_id, current_turn_player, status) VALUES (:player1_id, :current_turn_player, 'waiting')");
        $stmt->execute([':player1_id' => $player_id, ':current_turn_player' => $player_id]);
        $game_id = $db->lastInsertId();

        echo json_encode(['game_id' => $game_id, 'status' => 'waiting']);
        
    } elseif ($action == 'join') {
        // Join an existing game in 'waiting' status
        $stmt = $db->prepare("SELECT id FROM games WHERE status = 'waiting' LIMIT 1");
        $stmt->execute();
        $game = $stmt->fetch();

        if ($game) {
            $stmt = $db->prepare("UPDATE games SET player2_id = :player2_id, status = 'in_progress' WHERE id = :game_id");
            $stmt->execute([':player2_id' => $player_id, ':game_id' => $game['id']]);

            echo json_encode(['game_id' => $game['id'], 'status' => 'in_progress']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No game available to join']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
}
?>
