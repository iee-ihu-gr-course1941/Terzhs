<?php
// advance.php
require 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;
$option  = $_POST['option']  ?? null; // 1 => pair_1, 2 => pair_2, 3 => pair_3

// Validate input
if (!$game_id || !$token || !$option) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID, token, and option are required.']);
    exit;
}

try {
    // 1) Fetch player & game
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

    if (!$playerData) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid game or token.']);
        exit;
    }

    $player_id           = $playerData['player_id'];
    $current_turn_player = $playerData['current_turn_player'];
    $game_status         = $playerData['status'];

    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'Game is not in progress.']);
        exit;
    }
    if ($player_id != $current_turn_player) {
        echo json_encode(['status' => 'error', 'message' => 'Not your turn.']);
        exit;
    }

    // 2) Fetch latest dice roll with has_rolled=1
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
    $roll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roll) {
        echo json_encode(['status' => 'error', 'message' => 'No dice roll found. Please roll first.']);
        exit;
    }
    if ($roll['has_rolled'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'You must roll again before advancing.']);
        exit;
    }

    // 3) Determine which pair
    $optionMap = [
        1 => [$roll['pair_1a'], $roll['pair_1b']],
        2 => [$roll['pair_2a'], $roll['pair_2b']],
        3 => [$roll['pair_3a'], $roll['pair_3b']]
    ];

    if (!isset($optionMap[$option])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid option.']);
        exit;
    }

    $selectedPair = $optionMap[$option];
    $messages = [];
    $didAdvance = false;

    foreach ($selectedPair as $colNum) {
        // Check if column exists
        $stmtC = $db->prepare("SELECT max_height FROM columns WHERE column_number = :col");
        $stmtC->execute([':col' => $colNum]);
        $colInfo = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$colInfo) {
            $messages[] = "Column $colNum does not exist.";
            continue;
        }

        // Enforce up to 3 distinct columns
        $stmtCount = $db->prepare("
            SELECT COUNT(DISTINCT column_number)
            FROM turn_markers
            WHERE game_id = :game_id
              AND player_id = :player_id
        ");
        $stmtCount->execute([':game_id' => $game_id, ':player_id' => $player_id]);
        $distinctCount = $stmtCount->fetchColumn();

        // Check if colNum is already among the player's turn_markers
        $stmtCheck = $db->prepare("
            SELECT 1
            FROM turn_markers
            WHERE game_id = :game_id
              AND player_id = :player_id
              AND column_number = :col
        ");
        $stmtCheck->execute([
            ':game_id'   => $game_id,
            ':player_id' => $player_id,
            ':col'       => $colNum
        ]);
        $alreadyUsed = $stmtCheck->fetchColumn();

        if ($distinctCount >= 3 && !$alreadyUsed) {
            $messages[] = "Cannot add a 4th distinct column ($colNum). Skipped.";
            continue;
        }

        // Insert or update turn_markers
        $stmtIns = $db->prepare("
            INSERT INTO turn_markers (game_id, player_id, column_number, temp_progress)
            VALUES (:game_id, :player_id, :col_num, 1)
            ON DUPLICATE KEY UPDATE temp_progress = temp_progress + 1
        ");
        $stmtIns->execute([
            ':game_id'   => $game_id,
            ':player_id' => $player_id,
            ':col_num'   => $colNum
        ]);

        $didAdvance = true;
        $messages[] = "Advanced temporary marker on column $colNum (not yet permanent).";
    }

    // 4) If at least one column advanced, set has_rolled=0
    if ($didAdvance) {
        $stmt = $db->prepare("
            UPDATE dice_rolls
            SET has_rolled = 0
            WHERE game_id = :game_id
              AND player_id = :player_id
            ORDER BY roll_time DESC
            LIMIT 1
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    } else {
        // No columns advanced
        echo json_encode([
            'status'  => 'error',
            'message' => implode(' ', $messages) ?: 'No valid columns advanced.'
        ]);
        exit;
    }

    echo json_encode([
        'status'  => 'success',
        'message' => implode(' ', $messages)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
