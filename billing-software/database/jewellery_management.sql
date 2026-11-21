-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2026 at 12:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jewellery_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `bill_date` date DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `purity` varchar(20) DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,3) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `making_percent` decimal(5,2) DEFAULT NULL,
  `gold_value` decimal(12,2) DEFAULT NULL,
  `making_amount` decimal(12,2) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  `cgst` decimal(12,2) DEFAULT NULL,
  `sgst` decimal(12,2) DEFAULT NULL,
  `net_amount` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bill_type` enum('SALE','LABOUR') NOT NULL DEFAULT 'SALE',
  `status` varchar(20) DEFAULT 'ACTIVE',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `gst_no` varchar(100) DEFAULT NULL,
  `state_code` varchar(50) DEFAULT NULL,
  `pan_no` varchar(100) DEFAULT NULL,
  `delivery_option` varchar(50) NOT NULL DEFAULT 'At Shop',
  `gold_rate` decimal(12,2) DEFAULT NULL,
  `silver_rate` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `gold_credit` decimal(10,3) DEFAULT 0.000,
  `discount` decimal(12,2) DEFAULT 0.00,
  `cash_received` decimal(12,2) DEFAULT 0.00,
  `bank_received` decimal(12,2) DEFAULT 0.00,
  `old_gold_amount` decimal(12,2) DEFAULT 0.00,
  `balance_amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,3) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `making_percent` decimal(5,2) DEFAULT NULL,
  `item_metal_type` varchar(20) DEFAULT 'gold',
  `purity_title` varchar(50) DEFAULT NULL,
  `gold_value` decimal(12,2) DEFAULT NULL,
  `making_amount` decimal(12,2) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `description`, `hsn_code`, `quantity`, `rate`, `making_percent`, `item_metal_type`, `purity_title`, `gold_value`, `making_amount`, `total_amount`) VALUES
(34, 0, 'BUTTI', '7113', 10.000, 8932.04, 16.00, '0.00', NULL, 0.92, 0.00, 105320.00),
(35, 0, 'BUTTI', '7113', 10.000, 8932.04, 16.00, '0.00', NULL, 0.92, 0.00, 105320.00),
(36, 0, 'RING', '7113', 25.000, 250.00, 0.00, '0.00', NULL, 0.00, 0.00, 6250.00),
(38, 0, 'BUTTI', '7113', 10.000, 8932.04, 16.00, '0.00', NULL, 0.92, 0.00, 105320.00),
(39, 0, 'BUTTI', '7113', 10.000, 8932.04, 16.00, '0.00', NULL, 0.92, 0.00, 105320.00),
(40, 0, 'BUTTI', '7113', 10.000, 8932.04, 16.00, '0.00', NULL, 0.92, 0.00, 105320.00),
(42, 0, 'ring', '711319', 10.000, 13429.32, 16.00, '0.00', NULL, 0.92, 0.00, 158349.00);

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gst_no` varchar(100) DEFAULT NULL,
  `pan_no` varchar(100) DEFAULT NULL,
  `state_code` varchar(20) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_no` varchar(255) DEFAULT NULL,
  `ifsc` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gst_no` varchar(20) DEFAULT '',
  `pan_no` varchar(15) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `mobile`, `address`, `created_at`, `gst_no`, `pan_no`) VALUES
(1, 'Jay soni', '7990361109', 'khed', '2026-06-19 11:47:18', '2222222222', '222222222'),
(2, 'Urmilaben Babubhai Bhuraiya', 'n\\a', 'Khedbrahma', '2026-06-19 12:31:52', 'n/a', 'n/a'),
(3, 'MANOJBHAI D GOSWAMI', 'N/A', 'KHEDBRAHMA', '2026-06-19 12:51:25', 'N/A', 'N/A'),
(4, 'UU', '1234567890', 'UU', '2026-06-22 04:36:19', 'UU', 'UU'),
(5, 'Aryan shah', '9316956352', 'Kodinar ', '2026-06-23 04:02:20', '24IKEPS1148R1ZU', 'IKEPS1148R'),
(6, 'Akhil Prajapati', '9327056272', 'Visnagar', '2026-06-24 08:44:33', '--', '--');

-- --------------------------------------------------------

--
-- Table structure for table `gold_rates`
--

CREATE TABLE `gold_rates` (
  `id` int(11) NOT NULL,
  `purity` varchar(20) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `rate_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hsn_master`
--

CREATE TABLE `hsn_master` (
  `id` int(11) NOT NULL,
  `hsn_code` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hsn_master`
--

INSERT INTO `hsn_master` (`id`, `hsn_code`, `description`, `gst_rate`) VALUES
(3, '7113119', 'MANGALSUTRA', 3.00);

-- --------------------------------------------------------

--
-- Table structure for table `purity_master`
--

