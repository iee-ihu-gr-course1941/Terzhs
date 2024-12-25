<?php
// advance.php
require 'db_connect.php';

// Return JSON response
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get the required parameters
$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;
$option  = $_POST['option']  ?? null; // e.g., 1 => pair_1, 2 => pair_2, 3 => pair_3

// Validate input
if (!$game_id || !$token || !$option) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID, player token, and option are required.']);
    exit;
}

try {
    // 1) Get player and game data
    $stmt = $db->prepare("
        SELECT 
            p.id AS player_id,
            g.current_turn_player,
            g.status
        FROM players p
        JOIN games g ON g.id = :game_id
        WHERE p.player_token = :token
    ");
    $stmt->execute([':game_id' => $game_id, ':token' => $token]);
    $player_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the record is valid
    if (!$player_data) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token.']);
        exit;
    }

    $player_id           = $player_data['player_id'];
    $current_turn_player = $player_data['current_turn_player'];
    $game_status         = $player_data['status'];

    // 2) Confirm the game is active and it’s the player’s turn
    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'The game is not in progress.']);
        exit;
    }
    if ($current_turn_player != $player_id) {
        echo json_encode(['status' => 'error', 'message' => 'It is not your turn.']);
        exit;
    }

    // 3) Get the latest roll for this player
    $stmt = $db->prepare("
        SELECT 
            pair_1a, pair_1b, 
            pair_2a, pair_2b, 
            pair_3a, pair_3b, 
            has_rolled
        FROM dice_rolls
        WHERE game_id = :game_id
          AND player_id = :player_id
        ORDER BY roll_time DESC
        LIMIT 1
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $dice_roll = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if a roll exists and is still pending
    if (!$dice_roll) {
        echo json_encode(['status' => 'error', 'message' => 'No dice roll found. Please roll before advancing.']);
        exit;
    }
    if ($dice_roll['has_rolled'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'You must roll the dice before advancing again.']);
        exit;
    }

    // 4) Map the chosen option (1, 2, or 3) to the correct pair
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

    // 5) Advance progress in player_columns for each sum in the chosen pair
    foreach ($selected_pair as $column_number) {
        // Check if the column exists
        $stmt = $db->prepare("SELECT max_height FROM columns WHERE column_number = :column_number");
        $stmt->execute([':column_number' => $column_number]);
        $column_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Skip if column does not exist
        if (!$column_info) {
            $messages[] = "Invalid column number: $column_number";
            continue;
        }

        // Insert or update the player's progress for this column
        $stmt = $db->prepare("
            INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
            VALUES (:game_id, :player_id, :col, 1, 1)
            ON DUPLICATE KEY UPDATE
                progress = progress + 1,
                is_active = 1
        ");
        $stmt->execute([
            ':game_id'   => $game_id,
            ':player_id' => $player_id,
            ':col'       => $column_number
        ]);

        // Check updated progress
        $stmt = $db->prepare("
            SELECT pc.progress, c.max_height
            FROM player_columns pc
            JOIN columns c ON c.column_number = pc.column_number
            WHERE pc.game_id = :game_id
              AND pc.player_id = :player_id
              AND pc.column_number = :column_number
        ");
        $stmt->execute([
            ':game_id'     => $game_id,
            ':player_id'   => $player_id,
            ':column_number' => $column_number
        ]);
        $progress_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // If progress reached or exceeded max height, mark the column as won
        if ($progress_info && $progress_info['progress'] >= $progress_info['max_height']) {
            $stmt = $db->prepare("
                UPDATE player_columns
                SET is_active = 0, is_won = 1
                WHERE game_id = :game_id
                  AND player_id = :player_id
                  AND column_number = :column_number
            ");
            $stmt->execute([
                ':game_id'     => $game_id,
                ':player_id'   => $player_id,
                ':column_number' => $column_number
            ]);
            $messages[] = "Column $column_number is won!";
        } else {
            $messages[] = "Column $column_number advanced to " 
                          . $progress_info['progress'] 
                          . " out of " 
                          . $progress_info['max_height'] . ".";
        }
    }

    // 6) Allow the player to roll again by resetting has_rolled
    $stmt = $db->prepare("
        UPDATE dice_rolls
        SET has_rolled = 0
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

    // 7) Check if this player has won 3 columns
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM player_columns
        WHERE game_id = :game_id
          AND player_id = :player_id
          AND is_won = 1
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $columns_won = $stmt->fetchColumn();

    // If 3 columns are won, mark the game as completed
    if ($columns_won >= 3) {
        $stmt = $db->prepare("
            UPDATE games
            SET status = 'completed', winner_id = :player_id
            WHERE id = :game_id
        ");
        $stmt->execute([':player_id' => $player_id, ':game_id' => $game_id]);
        $messages[] = "You have won the game!";
    }

    // Return a success response
    echo json_encode([
        'status'  => 'success',
        'message' => implode(' ', $messages)
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
