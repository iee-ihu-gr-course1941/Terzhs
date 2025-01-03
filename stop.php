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
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid player token.'
        ]);
        exit;
    }
    $player_id = $player['id'];

    // 2) Check game status and whose turn it is
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

    // We'll collect partial progress messages here
    $messages = [];

    // 3) Fetch temporary progress from turn_markers
    $stmt = $db->prepare("
        SELECT column_number, temp_progress
        FROM turn_markers
        WHERE game_id   = :game_id
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

            // --- 3.1) Check if this column is already won or maxed for THIS player ---
            // We'll see if is_won=1 or progress >= max_height before we do increments
            $stmtCheckPlayerCol = $db->prepare("
                SELECT 
                    pc.progress,
                    pc.is_won,
                    c.max_height
                FROM columns c
                LEFT JOIN player_columns pc
                       ON pc.column_number = c.column_number
                      AND pc.game_id      = :game_id
                      AND pc.player_id    = :player_id
                WHERE c.column_number = :col_num
                LIMIT 1
            ");
            $stmtCheckPlayerCol->execute([
                ':game_id'   => $game_id,
                ':player_id' => $player_id,
                ':col_num'   => $column_number
            ]);
            $row = $stmtCheckPlayerCol->fetch(PDO::FETCH_ASSOC);

            // If there's no row yet in player_columns, that means progress=0, is_won=0 by default.
            $existingProgress = $row ? (int)$row['progress'] : 0;
            $maxHeight        = $row ? (int)$row['max_height'] : 0;
            $isWon            = $row ? (int)$row['is_won'] : 0;

            // If is_won=1 or existing progress is >= max height, skip further increments
            if ($isWon === 1 || $existingProgress >= $maxHeight) {
                if ($isWon === 1) {
                    $messages[] = "Column $column_number is already won by you; skipping any increments.";
                } else {
                    $messages[] = "Column $column_number is already at max progress ($existingProgress); skipping increments.";
                }
                continue; 
            }

            // --- 3.2) Merge the new temp_progress into the player's permanent progress ---
            $stmtMerge = $db->prepare("
                INSERT INTO player_columns (game_id, player_id, column_number, progress, is_won)
                VALUES (:game_id, :player_id, :col_num, :temp_progress, 0)
                ON DUPLICATE KEY UPDATE
                    progress = progress + VALUES(progress)
            ");
            $stmtMerge->execute([
                ':game_id'      => $game_id,
                ':player_id'    => $player_id,
                ':col_num'      => $column_number,
                ':temp_progress'=> $temp_progress
            ]);

            // --- 3.3) Now re-check final progress for messages ---
            $stmtCheckFinal = $db->prepare("
                SELECT pc.progress, c.max_height
                FROM player_columns pc
                JOIN columns c ON c.column_number = pc.column_number
                WHERE pc.game_id   = :game_id
                  AND pc.player_id = :player_id
                  AND pc.column_number = :col_num
                LIMIT 1
            ");
            $stmtCheckFinal->execute([
                ':game_id'   => $game_id,
                ':player_id' => $player_id,
                ':col_num'   => $column_number
            ]);
            $finalRow = $stmtCheckFinal->fetch(PDO::FETCH_ASSOC);

            if (!$finalRow) {
                // Shouldn't happen, but just in case
                continue;
            }
            $finalProgress = (int)$finalRow['progress'];
            $colMaxHeight  = (int)$finalRow['max_height'];

            if ($finalProgress >= $colMaxHeight) {
                // Mark the column as won
                $stmtWon = $db->prepare("
                    UPDATE player_columns
                    SET is_won = 1
                    WHERE game_id   = :game_id
                      AND player_id = :player_id
                      AND column_number = :col_num
                ");
                $stmtWon->execute([
                    ':game_id'   => $game_id,
                    ':player_id' => $player_id,
                    ':col_num'   => $column_number
                ]);

                $messages[] = "Column $column_number is completed and now won by you!";
            } else {
                // Partial progress
                $messages[] = "Column $column_number progress increased to $finalProgress.";
            }
        }
    }

    // 4) Clear turn_markers for this player
    $stmt = $db->prepare("
        DELETE FROM turn_markers
        WHERE game_id   = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id
    ]);

    // 5) Switch turn to the other player
    $stmt = $db->prepare("
        UPDATE games
        SET current_turn_player = CASE
            WHEN current_turn_player = player1_id THEN player2_id
            ELSE player1_id
        END
        WHERE id = :game_id
    ");
    $stmt->execute([':game_id' => $game_id]);

    // 6) Build final message
    $joinedMessages = empty($messages)
        ? "Turn ended. Your progress is locked in, and it's now the other player's turn."
        : implode(' ', $messages) . " Turn ended. Your progress is locked in, and it's now the other player's turn.";

    // Return success
    echo json_encode([
        'status'  => 'success',
        'message' => $joinedMessages
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
