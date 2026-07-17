-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.44.242.70
-- Generation Time: Jul 17, 2026 at 07:12 AM
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
-- Table structure for table `hsp_readings`
--

CREATE TABLE `hsp_readings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` int(10) UNSIGNED NOT NULL,
  `device_code` varchar(32) NOT NULL,
  `water_status` varchar(32) DEFAULT NULL,
  `has_water` tinyint(1) DEFAULT NULL,
  `battery_voltage` decimal(5,2) DEFAULT NULL,
  `site_name` varchar(120) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `sim_im_no` varchar(32) DEFAULT NULL,
  `sim_validity_upto` date DEFAULT NULL,
  `reported_at` datetime DEFAULT NULL,
  `fetched_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_online` tinyint(1) NOT NULL DEFAULT 1,
  `http_status` smallint(6) DEFAULT NULL,
  `raw_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hsp_readings`
--
ALTER TABLE `hsp_readings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reading` (`device_id`,`reported_at`),
  ADD KEY `idx_code_time` (`device_code`,`fetched_at`),
  ADD KEY `idx_read_dev_fetch` (`device_id`,`fetched_at`),
  ADD KEY `idx_read_reported` (`reported_at`),
  ADD KEY `idx_read_fetched` (`fetched_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hsp_readings`
--
ALTER TABLE `hsp_readings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hsp_readings`
--
ALTER TABLE `hsp_readings`
  ADD CONSTRAINT `hsp_fk_read_device` FOREIGN KEY (`device_id`) REFERENCES `hsp_devices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
