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

-- Dumping structure for table lib_client.districts
CREATE TABLE IF NOT EXISTS `districts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `division_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `en_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `districts_division_id_foreign` (`division_id`),
  CONSTRAINT `districts_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lib_client.districts: ~64 rows (approximately)
INSERT INTO `districts` (`id`, `division_id`, `name`, `en_name`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 1, 'কুমিল্লা', 'Comilla', NULL, NULL, NULL),
	(2, 1, 'ফেনী', 'Feni', NULL, NULL, NULL),
	(3, 1, 'ব্রাহ্মণবাড়িয়া', 'Brahmanbaria', NULL, NULL, NULL),
	(4, 1, 'রাঙ্গামাটি', 'Rangamati', NULL, NULL, NULL),
	(5, 1, 'নোয়াখালী', 'Noakhali', NULL, NULL, NULL),
	(6, 1, 'চাঁদপুর', 'Chandpur', NULL, NULL, NULL),
	(7, 1, 'লক্ষ্মীপুর', 'Lakshmipur', NULL, NULL, NULL),
	(8, 1, 'চট্টগ্রাম', 'Chattogram', NULL, NULL, NULL),
	(9, 1, 'কক্সবাজার', 'Coxsbazar', NULL, NULL, NULL),
	(10, 1, 'খাগড়াছড়ি', 'Khagrachhari', NULL, NULL, NULL),
	(11, 1, 'বান্দরবান', 'Bandarban', NULL, NULL, NULL),
	(12, 2, 'সিরাজগঞ্জ', 'Sirajganj', NULL, NULL, NULL),
	(13, 2, 'পাবনা', 'Pabna', NULL, NULL, NULL),
	(14, 2, 'বগুড়া', 'Bogura', NULL, NULL, NULL),
	(15, 2, 'রাজশাহী', 'Rajshahi', NULL, NULL, NULL),
	(16, 2, 'নাটোর', 'Natore', NULL, NULL, NULL),
	(17, 2, 'জয়পুরহাট', 'Joypurhat', NULL, NULL, NULL),
	(18, 2, 'চাঁপাইনবাবগঞ্জ', 'Chapainawabganj', NULL, NULL, NULL),
	(19, 2, 'নওগাঁ', 'Naogaon', NULL, NULL, NULL),
	(20, 3, 'যশোর', 'Jashore', NULL, NULL, NULL),
	(21, 3, 'সাতক্ষীরা', 'Satkhira', NULL, NULL, NULL),
	(22, 3, 'মেহেরপুর', 'Meherpur', NULL, NULL, NULL),
	(23, 3, 'নড়াইল', 'Narail', NULL, NULL, NULL),
	(24, 3, 'চুয়াডাঙ্গা', 'Chuadanga', NULL, NULL, NULL),
	(25, 3, 'কুষ্টিয়া', 'Kushtia', NULL, NULL, NULL),
	(26, 3, 'মাগুরা', 'Magura', NULL, NULL, NULL),
	(27, 3, 'খুলনা', 'Khulna', NULL, NULL, NULL),
	(28, 3, 'বাগেরহাট', 'Bagerhat', NULL, NULL, NULL),
	(29, 3, 'ঝিনাইদহ', 'Jhenaidah', NULL, NULL, NULL),
	(30, 4, 'ঝালকাঠি', 'Jhalakathi', NULL, NULL, NULL),
	(31, 4, 'পটুয়াখালী', 'Patuakhali', NULL, NULL, NULL),
	(32, 4, 'পিরোজপুর', 'Pirojpur', NULL, NULL, NULL),
	(33, 4, 'বরিশাল', 'Barisal', NULL, NULL, NULL),
	(34, 4, 'ভোলা', 'Bhola', NULL, NULL, NULL),
	(35, 4, 'বরগুনা', 'Barguna', NULL, NULL, NULL),
	(36, 5, 'সিলেট', 'Sylhet', NULL, NULL, NULL),
	(37, 5, 'মৌলভীবাজার', 'Moulvibazar', NULL, NULL, NULL),
	(38, 5, 'হবিগঞ্জ', 'Habiganj', NULL, NULL, NULL),
	(39, 5, 'সুনামগঞ্জ', 'Sunamganj', NULL, NULL, NULL),
	(40, 6, 'নরসিংদী', 'Narsingdi', NULL, NULL, NULL),
	(41, 6, 'গাজীপুর', 'Gazipur', NULL, NULL, NULL),
	(42, 6, 'শরীয়তপুর', 'Shariatpur', NULL, NULL, NULL),
	(43, 6, 'নারায়ণগঞ্জ', 'Narayanganj', NULL, NULL, NULL),
	(44, 6, 'টাঙ্গাইল', 'Tangail', NULL, NULL, NULL),
	(45, 6, 'কিশোরগঞ্জ', 'Kishoreganj', NULL, NULL, NULL),
	(46, 6, 'মানিকগঞ্জ', 'Manikganj', NULL, NULL, NULL),
	(47, 6, 'ঢাকা', 'Dhaka', NULL, NULL, NULL),
	(48, 6, 'মুন্সিগঞ্জ', 'Munshiganj', NULL, NULL, NULL),
	(49, 6, 'রাজবাড়ী', 'Rajbari', NULL, NULL, NULL),
	(50, 6, 'মাদারীপুর', 'Madaripur', NULL, NULL, NULL),
	(51, 6, 'গোপালগঞ্জ', 'Gopalganj', NULL, NULL, NULL),
	(52, 6, 'ফরিদপুর', 'Faridpur', NULL, NULL, NULL),
	(53, 7, 'পঞ্চগড়', 'Panchagarh', NULL, NULL, NULL),
	(54, 7, 'দিনাজপুর', 'Dinajpur', NULL, NULL, NULL),
	(55, 7, 'লালমনিরহাট', 'Lalmonirhat', NULL, NULL, NULL),
	(56, 7, 'নীলফামারী', 'Nilphamari', NULL, NULL, NULL),
	(57, 7, 'গাইবান্ধা', 'Gaibandha', NULL, NULL, NULL),
	(58, 7, 'ঠাকুরগাঁও', 'Thakurgaon', NULL, NULL, NULL),
	(59, 7, 'রংপুর', 'Rangpur', NULL, NULL, NULL),
	(60, 7, 'কুড়িগ্রাম', 'Kurigram', NULL, NULL, NULL),
	(61, 8, 'শেরপুর', 'Sherpur', NULL, NULL, NULL),
	(62, 8, 'ময়মনসিংহ', 'Mymensingh', NULL, NULL, NULL),
	(63, 8, 'জামালপুর', 'Jamalpur', NULL, NULL, NULL),
	(64, 8, 'নেত্রকোণা', 'Netrokona', NULL, NULL, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
