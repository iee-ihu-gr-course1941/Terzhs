<?php
// stop.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'];
    $token = $_POST['token'];

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        // Update the current turn player to the other player
        $stmt = $db->prepare("UPDATE games SET current_turn_player = CASE WHEN current_turn_player = player1_id THEN player2_id ELSE player1_id END WHERE id = :game_id");
        $stmt->execute([':game_id' => $game_id]);

        echo json_encode(['status' => 'success', 'message' => "Turn ended, next player's turn"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
