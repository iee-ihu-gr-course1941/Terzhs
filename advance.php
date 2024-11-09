<?php
// advance_move.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;
    $columns = $_POST['columns'] ?? [];

    // Get player ID using the token
    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $player_id = $player['id'];
        
        // Check the number of markers used in this turn
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT column_number) AS active_markers 
            FROM player_columns 
            WHERE game_id = :game_id AND player_id = :player_id AND is_active = 1
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
        $marker_count = $stmt->fetchColumn();

        if ($marker_count >= 3) {
            echo json_encode(['status' => 'error', 'message' => 'Player has already placed 3 markers this turn']);
            exit;
        }

        // Process each selected column
        foreach ($columns as $column_number) {
            $stmt = $db->prepare("
                INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
                VALUES (:game_id, :player_id, :column_number, 1, 1)
                ON DUPLICATE KEY UPDATE progress = progress + 1, is_active = 1
            ");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);

            // Check if player reached the maximum height of the column
            $stmt = $db->prepare("
                SELECT c.max_value, pc.progress
                FROM columns c
                JOIN player_columns pc ON c.column_number = pc.column_number
                WHERE pc.game_id = :game_id AND pc.player_id = :player_id AND pc.column_number = :column_number
            ");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);
            $result = $stmt->fetch();

            if ($result && $result['progress'] >= $result['max_value']) {
                $stmt = $db->prepare("UPDATE player_columns SET is_active = 0, is_won = 1 WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column_number");
                $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);

                $stmt = $db->prepare("SELECT COUNT(*) AS won_columns FROM player_columns WHERE game_id = :game_id AND player_id = :player_id AND is_won = 1");
                $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
                $won_columns = $stmt->fetchColumn();

                if ($won_columns >= 3) {
                    $stmt = $db->prepare("UPDATE games SET winner_id = :player_id WHERE id = :game_id");
                    $stmt->execute([':player_id' => $player_id, ':game_id' => $game_id]);

                    echo json_encode(['status' => 'success', 'message' => "Player has won the game by claiming 3 columns!"]);
                    exit;
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Player advanced in the selected columns']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
