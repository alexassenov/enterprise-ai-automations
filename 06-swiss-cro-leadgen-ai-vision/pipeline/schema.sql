-- MySQL dump 10.13  Distrib 8.0.46, for Linux (aarch64)
--
-- Host: localhost    Database: joschua_leads
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `website_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `reviews_count` int DEFAULT '0',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Google Ads / Serp',
  `pagespeed_score` int DEFAULT NULL,
  `fcp` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lcp` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technical_issues` text COLLATE utf8mb4_unicode_ci,
  `ai_ux_flaws` text COLLATE utf8mb4_unicode_ci,
  `ai_ux_flaws_en` text COLLATE utf8mb4_unicode_ci,
  `generated_pitch` text COLLATE utf8mb4_unicode_ci,
  `generated_pitch_en` text COLLATE utf8mb4_unicode_ci,
  `screenshot_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desktop_screenshot_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('new','reviewed','contacted') COLLATE utf8mb4_unicode_ci DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-26 19:22:12
