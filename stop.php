<?php
// stop.php
require 'db_connect.php';

// We'll return JSON responses
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;

if (!$game_id || !$token) {
    echo json_encode([
        'status' => 'error',
        'message'=> 'Game ID and player token are required.'
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
            'status' => 'error',
            'message'=> 'Invalid player token.'
        ]);
        exit;
    }
    $player_id = $player['id'];

    // 2) Check the game status and whose turn it is
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
            'status' => 'error',
            'message'=> 'Invalid game ID.'
        ]);
        exit;
    }
    if ($game['status'] !== 'in_progress') {
        echo json_encode([
            'status' => 'error',
            'message'=> 'Game is not in progress.'
        ]);
        exit;
    }
    if ($game['current_turn_player'] != $player_id) {
        echo json_encode([
            'status' => 'error',
            'message'=> 'It is not your turn.'
        ]);
        exit;
    }

    // We'll collect partial progress messages here
    $messages = [];

    // 3) Merge any temp progress from turn_markers into player_columns
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

            // Insert or update permanent progress
            $stmtMerge = $db->prepare("
                INSERT INTO player_columns (
                    game_id,
                    player_id,
                    column_number,
                    progress
                ) VALUES (
                    :game_id,
                    :player_id,
                    :col_num,
                    :temp_progress
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

            // Check how much progress the player now has
            $stmtCheck = $db->prepare("
                SELECT pc.progress, c.max_height
                FROM player_columns pc
                JOIN columns c ON c.column_number = pc.column_number
                WHERE pc.game_id   = :game_id
                  AND pc.player_id = :player_id
                  AND pc.column_number = :col_num
            ");
            $stmtCheck->execute([
                ':game_id'   => $game_id,
                ':player_id' => $player_id,
                ':col_num'   => $column_number
            ]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $currentProgress = (int)$row['progress'];
                $maxHeight       = (int)$row['max_height'];

                if ($currentProgress >= $maxHeight) {
                    // Mark column as won
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

                    // Partial message that the column was completed
                    $messages[] = "Column $column_number is completed and now won by you!";
                } else {
                    // Not completed, so partial progress
                    $messages[] = "Column $column_number progress increased to $currentProgress.";
                }
            }
        }
    }

    // 4) Clear turn_markers for this player
    $stmt = $db->prepare("
        DELETE FROM turn_markers
        WHERE game_id = :game_id
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
