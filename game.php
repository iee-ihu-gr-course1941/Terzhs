<?php
// game.php
require 'db_connect.php';

// Helper function to get player ID by token
function getPlayerIdByToken($db, $token) {
    $stmt = $db->prepare("SELECT id FROM players WHERE token = :token");
    $stmt->execute([':token' => $token]);
    return $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $token = $_POST['token']; // Get the token from the request

    // Get player ID from the token
    $player_id = getPlayerIdByToken($db, $token);

    if (!$player_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    if ($action == 'create') {
        // Create a new game session
        $stmt = $db->prepare("INSERT INTO games (player1_id, current_turn_player, status) VALUES (:player1_id, :current_turn_player, 'waiting')");
        $stmt->execute([':player1_id' => $player_id, ':current_turn_player' => $player_id]);
        echo json_encode(['game_id' => $db->lastInsertId()]);

    } elseif ($action == 'join') {
        // Join an existing game in waiting status
        $stmt = $db->prepare("SELECT id FROM games WHERE status = 'waiting' LIMIT 1");
        $stmt->execute();
        $game = $stmt->fetch();

        if ($game) {
            $stmt = $db->prepare("UPDATE games SET player2_id = :player2_id, status = 'in_progress' WHERE id = :game_id");
            $stmt->execute([':player2_id' => $player_id, ':game_id' => $game['id']]);
            echo json_encode(['game_id' => $game['id']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No game available to join']);
        }
    }
}
?>
