<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = $_POST['game_id'];
    $token = $_POST['token'];

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $player_id = $player['id'];

        $stmt = $db->prepare("SELECT COUNT(column_number) AS won_columns FROM player_columns JOIN columns ON player_columns.column_number = columns.column_number WHERE player_id = :player_id AND game_id = :game_id AND progress >= max_value");
        $stmt->execute([':player_id' => $player_id, ':game_id' => $game_id]);
        $won_columns = $stmt->fetchColumn();

        if ($won_columns >= 3) {
            $stmt = $db->prepare("UPDATE games SET status = 'completed' WHERE id = :game_id");
            $stmt->execute([':game_id' => $game_id]);

            echo json_encode(['status' => 'success', 'message' => "Player won the game!"]);
        } else {
            $stmt = $db->prepare("UPDATE games SET current_turn_player = CASE WHEN current_turn_player = player1_id THEN player2_id ELSE player1_id END WHERE id = :game_id");
            $stmt->execute([':game_id' => $game_id]);

            echo json_encode(['status' => 'success', 'message' => "Turn ended, next player's turn"]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