CREATE TABLE `purity_master` (
  `id` int(11) NOT NULL,
  `purity_name` varchar(50) DEFAULT NULL,
  `metal_type` varchar(20) DEFAULT NULL,
  `purity_value` varchar(20) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purity_master`
--

INSERT INTO `purity_master` (`id`, `purity_name`, `metal_type`, `purity_value`, `rate`) VALUES
(1, 'Gold 92 Hallmark', NULL, '92.0', NULL),
(2, 'Gold 22K', NULL, '91.6', NULL),
(3, 'Gold 18K', NULL, '75.0', NULL),
(4, 'Gold 24K', NULL, '99.9', NULL),
(5, 'Gold 92 Hallmark', NULL, '92.0', NULL),
(6, 'Gold 22K', NULL, '91.6', NULL),
(7, 'Gold 18K', NULL, '75.0', NULL),
(8, 'Gold 24K', NULL, '99.9', NULL),
(9, '83.3%', NULL, '83.3', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rate_master`
--

CREATE TABLE `rate_master` (
  `id` int(11) NOT NULL,
  `purity_id` int(11) NOT NULL,
  `rate` decimal(12,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_master`
--

INSERT INTO `rate_master` (`id`, `purity_id`, `rate`, `updated_at`) VALUES
(6, 8, 15000.00, '2026-06-20 05:34:53'),
(7, 1, 13800.00, '2026-06-20 05:35:16');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) DEFAULT NULL,
  `setting_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_name`, `setting_value`) VALUES
(1, 'gold_gst', '3'),
(2, 'silver_gst', '3'),
(3, 'default_making', '10'),
(4, 'default_wastage', '5'),
(5, 'sale_gst', '3'),
(6, 'labour_gst', '5'),
(7, 'shop_name', 'CHANDRAKALA JEWELLERS');

-- --------------------------------------------------------

--
-- Table structure for table `settings_backup`
--

CREATE TABLE `settings_backup` (
  `id` int(11) NOT NULL DEFAULT 1,
  `backup_folder` varchar(500) DEFAULT NULL,
  `auto_backup` tinyint(1) DEFAULT 0,
  `last_backup` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings_backup`
--

INSERT INTO `settings_backup` (`id`, `backup_folder`, `auto_backup`, `last_backup`, `updated_at`) VALUES
(1, '../backups/', 1, '2026-06-23 20:53:41', '2026-06-23 15:23:41');

-- --------------------------------------------------------

--
-- Table structure for table `settings_business`
--

CREATE TABLE `settings_business` (
  `id` int(11) NOT NULL DEFAULT 1,
  `shop_name` varchar(255) DEFAULT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gst_no` varchar(30) DEFAULT NULL,
  `pan_no` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `invoice_footer` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings_business`
--

INSERT INTO `settings_business` (`id`, `shop_name`, `owner_name`, `address`, `mobile`, `email`, `gst_no`, `pan_no`, `logo`, `invoice_footer`) VALUES
(1, 'Chandrakala Jewellers', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings_notifications`
--

CREATE TABLE `settings_notifications` (
  `id` int(11) NOT NULL DEFAULT 1,
  `backup_reminder` tinyint(1) DEFAULT 1,
  `pending_payment_alert` tinyint(1) DEFAULT 1,
  `low_storage_alert` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings_notifications`
--

INSERT INTO `settings_notifications` (`id`, `backup_reminder`, `pending_payment_alert`, `low_storage_alert`) VALUES
(1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings_printing`
--

CREATE TABLE `settings_printing` (
  `id` int(11) NOT NULL DEFAULT 1,
  `paper_size` enum('A4','A5') DEFAULT 'A5',
  `show_logo` tinyint(1) DEFAULT 1,
  `show_gst` tinyint(1) DEFAULT 1,
  `show_qr` tinyint(1) DEFAULT 1,
  `show_signature` tinyint(1) DEFAULT 1,
  `default_print_mode` enum('preview','direct') DEFAULT 'preview'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings_printing`
--

INSERT INTO `settings_printing` (`id`, `paper_size`, `show_logo`, `show_gst`, `show_qr`, `show_signature`, `default_print_mode`) VALUES
(1, 'A5', 1, 1, 1, 1, 'preview');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_no` (`bill_no`),
  ADD UNIQUE KEY `bill_no_2` (`bill_no`),
  ADD KEY `idx_bill_date` (`bill_date`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gold_rates`
--
ALTER TABLE `gold_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hsn_master`
--
ALTER TABLE `hsn_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purity_master`
--
ALTER TABLE `purity_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rate_master`
--
ALTER TABLE `rate_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purity_id` (`purity_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings_backup`
--
ALTER TABLE `settings_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings_business`
--
ALTER TABLE `settings_business`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings_notifications`
--
ALTER TABLE `settings_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings_printing`
--
ALTER TABLE `settings_printing`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `gold_rates`
--
ALTER TABLE `gold_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hsn_master`
--
ALTER TABLE `hsn_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purity_master`
--
ALTER TABLE `purity_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `rate_master`
--
ALTER TABLE `rate_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `rate_master`
--
ALTER TABLE `rate_master`
  ADD CONSTRAINT `rate_master_ibfk_1` FOREIGN KEY (`purity_id`) REFERENCES `purity_master` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
