<?php
// advance_move.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;
    $columns = isset($_POST['columns']) ? explode(',', $_POST['columns']) : [];

    // Validate input
    if (!$game_id || !$token || empty($columns)) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID, player token, and columns are required']);
        exit;
    }

    try {
        // Get player ID using the token
        $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
        $stmt->execute([':token' => $token]);
        $player = $stmt->fetch();

        if ($player) {
            $player_id = $player['id'];

            // Check if the game is in progress and it's the player's turn
            $stmt = $db->prepare("SELECT current_turn_player, status FROM games WHERE id = :game_id");
            $stmt->execute([':game_id' => $game_id]);
            $game = $stmt->fetch();

            if (!$game) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid game ID']);
                exit;
            }

            if ($game['status'] !== 'in_progress') {
                echo json_encode(['status' => 'error', 'message' => 'Game is not in progress']);
                exit;
            }

            if ($game['current_turn_player'] != $player_id) {
                echo json_encode(['status' => 'error', 'message' => 'It is not your turn']);
                exit;
            }

            // Check active markers in the current turn
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

            foreach ($columns as $column_number) {
                // Ensure valid column number
                $stmt = $db->prepare("SELECT max_height FROM columns WHERE column_number = :column_number");
                $stmt->execute([':column_number' => $column_number]);
                $column = $stmt->fetch();

                if (!$column) {
                    echo json_encode(['status' => 'error', 'message' => "Invalid column number: $column_number"]);
                    exit;
                }

                // Check if the column is already won
                $stmt = $db->prepare("
                    SELECT is_won 
                    FROM player_columns 
                    WHERE game_id = :game_id AND column_number = :column_number AND is_won = 1
                ");
                $stmt->execute([':game_id' => $game_id, ':column_number' => $column_number]);
                $is_won = $stmt->fetch();

                if ($is_won) {
                    echo json_encode(['status' => 'error', 'message' => "Column $column_number has already been won and cannot be selected"]);
                    exit;
                }

                // Update or insert the player's marker
                $stmt = $db->prepare("
                    INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
                    VALUES (:game_id, :player_id, :column_number, 1, 1)
                    ON DUPLICATE KEY UPDATE progress = progress + 1, is_active = 1
                ");
                $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);

                // Check if player reached max height in the column
                $stmt = $db->prepare("
                    SELECT c.max_height, pc.progress
                    FROM columns c
                    JOIN player_columns pc ON c.column_number = pc.column_number
                    WHERE pc.game_id = :game_id AND pc.player_id = :player_id AND pc.column_number = :column_number
                ");
                $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);
                $result = $stmt->fetch();

                if ($result && $result['progress'] >= $result['max_height']) {
                    // Mark column as won
                    $stmt = $db->prepare("UPDATE player_columns SET is_active = 0, is_won = 1 WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column_number");
                    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column_number' => $column_number]);

                    echo json_encode(['status' => 'success', 'message' => "Player has won column $column_number"]);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Player advanced in the selected columns']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
