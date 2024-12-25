<?php
// advance.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;
    $option = $_POST['option'] ?? null; // Selected dice option to advance

    // Validate required inputs
    if (!$game_id || !$token || !$option) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID, player token, and option are required.']);
        exit;
    }

    try {
        // Fetch player and game details
        $stmt = $db->prepare("
            SELECT p.id AS player_id, g.current_turn_player, g.status
            FROM players p
            JOIN games g ON g.id = :game_id
            WHERE p.player_token = :token
        ");
        $stmt->execute([':game_id' => $game_id, ':token' => $token]);
        $player_data = $stmt->fetch();

        if (!$player_data) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token.']);
            exit;
        }

        $player_id = $player_data['player_id'];
        $current_turn_player = $player_data['current_turn_player'];
        $game_status = $player_data['status'];

        if ($game_status !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'The game is not in progress.']);
            exit;
        }

        if ($current_turn_player != $player_id) {
            echo json_encode(['status' => 'error', 'message' => 'It is not your turn.']);
            exit;
        }

        // Fetch the latest dice roll for this turn
        $stmt = $db->prepare("
            SELECT pair_1a, pair_1b, pair_2a, pair_2b, pair_3a, pair_3b, has_rolled
            FROM dice_rolls
            WHERE game_id = :game_id AND player_id = :player_id
            ORDER BY roll_time DESC LIMIT 1
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
        $dice_roll = $stmt->fetch();

        if (!$dice_roll) {
            echo json_encode(['status' => 'error', 'message' => 'No dice roll found for this turn. You must roll before advancing.']);
            exit;
        }

        // Check if the dice has been rolled
        if ($dice_roll['has_rolled'] != 1) {
            echo json_encode(['status' => 'error', 'message' => 'You must roll the dice before advancing.']);
            exit;
        }

        // Validate the selected option
        $options_map = [
            1 => [$dice_roll['pair_1a'], $dice_roll['pair_1b']],
            2 => [$dice_roll['pair_2a'], $dice_roll['pair_2b']],
            3 => [$dice_roll['pair_3a'], $dice_roll['pair_3b']],
        ];

        if (!isset($options_map[$option])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid option selected.']);
            exit;
        }

        $selected_pair = $options_map[$option];
        $messages = [];

        foreach ($selected_pair as $column_number) {
            // Validate column number
            $stmt = $db->prepare("SELECT max_height FROM columns WHERE column_number = :column_number");
            $stmt->execute([':column_number' => $column_number]);
            $column = $stmt->fetch();

            if (!$column) {
                $messages[] = "Invalid column number: $column_number";
                continue;
            }

            $stmt = $db->prepare("
                INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
                VALUES (:game_id, :player_id, :column_number, 1, 1)
                ON DUPLICATE KEY UPDATE progress = progress + 1, is_active = 1
            ");
            $stmt->execute([
                ':game_id' => $game_id,
                ':player_id' => $player_id,
                ':column_number' => $column_number,
            ]);

            // Check if column is won
            $stmt = $db->prepare("
                SELECT pc.progress, c.max_height
                FROM player_columns pc
                JOIN columns c ON c.column_number = pc.column_number
                WHERE pc.game_id = :game_id AND pc.player_id = :player_id AND pc.column_number = :column_number
            ");
            $stmt->execute([
                ':game_id' => $game_id,
                ':player_id' => $player_id,
                ':column_number' => $column_number,
            ]);
            $progress = $stmt->fetch();

            if ($progress['progress'] >= $progress['max_height']) {
                $stmt = $db->prepare("
                    UPDATE player_columns SET is_active = 0, is_won = 1
                    WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column_number
                ");
                $stmt->execute([
                    ':game_id' => $game_id,
                    ':player_id' => $player_id,
                    ':column_number' => $column_number,
                ]);
                $messages[] = "Column $column_number is won!";
            } else {
                $messages[] = "Column $column_number progressed to {$progress['progress']}. Max height: {$progress['max_height']}.";
            }
        }

        // Reset `has_rolled` after advancement
        $stmt = $db->prepare("
            UPDATE dice_rolls SET has_rolled = 0
            WHERE game_id = :game_id AND player_id = :player_id
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

        echo json_encode(['status' => 'success', 'message' => implode('. ', $messages)]);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
