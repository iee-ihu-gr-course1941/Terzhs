<?php
// roll_dice.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$game_id = $_POST['game_id'] ?? null;
$token   = $_POST['token']   ?? null;

// Validate input
if (!$game_id || !$token) {
    echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required.']);
    exit;
}

try {
    // 1) Fetch the player and game details
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
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid game ID or player token.']);
        exit;
    }

    $player_id            = $result['player_id'];
    $current_turn_player  = $result['current_turn_player'];
    $game_status          = $result['status'];
    $player1_id           = $result['player1_id'];
    $player2_id           = $result['player2_id'];

    // 2) Validate the game status and turn
    if ($game_status !== 'in_progress') {
        echo json_encode(['status' => 'error', 'message' => 'The game is not currently in progress.']);
        exit;
    }
    if ($player_id != $current_turn_player) {
        echo json_encode(['status' => 'error', 'message' => 'It is not your turn.']);
        exit;
    }

    // 3) Check if the player already rolled but hasn't advanced yet
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

    // 5) Generate all possible pairs
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

    // 6) Check valid pairs
    $valid_pairs = [];
    foreach ($pairs as $index => $pair) {
        $a = $pair['a'];
        $b = $pair['b'];

        $stmt = $db->prepare("
            SELECT column_number
            FROM columns
            WHERE column_number IN (:a, :b)
        ");
        $stmt->bindValue(':a', $a, PDO::PARAM_INT);
        $stmt->bindValue(':b', $b, PDO::PARAM_INT);
        $stmt->execute();

        $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($found)) {
            $valid_pairs[$index + 1] = ['a' => $a, 'b' => $b];
        }
    }

    // 7) Insert a new roll (has_rolled=1)
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
            :pair_1a, :pair_1b,
            :pair_2a, :pair_2b,
            :pair_3a, :pair_3b,
            1,
            NOW()
        )
    ");
    $stmt->execute([
        ':game_id'   => $game_id,
        ':player_id' => $player_id,
        ':pair_1a'   => $pairs[0]['a'],
        ':pair_1b'   => $pairs[0]['b'],
        ':pair_2a'   => $pairs[1]['a'],
        ':pair_2b'   => $pairs[1]['b'],
        ':pair_3a'   => $pairs[2]['a'],
        ':pair_3b'   => $pairs[2]['b']
    ]);

    // 8) If there are NO valid moves -> bust + auto switch
    if (empty($valid_pairs)) {
        // Remove any temporary progress in turn_markers
        $stmt = $db->prepare("
            DELETE FROM turn_markers
            WHERE game_id = :game_id
              AND player_id = :player_id
        ");
        $stmt->execute([
            ':game_id'   => $game_id,
            ':player_id' => $player_id
        ]);

        // Switch turn to the other player
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
            'message' => 'Bust! No valid moves found. Turn passes to the other player.',
            'dice'    => $dice,
            'pairs'   => $pairs
        ]);
        exit;
    }

    // 9) If valid pairs exist, return success
    echo json_encode([
        'status'         => 'success',
        'message'        => 'Dice rolled successfully. Choose a pair to advance your marker(s).',
        'dice'           => [
            'Die 1' => $dice[0],
            'Die 2' => $dice[1],
            'Die 3' => $dice[2],
            'Die 4' => $dice[3]
        ],
        'possible_pairs' => $valid_pairs
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
