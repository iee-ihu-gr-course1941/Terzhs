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
            [$dice[0] + $dice[1], $dice[2] + $dice[3]],
            [$dice[0] + $dice[2], $dice[1] + $dice[3]],
            [$dice[0] + $dice[3], $dice[1] + $dice[2]]
        ];

        echo json_encode([
            'status' => 'success',
            'dice' => $dice,       // Show individual dice values
            'pairs' => $pairs      // Show the three possible pairs of sums
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
