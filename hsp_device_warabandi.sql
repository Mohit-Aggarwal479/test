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
-- Table structure for table `hsp_device_warabandi`
--

CREATE TABLE `hsp_device_warabandi` (
  `id` int(10) UNSIGNED NOT NULL,
  `device_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '1=Mon .. 7=Sun (ISO, matches PHP date(N))',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `hsp_device_warabandi`
--

INSERT INTO `hsp_device_warabandi` (`id`, `device_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 20, 1, '12:19:00', '13:19:00'),
(2, 20, 1, '14:19:00', '14:22:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hsp_device_warabandi`
--
ALTER TABLE `hsp_device_warabandi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_war_device` (`device_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hsp_device_warabandi`
--
ALTER TABLE `hsp_device_warabandi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hsp_device_warabandi`
--
ALTER TABLE `hsp_device_warabandi`
  ADD CONSTRAINT `fk_war_device` FOREIGN KEY (`device_id`) REFERENCES `hsp_devices` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
