-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.44.242.70
-- Generation Time: Jul 17, 2026 at 07:13 AM
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
-- Table structure for table `hsp_schemes`
--

CREATE TABLE `hsp_schemes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `name` varchar(120) NOT NULL,
  `dashboard_url` varchar(255) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `hsp_schemes`
--

INSERT INTO `hsp_schemes` (`id`, `code`, `name`, `dashboard_url`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'naru', 'Naru Nangal Irrigation Scheme', 'https://hsp.jeps.co.in/dashboard/naru', 1, 1, '2026-06-29 10:13:37'),
(2, 'gog', 'Gogron', 'https://hsp.jeps.co.in/dashboard/gog', 2, 1, '2026-06-29 10:13:37'),
(3, 'gongo', 'Gauguwal', 'https://hsp.jeps.co.in/dashboard/gongo', 3, 1, '2026-06-29 10:13:37'),
(4, 'baddi', 'Bhaddi Lift Irrigation Scheme', 'https://hsp.jeps.co.in/dashboard/baddi', 4, 1, '2026-06-29 10:13:37'),
(5, 'poje', 'Pojewal Lift Irrigation Scheme', 'https://hsp.jeps.co.in/dashboard/poje', 5, 1, '2026-06-29 10:13:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hsp_schemes`
--
ALTER TABLE `hsp_schemes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_scheme_code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hsp_schemes`
--
ALTER TABLE `hsp_schemes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
