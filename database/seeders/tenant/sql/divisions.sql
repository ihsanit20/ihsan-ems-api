-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table lib_client.divisions
CREATE TABLE IF NOT EXISTS `divisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `en_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lib_client.divisions: ~8 rows (approximately)
INSERT INTO `divisions` (`id`, `name`, `en_name`, `url`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'চট্টগ্রাম', 'Chattagram', 'www.chittagongdiv.gov.bd', NULL, NULL, NULL),
	(2, 'রাজশাহী', 'Rajshahi', 'www.rajshahidiv.gov.bd', NULL, NULL, NULL),
	(3, 'খুলনা', 'Khulna', 'www.khulnadiv.gov.bd', NULL, NULL, NULL),
	(4, 'বরিশাল', 'Barisal', 'www.barisaldiv.gov.bd', NULL, NULL, NULL),
	(5, 'সিলেট', 'Sylhet', 'www.sylhetdiv.gov.bd', NULL, NULL, NULL),
	(6, 'ঢাকা', 'Dhaka', 'www.dhakadiv.gov.bd', NULL, NULL, NULL),
	(7, 'রংপুর', 'Rangpur', 'www.rangpurdiv.gov.bd', NULL, NULL, NULL),
	(8, 'ময়মনসিংহ', 'Mymensingh', 'www.mymensinghdiv.gov.bd', NULL, NULL, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
