-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.44.242.70
-- Generation Time: Jul 17, 2026 at 07:10 AM
-- Server version: 11.7.2-MariaDB-ubu2204
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wrdp_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `hsp_alerts`
--

CREATE TABLE `hsp_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` int(10) UNSIGNED NOT NULL,
  `type` enum('OFFLINE','STALE','LOW_BATTERY','SIM_EXPIRING','SIM_EXPIRED') NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'warning',
  `message` varchar(255) DEFAULT NULL,
  `value_text` varchar(64) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hsp_alerts`
--
ALTER TABLE `hsp_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alert_active` (`device_id`,`type`),
  ADD KEY `idx_alert_sev` (`severity`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hsp_alerts`
--
ALTER TABLE `hsp_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hsp_alerts`
--
ALTER TABLE `hsp_alerts`
  ADD CONSTRAINT `hsp_fk_alert_device` FOREIGN KEY (`device_id`) REFERENCES `hsp_devices` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
