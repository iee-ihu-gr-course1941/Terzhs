<?php
// roll_dice.php
require 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;

// Validate required inputs
if (!$game_id || !$token) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required.']);
    exit;
}

try {
    // 1) Fetch player + game info
    $stmt = $db->prepare("
        SELECT 
            p.id AS player_id,
            g.current_turn_player,
            g.status,
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

    // 2) Check game status + turn
    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'Game is not in progress.']);
        exit;
    }
    if ($player_id != $current_turn_player) {
        echo json_encode(['status' => 'error', 'message' => 'It is not your turn.']);
        exit;
    }

    // 3) Check if there's already a pending roll
    $stmt = $db->prepare("
        SELECT has_rolled
        FROM dice_rolls
        WHERE game_id = :game_id
          AND player_id = :player_id
        ORDER BY roll_time DESC
        LIMIT 1
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $lastRoll = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastRoll && $lastRoll['has_rolled'] == 1) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'You have already rolled this turn. Please advance a marker before rolling again.'
        ]);
        exit;
    }

    // 4) Roll 4 dice
    $dice = [
        random_int(1, 6),
        random_int(1, 6),
        random_int(1, 6),
        random_int(1, 6)
    ];

    // 5) Generate 3 possible pairs
    $pairs = [
        [
            'a' => $dice[0] + $dice[1],
            'b' => $dice[2] + $dice[3]
        ],
        [
            'a' => $dice[0] + $dice[2],
            'b' => $dice[1] + $dice[3]
        ],
        [
            'a' => $dice[0] + $dice[3],
            'b' => $dice[1] + $dice[2]
        ]
    ];

    // 6) Fetch existing turn_markers for this player
    $stmt = $db->prepare("
        SELECT column_number
        FROM turn_markers
        WHERE game_id = :game_id
          AND player_id = :player_id
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $turnMarkers = $stmt->fetchAll(PDO::FETCH_COLUMN); // just the column_numbers
    $markerMap = array_flip($turnMarkers);
    $distinctCount = count($turnMarkers);

    // 7) Find columns that are won or maxed out for this player
    $stmt = $db->prepare("
        SELECT pc.column_number
        FROM player_columns pc
        JOIN columns c ON c.column_number = pc.column_number
        WHERE pc.game_id = :game_id
          AND pc.player_id = :player_id
          AND (pc.is_won = 1 OR pc.progress >= c.max_height)
    ");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);
    $wonColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $wonSet = array_flip($wonColumns);

    // Helper to see if a sum is placeable
    $canPlace = function($sum) use ($distinctCount, $markerMap, $wonSet, $db, $game_id, $player_id) {
        // Must exist in columns
        $stmtCol = $db->prepare("SELECT 1 FROM columns WHERE column_number = :col");
        $stmtCol->execute([':col' => $sum]);
        if (!$stmtCol->fetchColumn()) return false; // doesn't exist

        // Must not be already won or maxed
        if (isset($wonSet[$sum])) return false;

        // If 3 distinct columns already in turn_markers, can only place if $sum is among them
        if ($distinctCount >= 3 && !isset($markerMap[$sum])) {
            return false;
        }
        return true;
    };

    // 8) Evaluate each pair
    $pairsOutput = [];
    $foundValid = false;
    foreach ($pairs as $index => $p) {
        $a = $p['a'];
        $b = $p['b'];
        $validA = $canPlace($a);
        $validB = $canPlace($b);
        $isValid = ($validA || $validB);

        if ($isValid) {
            $foundValid = true;
        }

        $pairsOutput[] = [
            'option' => $index + 1,
            'a'      => $a,
            'b'      => $b,
            'valid'  => $isValid ? 'valid' : 'invalid'
        ];
    }

    // 9) Insert a new roll with has_rolled=1
    $stmt = $db->prepare("
        INSERT INTO dice_rolls (
            game_id,
            player_id,
            pair_1a, pair_1b,
            pair_2a, pair_2b,
            pair_3a, pair_3b,
            has_rolled,
            roll_time
        ) VALUES (
            :game_id,
            :player_id,
            :p1a, :p1b,
            :p2a, :p2b,
            :p3a, :p3b,
            1,
            NOW()
        )
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id,
        ':p1a'       => $pairs[0]['a'],
        ':p1b'       => $pairs[0]['b'],
        ':p2a'       => $pairs[1]['a'],
        ':p2b'       => $pairs[1]['b'],
        ':p3a'       => $pairs[2]['a'],
        ':p3b'       => $pairs[2]['b']
    ]);

    // 10) If no valid pairs => bust
    if (!$foundValid) {
        // Remove partial progress from turn_markers
        $stmt = $db->prepare("
            DELETE FROM turn_markers
            WHERE game_id = :game_id
              AND player_id = :player_id
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

        // Reset has_rolled so next turn is not blocked
        $stmt = $db->prepare("
            UPDATE dice_rolls
            SET has_rolled = 0
            WHERE game_id = :game_id
              AND player_id = :player_id
            ORDER BY roll_time DESC
            LIMIT 1
        ");
        $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

        // Switch turn
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
            'status'  => 'info',
            'message' => 'Bust! No valid columns available. Your turn progress is lost.',
            'dice'    => $dice,
            'pairs'   => $pairsOutput
        ]);
        exit;
    }

    // 11) Otherwise, at least one valid pair
    echo json_encode([
        'status'  => 'success',
        'message' => 'Dice rolled successfully. Choose a pair to advance.',
        'dice'    => [
            'Die1' => $dice[0],
            'Die2' => $dice[1],
            'Die3' => $dice[2],
            'Die4' => $dice[3]
        ],
        'pairs' => $pairsOutput
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
