<?php
// roll_dice.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;

    // Get player ID using the token
    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        // Roll 4 dice
        $dice = [
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6)
        ];

        // Generate possible pairs
        $pairs = [
            [
                "pair_1" => $dice[0] + $dice[1],
                "pair_2" => $dice[2] + $dice[3],
                "description" => "Pair 1: Dice 1 + Dice 2, Pair 2: Dice 3 + Dice 4"
            ],
            [
                "pair_1" => $dice[0] + $dice[2],
                "pair_2" => $dice[1] + $dice[3],
                "description" => "Pair 1: Dice 1 + Dice 3, Pair 2: Dice 2 + Dice 4"
            ],
            [
                "pair_1" => $dice[0] + $dice[3],
                "pair_2" => $dice[1] + $dice[2],
                "description" => "Pair 1: Dice 1 + Dice 4, Pair 2: Dice 2 + Dice 3"
            ]
        ];

        // JSON response with improved structure and descriptions
        echo json_encode([
            'status' => 'success',
            'dice_rolls' => [
                'dice_1' => $dice[0],
                'dice_2' => $dice[1],
                'dice_3' => $dice[2],
                'dice_4' => $dice[3]
            ],
            'possible_pairs' => $pairs
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
