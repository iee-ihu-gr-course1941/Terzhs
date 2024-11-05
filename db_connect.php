<?php
// db_connect.php
try {
    $db = new PDO('mysql:unix_socket=/home/student/iee/2020/iee2020168/mysql/run/mysql.sock;dbname=cant_stop_game', 'iee2020168', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>
