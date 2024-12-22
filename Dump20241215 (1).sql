-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: cant_stop_game
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.6-MariaDB-0+deb12u1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `columns`
--

DROP TABLE IF EXISTS `columns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `columns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `column_number` int(11) NOT NULL,
  `max_height` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `column_number` (`column_number`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `columns`
--

LOCK TABLES `columns` WRITE;
/*!40000 ALTER TABLE `columns` DISABLE KEYS */;
INSERT INTO `columns` VALUES (1,2,3),(2,3,5),(3,4,7),(4,5,9),(5,6,11),(6,7,13),(7,8,11),(8,9,9),(9,10,7),(10,11,5),(11,12,3);
/*!40000 ALTER TABLE `columns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dice_rolls`
--

DROP TABLE IF EXISTS `dice_rolls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dice_rolls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `pair_1a` int(11) NOT NULL,
  `pair_1b` int(11) NOT NULL,
  `pair_2a` int(11) NOT NULL,
  `pair_2b` int(11) NOT NULL,
  `pair_3a` int(11) NOT NULL,
  `pair_3b` int(11) NOT NULL,
  `roll_time` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `dice_rolls_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dice_rolls_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dice_rolls`
--

LOCK TABLES `dice_rolls` WRITE;
/*!40000 ALTER TABLE `dice_rolls` DISABLE KEYS */;
INSERT INTO `dice_rolls` VALUES (1,4,1,10,6,9,7,9,7,'2024-12-14 13:25:50'),(2,4,1,9,5,5,9,6,8,'2024-12-14 13:50:05'),(3,4,1,11,8,9,10,9,10,'2024-12-14 13:50:12'),(4,4,1,7,4,8,3,8,3,'2024-12-14 13:50:17'),(5,4,1,10,9,10,9,7,12,'2024-12-14 13:50:22'),(6,4,1,10,3,7,6,8,5,'2024-12-14 13:50:32'),(7,4,1,4,11,7,8,8,7,'2024-12-14 17:53:58'),(8,6,1,6,8,7,7,7,7,'2024-12-14 18:53:22'),(9,6,1,6,5,5,6,2,9,'2024-12-15 01:17:41'),(10,6,1,6,5,3,8,6,5,'2024-12-15 01:18:04'),(11,6,1,7,12,10,9,10,9,'2024-12-15 01:18:08'),(12,6,1,7,8,5,10,7,8,'2024-12-15 01:18:12');
/*!40000 ALTER TABLE `dice_rolls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `games`
--

DROP TABLE IF EXISTS `games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player1_id` int(11) NOT NULL,
  `player2_id` int(11) DEFAULT NULL,
  `current_turn_player` int(11) NOT NULL,
  `status` enum('waiting','in_progress','completed','ended') DEFAULT 'waiting',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `winner_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player1_id` (`player1_id`),
  KEY `player2_id` (`player2_id`),
  KEY `winner_id` (`winner_id`),
  CONSTRAINT `games_ibfk_1` FOREIGN KEY (`player1_id`) REFERENCES `players` (`id`),
  CONSTRAINT `games_ibfk_2` FOREIGN KEY (`player2_id`) REFERENCES `players` (`id`),
  CONSTRAINT `games_ibfk_3` FOREIGN KEY (`winner_id`) REFERENCES `players` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `games`
--

LOCK TABLES `games` WRITE;
/*!40000 ALTER TABLE `games` DISABLE KEYS */;
INSERT INTO `games` VALUES (1,1,2,2,'completed','2024-11-10 13:00:52','2024-11-10 14:39:36',2),(2,3,4,3,'ended','2024-11-10 15:17:28','2024-12-07 17:48:29',NULL),(3,1,2,1,'ended','2024-12-07 17:54:33','2024-12-07 18:43:20',NULL),(4,1,2,1,'ended','2024-12-14 13:20:55','2024-12-14 18:29:18',NULL),(5,2,2,2,'in_progress','2024-12-14 13:21:18','2024-12-14 18:32:31',NULL),(6,1,2,1,'ended','2024-12-14 18:34:03','2024-12-15 01:29:03',NULL),(7,1,2,1,'in_progress','2024-12-15 13:44:37','2024-12-15 13:44:43',NULL),(8,1,2,1,'in_progress','2024-12-15 14:55:21','2024-12-15 14:56:55',NULL);
/*!40000 ALTER TABLE `games` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `player_columns`
--

DROP TABLE IF EXISTS `player_columns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_columns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `column_number` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 0,
  `is_won` tinyint(1) DEFAULT 0,
  `has_rolled` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_id` (`game_id`,`player_id`,`column_number`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `player_columns_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `player_columns_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `player_columns`
--

LOCK TABLES `player_columns` WRITE;
/*!40000 ALTER TABLE `player_columns` DISABLE KEYS */;
INSERT INTO `player_columns` VALUES (1,1,2,8,13,0,1,0),(14,1,2,2,3,0,1,0),(17,1,2,12,3,0,1,0),(20,2,3,12,3,0,1,0),(21,2,3,3,5,0,1,0),(25,2,3,5,2,1,0,0),(30,2,3,11,5,0,1,0),(35,2,3,6,11,0,1,0),(46,2,3,7,3,1,0,0),(49,3,1,2,3,0,1,0),(52,3,1,12,3,0,1,0),(55,3,1,3,5,0,1,0),(60,4,1,6,11,0,1,0),(61,4,1,10,7,0,1,0),(78,4,1,3,5,0,1,0),(83,4,1,8,1,1,0,0),(84,4,1,4,1,1,0,0),(85,6,1,7,13,0,1,0),(86,6,1,8,11,0,1,0),(109,6,1,5,7,1,0,0),(110,6,1,10,7,0,1,0);
/*!40000 ALTER TABLE `player_columns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `players`
--

DROP TABLE IF EXISTS `players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `player_token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `player_token` (`player_token`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `players`
--

LOCK TABLES `players` WRITE;
/*!40000 ALTER TABLE `players` DISABLE KEYS */;
INSERT INTO `players` VALUES (1,'John','e968767e1de0605e53c97799f067dcb1','2024-11-09 14:47:18'),(2,'Mike','c4b0478f179b13c86ba67abc6d92ce4a','2024-11-09 14:47:38'),(3,'Mike','43783ab43953a88baf51f42e5631c523','2024-11-09 15:25:01'),(4,'John','064e360f681c5c45d8daee31f7a76b49','2024-11-09 15:25:09');
/*!40000 ALTER TABLE `players` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-12-15 19:10:16
