<?php
// stop.php
require 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;

// Validate
if (!$game_id || !$token) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required.']);
    exit;
}

try {
    // 1) Find player & game
    $stmt = $db->prepare("
        SELECT 
            p.id AS player_id,
            g.status,
            g.current_turn_player,
            g.player1_id,
            g.player2_id
        FROM players p
        JOIN games g ON g.id = :game_id
        WHERE p.player_token = :token
    ");
    $stmt->execute([':game_id' => $game_id, ':token' => $token]);
    $gameData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gameData) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or token.']);
        exit;
    }

    $player_id           = $gameData['player_id'];
    $current_turn_player = $gameData['current_turn_player'];
    $game_status         = $gameData['status'];

    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'Game is not in progress.']);
        exit;
    }
    if ($player_id != $current_turn_player) {
        echo json_encode(['status' => 'error', 'message' => 'Not your turn.']);
        exit;
    }

    // 2) Merge from turn_markers to player_columns
    $stmt = $db->prepare("
        SELECT column_number, temp_progress
        FROM turn_markers
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $markers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    if ($markers) {
        foreach ($markers as $m) {
            $colNum       = $m['column_number'];
            $tempProgress = (int)$m['temp_progress'];

            // Merge into player_columns
            $stmtMerge = $db->prepare("
                INSERT INTO player_columns (game_id, player_id, column_number, progress, is_active)
                VALUES (:game_id, :player_id, :colNum, :tempProgress, 0)
                ON DUPLICATE KEY UPDATE 
                  progress = progress + VALUES(progress)
            ");
            $stmtMerge->execute([
                ':game_id'     => $game_id,
                ':player_id'   => $player_id,
                ':colNum'      => $colNum,
                ':tempProgress'=> $tempProgress
            ]);

            // Check if column is now won
            $stmtCheck = $db->prepare("
                SELECT pc.progress, c.max_height
                FROM player_columns pc
                JOIN columns c ON c.column_number = pc.column_number
                WHERE pc.game_id = :game_id
                  AND pc.player_id = :player_id
                  AND pc.column_number = :colNum
            ");
            $stmtCheck->execute([
                ':game_id'   => $game_id,
                ':player_id' => $player_id,
                ':colNum'    => $colNum
            ]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['progress'] >= $row['max_height']) {
                // Mark is_won=1
                $stmtWon = $db->prepare("
                    UPDATE player_columns
                    SET is_won = 1, is_active=0
                    WHERE game_id = :game_id
                      AND player_id = :player_id
                      AND column_number = :colNum
                ");
                $stmtWon->execute([
                    ':game_id'   => $game_id,
                    ':player_id' => $player_id,
                    ':colNum'    => $colNum
                ]);
                $messages[] = "Column $colNum is won!";
            } else {
                $messages[] = "Column $colNum progressed to {$row['progress']}.";
            }
        }
    }

    // 3) Clear turn_markers now
    $stmt = $db->prepare("
        DELETE FROM turn_markers
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

    // 4) Deactivate columns for this turn in player_columns
    $stmt = $db->prepare("
        UPDATE player_columns
        SET is_active = 0
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

    // 5) Check if this player has 3 columns won => game ends
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM player_columns
        WHERE game_id = :game_id
          AND player_id = :player_id
          AND is_won = 1
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $wonCount = $stmt->fetchColumn();

    if ($wonCount >= 3) {
        // Mark the game completed
        $stmtEnd = $db->prepare("
            UPDATE games
            SET status = 'completed',
                winner_id = :player_id
            WHERE id = :game_id
        ");
        $stmtEnd->execute([':player_id' => $player_id, ':game_id' => $game_id]);

        // Announce winner
        echo json_encode([
            'status'  => 'success',
            'message' => implode(' ', $messages) . " You have won the game! Congratulations!"
        ]);
        exit;
    }

    // 6) Switch turn if no winner yet
    $stmt = $db->prepare("
        UPDATE games
        SET current_turn_player = CASE 
            WHEN current_turn_player = player1_id THEN player2_id
            ELSE player1_id
        END
        WHERE id = :game_id
    ");
    $stmt->execute([':game_id' => $game_id]);

    echo json_encode([
        'status'  => 'success',
        'message' => implode(' ', $messages) . " Turn ended. Your progress is locked in!"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => "Stop error: " . $e->getMessage()
    ]);
}
?>
