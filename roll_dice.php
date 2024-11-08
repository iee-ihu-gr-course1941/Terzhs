<?php
// roll_dice.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'] ?? null;
    $token = $_POST['token'] ?? null;

    $stmt = $db->prepare("SELECT id FROM players WHERE player_token = :token");
    $stmt->execute([':token' => $token]);
    $player = $stmt->fetch();

    if ($player) {
        // Generate dice rolls
        $roll1 = random_int(1, 6);
        $roll2 = random_int(1, 6);
        $dice_pairs = [$roll1 + $roll2, $roll1 - $roll2, $roll1 * $roll2];  // Customize as needed

        echo json_encode(['status' => 'success', 'dice_pairs' => $dice_pairs]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid player token']);
    }
}
?>
