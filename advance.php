<?php
// advance.php
require 'db_connect.php';

// Return JSON
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Retrieve POST data
$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;
$option  = $_POST['option']  ?? null; // 1 => (pair_1a, pair_1b), 2 => (pair_2a, pair_2b), 3 => (pair_3a, pair_3b)

// Basic validation
if (!$game_id || !$token || !$option) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID, player token, and option are required.']);
    exit;
}

try {
    // 1) Find the player and game info
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
    $playerData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if valid
    if (!$playerData) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token.']);
        exit;
    }

    $player_id           = $playerData['player_id'];
    $current_turn_player = $playerData['current_turn_player'];
    $game_status         = $playerData['status'];

    // 2) Ensure it’s the player’s turn and the game is in progress
    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'The game is not in progress.']);
        exit;
    }
    if ($player_id != $current_turn_player) {
        echo json_encode(['status' => 'error', 'message' => 'It is not your turn.']);
        exit;
    }

    // 3) Find the latest dice roll with has_rolled = 1
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
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id
    ]);
    $diceRoll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$diceRoll) {
        echo json_encode(['status' => 'error', 'message' => 'No dice roll found. You must roll before advancing.']);
        exit;
    }
    if ($diceRoll['has_rolled'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'You must roll again before advancing.']);
        exit;
    }

    // 4) Determine which sums we’re advancing based on $option
    $optionMap = [
        1 => [$diceRoll['pair_1a'], $diceRoll['pair_1b']],
        2 => [$diceRoll['pair_2a'], $diceRoll['pair_2b']],
        3 => [$diceRoll['pair_3a'], $diceRoll['pair_3b']]
    ];

    // If invalid option (e.g., 4 or something else), do not reset has_rolled
    if (!isset($optionMap[$option])) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'Invalid pair option. Please choose a valid option.'
        ]);
        exit;
    }

    $selectedPair = $optionMap[$option];
    $messages     = [];
    $didAdvance   = false; // Flag to track if at least one column was advanced

    // 5) For each sum in the selected pair, update `turn_markers`
    foreach ($selectedPair as $colNum) {
        // Check if column exists in `columns` table
        $stmt = $db->prepare("SELECT 1 FROM columns WHERE column_number = :col");
        $stmt->execute([':col' => $colNum]);
        $colExists = $stmt->fetchColumn();

        // If column doesn't exist, skip it (don't reset has_rolled yet)
        if (!$colExists) {
            $messages[] = "Column $colNum does not exist in the game.";
            continue;
        }

        // Enforce a max of 3 distinct columns in turn_markers
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT column_number)
            FROM turn_markers
            WHERE game_id = :game_id
              AND player_id = :player_id
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
        $distinctCount = $stmt->fetchColumn();

        if ($distinctCount >= 3) {
            // If they already have 3 distinct columns, only allow if colNum is among them
            $stmt = $db->prepare("
                SELECT 1
                FROM turn_markers
                WHERE game_id = :game_id
                  AND player_id = :player_id
                  AND column_number = :col
            ");
            $stmt->execute([
                ':game_id'   => $game_id,
                ':player_id' => $player_id,
                ':col'       => $colNum
            ]);
            $alreadyUsed = $stmt->fetchColumn();

            if (!$alreadyUsed) {
                // This would be a 4th distinct column, skip it
                $messages[] = "Cannot add a 4th distinct column ($colNum). Skipped.";
                continue;
            }
        }

        // Insert or update the row in turn_markers
        $stmt = $db->prepare("
            INSERT INTO turn_markers (game_id, player_id, column_number, temp_progress)
            VALUES (:game_id, :player_id, :col, 1)
            ON DUPLICATE KEY UPDATE temp_progress = temp_progress + 1
        ");
        $stmt->execute([
            ':game_id'   => $game_id,
            ':player_id' => $player_id,
            ':col'       => $colNum
        ]);

        // We advanced at least one column
        $didAdvance = true;
        $messages[] = "Advanced temporary marker on column $colNum.";
    }

    // 6) Only reset `has_rolled = 0` if we successfully advanced at least one column
    if ($didAdvance) {
        $stmt = $db->prepare("
            UPDATE dice_rolls
            SET has_rolled = 0
            WHERE game_id = :game_id
              AND player_id = :player_id
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    }

    // 7) Respond accordingly
    if ($didAdvance) {
        echo json_encode([
            'status'  => 'success',
            'message' => implode(' ', $messages)
        ]);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => 'No valid columns were advanced. Please select a valid option.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
