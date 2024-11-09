<?php
// stop.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;

    // Get player ID using the token
    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $player_id = $player['id'];

        // Check if it's currently the player's turn
        $stmt = $db->prepare("SELECT current_turn_player FROM games WHERE id = :game_id");
        $stmt->execute([':game_id' => $game_id]);
        $current_turn_player = $stmt->fetchColumn();

        if ($current_turn_player != $player_id) {
            echo json_encode(['status' => 'error', 'message' => "It's not your turn"]);
            exit;
        }

        // Reset markers for the player for the next turn
        $stmt = $db->prepare("UPDATE player_columns SET is_active = 0 WHERE game_id = :game_id AND player_id = :player_id");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

        // Switch turn to the other player
        $stmt = $db->prepare("
            UPDATE games 
            SET current_turn_player = CASE 
                WHEN current_turn_player = player1_id THEN player2_id 
                ELSE player1_id 
            END 
            WHERE id = :game_id
        ");
        $stmt->execute([':game_id' => $game_id]);

        echo json_encode(['status' => 'success', 'message' => "Turn ended. It's now the other player's turn."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
