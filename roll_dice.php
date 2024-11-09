<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = $_POST['game_id'];
    $token = $_POST['token'];

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        $dice = [
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6)
        ];

        $pairs = [
            ["pair_1" => $dice[0] + $dice[1], "pair_2" => $dice[2] + $dice[3]],
            ["pair_1" => $dice[0] + $dice[2], "pair_2" => $dice[1] + $dice[3]],
            ["pair_1" => $dice[0] + $dice[3], "pair_2" => $dice[1] + $dice[2]]
        ];

        echo json_encode(['status' => 'success', 'dice_rolls' => $dice, 'possible_pairs' => $pairs]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
