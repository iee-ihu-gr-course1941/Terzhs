<?php
// roll_dice.php
require 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;

    // Validate required inputs
    if (!$game_id || !$token) {
        echo json_encode(['status' => 'error', 'message' => 'Game ID and player token are required']);
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

        // Enforce "one roll, one advance" rule
        if ($has_rolled && $active_markers > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You must advance your markers before rolling again.']);
            exit;
        }

        // Roll 4 dice
        $dice = [
            random_int(1, 6), 
            random_int(1, 6), 
            random_int(1, 6), 
            random_int(1, 6)
        ];

        // Generate pairs
        $pairs = [
            ['a' => $dice[0] + $dice[1], 'b' => $dice[2] + $dice[3]],
            ['a' => $dice[0] + $dice[2], 'b' => $dice[1] + $dice[3]],
            ['a' => $dice[0] + $dice[3], 'b' => $dice[1] + $dice[2]]
        ];

        // Check if any pair is valid for advancement
        $valid_pairs = [];
        foreach ($pairs as $index => $pair) {
            foreach ([$pair['a'], $pair['b']] as $column_number) {
                $stmt = $db->prepare("SELECT 1 FROM columns WHERE column_number = :column_number");
                $stmt->execute([':column_number' => $column_number]);
                if ($stmt->fetch()) {
                    $valid_pairs[$index + 1] = $pair;
                    break;
                }
            }
        }

        if (empty($valid_pairs)) {
            // No valid moves, end the turn
            $stmt = $db->prepare("UPDATE dice_rolls SET has_rolled = 0 WHERE game_id = :game_id AND player_id = :player_id");
            $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id]);

            // Find the next player
            $stmt = $db->prepare("
                SELECT player_id 
                FROM game_players 
                WHERE game_id = :game_id AND player_id != :current_player_id 
                ORDER BY player_id LIMIT 1
            ");
            $stmt->execute([':game_id' => $game_id, ':current_player_id' => $player_id]);
            $next_player = $stmt->fetchColumn();

            if ($next_player) {
                $stmt = $db->prepare("
                    UPDATE games 
                    SET current_turn_player = :next_player 
                    WHERE id = :game_id
                ");
                $stmt->execute([':next_player' => $next_player, ':game_id' => $game_id]);
                echo json_encode([
                    'status' => 'info',
                    'message' => 'No valid advancements possible. Turn passed to the next player.',
                    'next_player' => $next_player
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Unable to determine the next player']);
            }
            exit;
        }

        // Save the roll in the dice_rolls table
        $stmt = $db->prepare("
            INSERT INTO dice_rolls (game_id, player_id, pair_1a, pair_1b, pair_2a, pair_2b, pair_3a, pair_3b, has_rolled)
            VALUES (:game_id, :player_id, :pair_1a, :pair_1b, :pair_2a, :pair_2b, :pair_3a, :pair_3b, 1)
            ON DUPLICATE KEY UPDATE 
                pair_1a = VALUES(pair_1a), pair_1b = VALUES(pair_1b),
                pair_2a = VALUES(pair_2a), pair_2b = VALUES(pair_2b),
                pair_3a = VALUES(pair_3a), pair_3b = VALUES(pair_3b),
                has_rolled = 1
        ");
        $stmt->execute([
            ':game_id' => $game_id,
            ':player_id' => $player_id,
            ':pair_1a' => $pairs[0]['a'],
            ':pair_1b' => $pairs[0]['b'],
            ':pair_2a' => $pairs[1]['a'],
            ':pair_2b' => $pairs[1]['b'],
            ':pair_3a' => $pairs[2]['a'],
            ':pair_3b' => $pairs[2]['b']
        ]);

        // Return the dice roll options
        echo json_encode([
            'status' => 'success',
            'dice' => $dice,
            'possible_pairs' => $valid_pairs
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        // Handle exceptions
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
