<?php
// turn.php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'];
    $player_id = $_POST['player_id'];
    $roll1 = random_int(1, 6);
    $roll2 = random_int(1, 6);

    $stmt = $db->prepare("INSERT INTO turns (game_id, player_id, roll1, roll2) VALUES (:game_id, :player_id, :roll1, :roll2)");
    $stmt->execute([':game_id' => $game_id, ':player_id' => $player_id, ':roll1' => $roll1, ':roll2' => $roll2]);
    
    echo json_encode(['roll1' => $roll1, 'roll2' => $roll2]);
}
?>
