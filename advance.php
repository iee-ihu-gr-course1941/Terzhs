<?php
// advance.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;
    $column = $_POST['column'] ?? null;

    // Validate required inputs
    if (!$game_id || !$token || !$column) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID, player token, and column are required']);
        exit;
    }

    try {
        // Fetch player and game information
        $stmt = $db->prepare("
            SELECT 
                p.id AS player_id,
                g.current_turn_player,
                g.status,
                COALESCE(dr.has_rolled, 0) AS has_rolled,
                COUNT(pc.is_active) AS active_markers
            FROM players p
            LEFT JOIN games g ON g.id = :game_id
            LEFT JOIN dice_rolls dr ON dr.game_id = g.id AND dr.player_id = p.id
            LEFT JOIN player_columns pc ON pc.game_id = g.id AND pc.player_id = p.id AND pc.is_active = 1
            WHERE p.player_token = :token
        ");
        $stmt->execute([':game_id' => $game_id, ':token' => $token]);
        $result = $stmt->fetch();

        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token']);
            exit;
        }

        $player_id = $result['player_id'];
        $current_turn_player = $result['current_turn_player'];
        $game_status = $result['status'];
        $has_rolled = $result['has_rolled'];
        $active_markers = $result['active_markers'];

        // Validate game status and turn
        if ($game_status !== 'in_progress') {
            echo json_encode(['status' => 'error', 'message' => 'The game is not in progress']);
            exit;
        }
        if ($player_id != $current_turn_player) {
            echo json_encode(['status' => 'error', 'message' => 'It is not your turn']);
            exit;
        }

        // Ensure the player has rolled before advancing
        if (!$has_rolled) {
            echo json_encode(['status' => 'error', 'message' => 'You must roll the dice before advancing a marker.']);
            exit;
        }

        // Validate column selection
        $stmt = $db->prepare("SELECT max_value FROM columns WHERE column_number = :column");
        $stmt->execute([':column' => $column]);
        $column_data = $stmt->fetch();

        if (!$column_data) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid column selected.']);
            exit;
        }

        // Check if advancing is valid for the column
        $stmt = $db->prepare("
            SELECT 1 
            FROM dice_rolls 
            WHERE game_id = :game_id AND player_id = :player_id 
              AND (pair_1a = :column OR pair_1b = :column 
                   OR pair_2a = :column OR pair_2b = :column 
                   OR pair_3a = :column OR pair_3b = :column)
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column' => $column]);

        if (!$stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'The selected column is not valid for advancement based on your roll.']);
            exit;
        }

        // Check if the column is already won
        $stmt = $db->prepare("
            SELECT player_id 
            FROM player_columns 
            WHERE game_id = :game_id AND column_number = :column AND is_winner = 1
        ");
        $stmt->execute([':game_id' => $game_id, ':column' => $column]);
        $winner = $stmt->fetch();

        if ($winner) {
            echo json_encode(['status' => 'error', 'message' => 'This column has already been won by another player.']);
            exit;
        }

        // Advance the marker in the column
        $stmt = $db->prepare("
            INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
            VALUES (:game_id, :player_id, :column, 1, 1)
            ON DUPLICATE KEY UPDATE 
                progress = LEAST(progress + 1, :max_value), 
                is_active = 1
        ");
        $stmt->execute([
            ':game_id' => $game_id,
            ':player_id' => $player_id,
            ':column' => $column,
            ':max_value' => $column_data['max_value']
        ]);

        // Check if the column is won
        $stmt = $db->prepare("
            SELECT progress 
            FROM player_columns 
            WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column' => $column]);
        $progress = $stmt->fetchColumn();

        if ($progress >= $column_data['max_value']) {
            // Mark the column as won
            $stmt = $db->prepare("
                UPDATE player_columns 
                SET is_winner = 1, is_active = 0 
                WHERE game_id = :game_id AND player_id = :player_id AND column_number = :column
            ");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':column' => $column]);
            echo json_encode(['status' => 'success', 'message' => 'You have won the column!', 'column' => $column]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Marker advanced successfully.', 'progress' => $progress]);
        }

        // Update dice_rolls to indicate advancement
        $stmt = $db->prepare("
            UPDATE dice_rolls 
            SET has_rolled = 0 
            WHERE game_id = :game_id AND player_id = :player_id
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

    } catch (Exception $e) {
        // Handle exceptions
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
