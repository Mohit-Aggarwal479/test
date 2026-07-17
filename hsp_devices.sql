-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.44.242.70
-- Generation Time: Jul 17, 2026 at 07:11 AM
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
-- Table structure for table `hsp_devices`
--

CREATE TABLE `hsp_devices` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` varchar(16) DEFAULT NULL,
  `scheme_id` int(10) UNSIGNED NOT NULL,
  `device_code` varchar(32) NOT NULL,
  `tail_label` varchar(32) NOT NULL,
  `tail_no` tinyint(3) UNSIGNED NOT NULL,
  `api_endpoint` varchar(255) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `office_id` int(11) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hsp_devices`
--
ALTER TABLE `hsp_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_device_code` (`device_code`),
  ADD UNIQUE KEY `uq_scheme_tail` (`scheme_id`,`tail_no`),
  ADD UNIQUE KEY `uq_site_id` (`site_id`),
  ADD KEY `idx_dev_scheme` (`scheme_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hsp_devices`
--
ALTER TABLE `hsp_devices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hsp_devices`
--
ALTER TABLE `hsp_devices`
  ADD CONSTRAINT `hsp_fk_dev_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `hsp_schemes` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
