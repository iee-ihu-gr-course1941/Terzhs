<?php
// stop.php
require 'db_connect.php';

// We'll return JSON responses
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;

if (!$game_id || !$token) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Game ID and player token are required.'
    ]);
    exit;
}

try {
    // 1) Find player from the token
    $stmt = $db->prepare("
        SELECT id 
        FROM players 
        WHERE player_token = :token
    ");
    $stmt->execute([
        ':token' => $token
    ]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid player token.'
        ]);
        exit;
    }
    $player_id = $player['id'];

    // 2) Check game status and turn
    $stmt = $db->prepare("
        SELECT 
            id,
            status,
            current_turn_player,
            player1_id,
            player2_id
        FROM games 
        WHERE id = :game_id
    ");
    $stmt->execute([':game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid game ID.'
        ]);
        exit;
    }
    if ($game['status'] !== 'in_progress') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Game is not in progress.'
        ]);
        exit;
    }
    if ($game['current_turn_player'] != $player_id) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'It is not your turn.'
        ]);
        exit;
    }

    // We will collect messages about columns that got completed
    $messages = [];

    // 3) Merge from turn_markers into player_columns
    $stmt = $db->prepare("
        SELECT column_number, temp_progress
        FROM turn_markers
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id
    ]);
    $tempMarkers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($tempMarkers) {
        foreach ($tempMarkers as $marker) {
            $column_number = $marker['column_number'];
            $temp_progress = (int)$marker['temp_progress'];

            // Insert or update player's permanent progress
            $stmtMerge = $db->prepare("
                INSERT INTO player_columns (
                    game_id, 
                    player_id, 
                    column_number,
                    progress,
                    is_active
                ) VALUES (
                    :game_id,
                    :player_id,
                    :col_num,
                    :temp_progress,
                    0
                )
                ON DUPLICATE KEY UPDATE
                    progress = progress + VALUES(progress)
            ");
            $stmtMerge->execute([
                ':game_id'      => $game_id,
                ':player_id'    => $player_id,
                ':col_num'      => $column_number,
                ':temp_progress'=> $temp_progress
            ]);

            // Check if that column reached max height
            $stmtCheck = $db->prepare("
                SELECT pc.progress, c.max_height
                FROM player_columns pc
                JOIN columns c ON c.column_number = pc.column_number
                WHERE pc.game_id = :game_id
                  AND pc.player_id = :player_id
                  AND pc.column_number = :col_num
            ");
            $stmtCheck->execute([
                ':game_id'   => $game_id,
                ':player_id' => $player_id,
                ':col_num'   => $column_number
            ]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['progress'] >= $row['max_height']) {
                // Mark this column as won
                $stmtWon = $db->prepare("
                    UPDATE player_columns
                    SET is_won = 1,
                        is_active = 0
                    WHERE game_id = :game_id
                      AND player_id = :player_id
                      AND column_number = :col_num
                ");
                $stmtWon->execute([
                    ':game_id'   => $game_id,
                    ':player_id' => $player_id,
                    ':col_num'   => $column_number
                ]);

                // We won't append a separate message
                // because we'll handle the overall message below 
                // if the user has 3 columns.
            } else {
                // The column is not yet completed, so print a partial-progress message
                $messages[] = "Column $column_number progress increased to {$row['progress']}.";
            }
        }
    }

    // 4) Clear turn_markers now that we’ve merged them
    $stmt = $db->prepare("
        DELETE FROM turn_markers
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id
    ]);

    // 5) Deactivate columns for the player (end of turn)
    $stmt = $db->prepare("
        UPDATE player_columns
        SET is_active = 0
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id
    ]);

    // 6) Check if the player has now won 3 columns
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM player_columns
        WHERE game_id = :game_id
          AND player_id = :player_id
          AND is_won = 1
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id
    ]);
    $wonCount = (int)$stmt->fetchColumn();

    if ($wonCount >= 3) {
        // Gather all the columns the player won
        $stmtColumnsWon = $db->prepare("
            SELECT column_number
            FROM player_columns
            WHERE game_id = :game_id
              AND player_id = :player_id
              AND is_won = 1
        ");
        $stmtColumnsWon->execute([
            ':game_id'   => $game_id,
            ':player_id' => $player_id
        ]);
        $columnsWonList = $stmtColumnsWon->fetchAll(PDO::FETCH_COLUMN);

        // Mark the game completed
        $stmtEnd = $db->prepare("
            UPDATE games
            SET status = 'completed',
                winner_id = :player_id
            WHERE id = :game_id
        ");
        $stmtEnd->execute([
            ':player_id' => $player_id, 
            ':game_id'   => $game_id
        ]);

        // Instead of printing all partial messages plus the won columns,
        // we override the entire final message:
        echo json_encode([
            'status'  => 'success',
            'message' => "You have won the game with columns " 
                         . implode(', ', $columnsWonList) 
                         . "! Congratulations!"
        ]);
        exit;
    }

    // 7) Switch turn if no winner yet
    $stmt = $db->prepare("
        UPDATE games
        SET current_turn_player = CASE 
            WHEN current_turn_player = player1_id THEN player2_id 
            ELSE player1_id 
        END
        WHERE id = :game_id
    ");
    $stmt->execute([':game_id' => $game_id]);

    // 8) Print normal success message if the user has not yet won
    //    (Just includes partial progress messages, if any)
    echo json_encode([
        'status'  => 'success',
        'message' => empty($messages) 
            ? "Turn ended. Your progress is locked in, and it's now the other player's turn."
            : implode(' ', $messages) 
              . " Turn ended. Your progress is locked in, and it's now the other player's turn."
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
