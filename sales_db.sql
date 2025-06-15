-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2025 at 06:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sales_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `daily_sales`
--

CREATE TABLE `daily_sales` (
  `id` int(11) NOT NULL,
  `clerk_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `cash_orders` int(11) DEFAULT 0,
  `cash_sales` decimal(10,2) DEFAULT 0.00,
  `card_orders` int(11) DEFAULT 0,
  `card_sales` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_sales`
--

INSERT INTO `daily_sales` (`id`, `clerk_id`, `date`, `total_orders`, `total_sales`, `cash_orders`, `cash_sales`, `card_orders`, `card_sales`, `created_at`) VALUES
(5, 16, '2024-12-28', 2, 16.00, 2, 16.00, 0, 0.00, '2024-12-28 03:45:51'),
(7, 16, '2025-01-13', 2, 7.70, 1, 4.00, 0, 0.00, '2025-01-13 14:05:52'),
(9, 16, '2025-04-05', 2, 10.50, 1, 8.00, 0, 0.00, '2025-04-05 08:03:05');

-- --------------------------------------------------------

--
-- Table structure for table `employee_ids`
--

CREATE TABLE `employee_ids` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(255) NOT NULL,
  `fullname` varchar(200) NOT NULL,
  `role` enum('Supervisor','Clerk') NOT NULL,
  `assigned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_ids`
--

INSERT INTO `employee_ids` (`id`, `employee_id`, `fullname`, `role`, `assigned`, `created_at`) VALUES
(1, 'SV-1001', 'Alice Johnson', 'Supervisor', 0, '2024-12-28 02:43:55'),
(2, 'SV-1002', 'Bob Smith', 'Supervisor', 1, '2024-12-28 02:43:55'),
(3, 'CL-2001', 'Charlie Brown', 'Clerk', 0, '2024-12-28 02:43:55'),
(4, 'CL-2002', 'Diana Prince', 'Clerk', 1, '2024-12-28 02:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `guest`
--

CREATE TABLE `guest` (
  `id` int(11) NOT NULL,
  `fullname` varchar(200) NOT NULL,
  `contact` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `salt` varchar(64) NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 3 COMMENT '1=SalesSV, 2=Clerks, 3=Guest',
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `employee_id` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guest`
--

INSERT INTO `guest` (`id`, `fullname`, `contact`, `address`, `email`, `password`, `salt`, `type`, `date_created`, `employee_id`) VALUES
(15, 'Bob Smith', '0112223333', '', 'bob@gmail.com', '$2y$10$6KwCOC2QyFEelH71bI9LkuwpPuIS3gJQQ/f5r5wFGDEv8GxxCRLVa', '25c46967aca930d3b911230aab33588cf6b53b107766c950f61ff8705f2c47d0', 1, '2024-12-28 11:09:06', 'SV-1002'),
(16, 'Diana Prince', '01122233332', '', 'diana@gmail.com', '$2y$10$pT2lae1dNP.0bBe64eNEPeSZOt1QvFUL8.Q0TYr0QDtvr3KMNqiu2', '69e8e908724bfe2c8d9ca54b67e8aeefcea54bfc1f27294f6e9ec05eaf52078c', 2, '2024-12-28 11:11:12', 'CL-2002'),
(19, 'Alif Ibrahim', '01234567890', '-', 'walk-in-1735397966', '', '', 3, '2024-12-28 22:59:26', NULL),
(20, 'Tuan Muhammad Farhan Bin Tuan Rashid', '0197909367', 'Jalan Surabaya', 'farhanrashid293@gmail.com', '$2y$10$8y/CVwWah74WMI0AQygoq.SfPHB2Eds/eCk3W3ItdilZYfSgGdag.', '', 3, '2024-12-28 23:19:20', ''),
(21, 'Abqari', '0197909311', 'NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA', 'abqari@gmail.com', '$2y$10$EDVI7OCu9XxTZ3KCT/fad.bMw3zbZI.5boG6RJgvHAIUqGoOTHP3q', '', 3, '2024-12-29 01:43:48', ''),
(22, 'Mohammed Umair bin Mohammed Suhaimee', '601157033208', 'Lot 13, Kampung Baru, Pulau Sayak, 08500, Kota Kuala Muda, Kedah', 'umair1211@gmail.com', '$2y$10$0vQAdGDcTmh./sxFtvrC6uoN.En498cHaG19Qf9QA5rH2NphY763u', '', 3, '2025-01-12 22:21:34', NULL),
(23, 'farhanrashid', '0197909367', 'NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA', 'farhanras@gmail.com', '$2y$10$M5E.2zSMROKvV91hYNL9luGbgZaexi2odL8Qm.n1Kk2coUUxFRyzS', '8211c2ca72eca89e751af21cb197e09f3b8a6d004e238f02fa99b987933a55c7', 3, '2025-01-13 21:19:03', NULL),
(24, 'Tuan Muhammad Farhan Bin Tuan Rashid', '0197909367', '-', 'walk-in-1736777152', '', '', 3, '2025-01-13 22:05:52', NULL),
(25, 'PAAN', '0197909367', '-', 'walk-in-1736778073', '', '', 3, '2025-01-13 22:21:13', NULL),
(26, 'Ryan Teo', '0133173626', 'Jalan Goshen', 'ryanteo329@gmail.com', '$2y$10$dDQkMXRKnp7JVR8o.Z2jA.PbR3xKi4ALPRoBuJuT8yV3P9VOS1Voy', '85da7c0efb3028fbc37b6319e4af26033387bb5b4b70cc31759a69d1b4fc88e6', 3, '2025-03-25 10:12:25', NULL),
(27, 'Ryan', '0133173626', '-', 'walk-in-1743840185', '', '', 3, '2025-04-05 16:03:05', NULL),
(28, 'Ryan Teo', '0133173626', '-', 'walk-in-1743864743', '', '', 3, '2025-04-05 22:52:23', NULL),
(29, 'MIZA RAIHANAH BINTI MISDIFIAN', '0135685063', 'UUM Sintok Kedah Darul Aman, Malaysia', 'chimhoneybee@gmail.com', '$2y$10$YCIEqk8F5OH6n1XcZGubOOBBRv7ErbKzrqsjbmlA93Uf..bBzhdVe', 'a7014f466a24139303b17dd2a5d855aaed04ab9fa11ee06705a6fdb89476efbb', 3, '2025-06-14 22:11:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `stock_level` int(11) NOT NULL,
  `reorder_point` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_name`, `description`, `category`, `unit_price`, `stock_level`, `reorder_point`, `status`, `created_at`, `updated_at`) VALUES
(9, 'Classic Butter Bun', NULL, '', 1.50, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 07:04:55'),
(10, 'Chocolate Croissant', NULL, '', 2.00, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 17:36:37'),
(11, 'Almond Danish', NULL, '', 2.50, 88, 0, 'active', '2024-12-28 07:04:55', '2025-01-13 14:23:12'),
(12, 'Cinnamon Roll Delight', NULL, '', 2.00, 100, 0, 'active', '2024-12-28 07:04:55', '2025-04-08 16:15:11'),
(13, 'Roti Canai Special', NULL, '', 1.20, 99, 0, 'active', '2024-12-28 07:04:55', '2025-01-13 14:21:13'),
(14, 'Pineapple Tart', NULL, '', 3.00, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 17:36:50'),
(15, 'Red Bean Bun', NULL, '', 1.80, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 17:36:53'),
(16, 'Mango Mousse Cake', NULL, '', 5.00, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 17:37:40'),
(17, 'Cheese Puff Pastry', NULL, '', 2.80, 94, 0, 'active', '2024-12-28 07:04:55', '2025-01-13 13:22:43'),
(18, 'Matcha Green Tea Cake', NULL, '', 4.50, 85, 0, 'active', '2024-12-28 07:04:55', '2025-04-10 07:23:52'),
(19, 'Blueberry Muffin', NULL, '', 1.50, 76, 0, 'active', '2024-12-28 07:04:55', '2025-04-09 04:13:35'),
(20, 'Honey Oat Bread', NULL, '', 2.20, 83, 0, 'active', '2024-12-28 07:04:55', '2025-04-10 05:26:40'),
(21, 'Spicy Tuna Puff', NULL, '', 2.50, 99, 0, 'active', '2024-12-28 07:04:55', '2025-04-05 14:52:23'),
(22, 'Egg Tart', NULL, '', 1.80, 99, 0, 'active', '2024-12-28 07:04:55', '2025-01-13 14:23:12'),
(23, 'Fruit Tartlet', NULL, '', 3.50, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 17:37:14'),
(24, 'Vanilla Cream Puff', NULL, '', 2.00, 98, 0, 'active', '2024-12-28 07:04:55', '2025-04-05 08:22:30'),
(25, 'Sesame Seed Bun', NULL, '', 1.50, 100, 0, 'active', '2024-12-28 07:04:55', '2024-12-28 07:04:55'),
(26, 'Lemon Drizzle Cake', NULL, '', 4.00, 96, 0, 'active', '2024-12-28 07:04:55', '2025-04-05 08:03:42'),
(27, 'Caramel Pecan Pie', NULL, '', 5.50, 86, 0, 'active', '2024-12-28 07:04:55', '2025-04-13 03:15:28'),
(28, 'Roti Jala (Lace Pancake)', NULL, '', 1.00, 96, 0, 'active', '2024-12-28 07:04:55', '2025-03-25 02:15:04');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `clerk_id` int(11) DEFAULT NULL,
  `transaction_type` enum('sale','restock','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `product_id`, `order_id`, `clerk_id`, `transaction_type`, `quantity`, `previous_stock`, `new_stock`, `transaction_date`) VALUES
(31, 11, 46, NULL, 'sale', 1, 100, 99, '2024-12-28 18:19:59'),
(32, 19, 46, NULL, 'sale', 2, 100, 98, '2024-12-28 18:19:59'),
(33, 27, 47, NULL, 'sale', 2, 100, 98, '2024-12-28 18:20:16'),
(34, 17, 47, NULL, 'sale', 2, 100, 98, '2024-12-28 18:20:16'),
(35, 11, 48, NULL, 'sale', 1, 98, 97, '2025-01-12 14:22:27'),
(36, 19, 49, NULL, 'sale', 6, 96, 90, '2025-01-12 14:34:46'),
(37, 17, 49, NULL, 'sale', 1, 96, 95, '2025-01-12 14:34:46'),
(38, 11, 53, NULL, 'sale', 1, 97, 96, '2025-01-13 13:19:12'),
(39, 19, 53, NULL, 'sale', 1, 90, 89, '2025-01-13 13:19:12'),
(40, 11, 54, NULL, 'sale', 1, 96, 95, '2025-01-13 13:20:00'),
(41, 19, 54, NULL, 'sale', 1, 89, 88, '2025-01-13 13:20:00'),
(42, 11, 55, NULL, 'sale', 2, 95, 93, '2025-01-13 13:21:25'),
(43, 19, 55, NULL, 'sale', 2, 88, 86, '2025-01-13 13:21:25'),
(44, 27, 56, NULL, 'sale', 1, 96, 95, '2025-01-13 13:22:43'),
(45, 17, 56, NULL, 'sale', 1, 95, 94, '2025-01-13 13:22:43'),
(46, 11, 57, NULL, 'sale', 1, 92, 91, '2025-01-13 13:22:59'),
(47, 19, 57, NULL, 'sale', 1, 85, 84, '2025-01-13 13:22:59'),
(48, 11, 58, 16, 'sale', 1, 91, 90, '2025-01-13 14:05:52'),
(49, 19, 58, 16, 'sale', 1, 84, 83, '2025-01-13 14:05:52'),
(50, 11, 59, 16, 'sale', 1, 90, 89, '2025-01-13 14:21:13'),
(51, 13, 59, 16, 'sale', 1, 100, 99, '2025-01-13 14:21:13'),
(52, 11, 60, NULL, 'sale', 1, 89, 88, '2025-01-13 14:23:12'),
(53, 19, 60, NULL, 'sale', 1, 83, 82, '2025-01-13 14:23:12'),
(54, 22, 60, NULL, 'sale', 1, 100, 99, '2025-01-13 14:23:12'),
(56, 28, 62, NULL, 'sale', 4, 100, 96, '2025-03-25 02:15:04'),
(57, 19, 63, NULL, 'sale', 1, 82, 81, '2025-03-25 02:18:45'),
(58, 19, 64, NULL, 'sale', 2, 81, 79, '2025-03-25 02:53:34'),
(59, 26, 65, 16, 'sale', 2, 100, 98, '2025-04-05 08:03:05'),
(60, 26, 66, NULL, 'sale', 2, 98, 96, '2025-04-05 08:03:42'),
(61, 24, 67, NULL, 'sale', 2, 100, 98, '2025-04-05 08:22:30'),
(62, 21, 68, 16, 'sale', 1, 100, 99, '2025-04-05 14:52:23'),
(63, 12, 69, NULL, 'sale', 1, 100, 99, '2025-04-05 18:25:13'),
(64, 19, 70, NULL, 'sale', 3, 79, 76, '2025-04-09 04:13:35'),
(65, 20, 71, NULL, 'sale', 10, 100, 90, '2025-04-10 05:26:18'),
(66, 27, 72, NULL, 'sale', 5, 95, 90, '2025-04-10 05:26:29'),
(67, 20, 73, NULL, 'sale', 7, 90, 83, '2025-04-10 05:26:40'),
(68, 18, 74, NULL, 'sale', 15, 100, 85, '2025-04-10 07:23:52'),
(69, 27, 75, NULL, 'sale', 4, 90, 86, '2025-04-13 03:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `order_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `promo_code` varchar(50) DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','card','online') NOT NULL,
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `delivery_address` text DEFAULT NULL,
  `order_type` enum('in-store','online') NOT NULL,
  `clerk_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `guest_id`, `order_number`, `order_date`, `total_amount`, `subtotal`, `discount`, `promo_code`, `status`, `payment_method`, `payment_status`, `delivery_address`, `order_type`, `clerk_id`, `notes`, `created_at`) VALUES
(46, 20, 'ORD1735409999', '2024-12-29 02:19:59', 5.50, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', 'Jalan Surabaya', 'online', 16, NULL, '2024-12-28 18:19:59'),
(47, 21, 'ORD1735410016', '2024-12-29 02:20:16', 16.60, 0.00, 0.00, NULL, 'completed', 'online', 'paid', 'NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA', 'online', 16, NULL, '2024-12-28 18:20:16'),
(48, 22, 'ORD1736691747', '2025-01-12 22:22:27', 2.50, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', 'Lot 13, Kampung Baru, Pulau Sayak, 08500, Kota Kuala Muda, Kedah', 'online', 16, NULL, '2025-01-12 14:22:27'),
(49, 22, 'ORD1736692486', '2025-01-12 22:34:46', 11.80, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', 'Lot 13, Kampung Baru, Pulau Sayak, 08500, Kota Kuala Muda, Kedah', 'online', 16, NULL, '2025-01-12 14:34:46'),
(53, 23, 'ORD1736774352', '2025-01-13 21:19:12', 4.00, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', '                                NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA                            ', 'online', 16, NULL, '2025-01-13 13:19:12'),
(54, 23, 'ORD1736774400', '2025-01-13 21:20:00', 4.00, 0.00, 0.00, NULL, 'cancelled', 'cash', 'pending', '                                NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA                            ', 'online', 16, NULL, '2025-01-13 13:20:00'),
(55, 23, 'ORD1736774485', '2025-01-13 21:21:25', 8.00, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', 'NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA', 'online', 16, NULL, '2025-01-13 13:21:25'),
(56, 23, 'ORD1736774563', '2025-01-13 21:22:43', 8.30, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', '                                NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA                            ', 'online', 16, NULL, '2025-01-13 13:22:43'),
(57, 23, 'ORD1736774579', '2025-01-13 21:22:59', 4.00, 0.00, 0.00, NULL, 'completed', 'card', 'paid', '                                NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA                            ', 'online', 16, NULL, '2025-01-13 13:22:59'),
(58, 24, 'POS1736777152', '2025-01-13 22:05:52', 4.00, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', NULL, 'in-store', 16, NULL, '2025-01-13 14:05:52'),
(59, 25, 'POS1736778073', '2025-01-13 22:21:13', 3.70, 0.00, 0.00, NULL, 'completed', 'online', 'paid', NULL, 'in-store', 16, NULL, '2025-01-13 14:21:13'),
(60, 23, 'ORD1736778192', '2025-01-13 22:23:12', 5.80, 0.00, 0.00, NULL, 'completed', 'online', 'paid', '                                NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA                            ', 'online', 16, NULL, '2025-01-13 14:23:12'),
(61, 23, 'ORD1736778576', '2025-01-13 22:29:36', 180.00, 0.00, 0.00, NULL, 'cancelled', 'cash', 'pending', '                                NO S-48/50-A BATU 1  1/2\r\nJALAN PENGKALAN CHEPA                            ', 'online', 16, NULL, '2025-01-13 14:29:36'),
(62, 26, 'ORD1742868904', '2025-03-25 10:15:04', 4.00, 0.00, 0.00, NULL, 'completed', 'card', 'paid', '                     ', 'online', 16, NULL, '2025-03-25 02:15:04'),
(63, 26, 'ORD1742869125', '2025-03-25 10:18:45', 1.50, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', '                                Jalan Goshen                            ', 'online', 16, NULL, '2025-03-25 02:18:45'),
(64, 26, 'ORD1742871214', '2025-03-25 10:53:34', 3.00, 0.00, 0.00, NULL, 'completed', 'card', 'paid', '                                Jalan Goshen                            ', 'online', 16, NULL, '2025-03-25 02:53:34'),
(65, 27, 'POS1743840185', '2025-04-05 16:03:05', 8.00, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', NULL, 'in-store', 16, NULL, '2025-04-05 08:03:05'),
(66, 26, 'ORD1743840222', '2025-04-05 16:03:42', 8.00, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', 'Jalan Goshen                            ', 'online', 16, NULL, '2025-04-05 08:03:42'),
(67, 26, 'ORD1743841350', '2025-04-05 16:22:30', 4.00, 0.00, 0.00, NULL, 'completed', 'online', 'paid', 'Jalan Goshen                            ', 'online', 16, NULL, '2025-04-05 08:22:30'),
(68, 28, 'POS1743864743', '2025-04-05 22:52:23', 2.50, 0.00, 0.00, NULL, 'completed', 'online', 'paid', NULL, 'in-store', 16, NULL, '2025-04-05 14:52:23'),
(69, 26, 'ORD1743877513', '2025-04-06 02:25:13', 2.00, 0.00, 0.00, NULL, 'cancelled', 'cash', 'pending', '                                    Jalan Goshen                                ', 'online', NULL, NULL, '2025-04-05 18:25:13'),
(70, 26, 'ORD1744172015', '2025-04-09 12:13:35', 4.50, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', '                                    Jalan Goshen                                ', 'online', 16, NULL, '2025-04-09 04:13:35'),
(71, 26, 'ORD1744262778', '2025-04-10 13:26:18', 22.00, 0.00, 0.00, NULL, 'completed', 'card', 'paid', '                                    Jalan Goshen                                ', 'online', 16, NULL, '2025-04-10 05:26:18'),
(72, 26, 'ORD1744262789', '2025-04-10 13:26:29', 27.50, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', '                                    Jalan Goshen                                ', 'online', 16, NULL, '2025-04-10 05:26:29'),
(73, 26, 'ORD1744262800', '2025-04-10 13:26:40', 15.40, 0.00, 0.00, NULL, 'completed', 'online', 'paid', '                                    Jalan Goshen                                ', 'online', 16, NULL, '2025-04-10 05:26:40'),
(74, 26, 'ORD1744269832', '2025-04-10 15:23:52', 67.50, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', '                                    Jalan Goshen                                ', 'online', 16, NULL, '2025-04-10 07:23:52'),
(75, 26, 'ORD1744514128', '2025-04-13 11:15:28', 22.00, 0.00, 0.00, NULL, 'completed', 'cash', 'paid', 'Jalan Goshen', 'online', 16, NULL, '2025-04-13 03:15:28');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    -- Only track in-store sales in daily_sales
    IF NEW.order_type = 'in-store' THEN
        INSERT INTO daily_sales (
            clerk_id, 
            date, 
            total_orders,
            total_sales,
            cash_orders,
            cash_sales,
            card_orders,
            card_sales
        )
        VALUES (
            NEW.clerk_id,
            DATE(NEW.order_date),
            1,
            NEW.total_amount,
            IF(NEW.payment_method = 'cash', 1, 0),
            IF(NEW.payment_method = 'cash', NEW.total_amount, 0),
            IF(NEW.payment_method = 'card', 1, 0),
            IF(NEW.payment_method = 'card', NEW.total_amount, 0)
        )
        ON DUPLICATE KEY UPDATE
            total_orders = total_orders + 1,
            total_sales = total_sales + VALUES(total_sales),
            cash_orders = cash_orders + VALUES(cash_orders),
            cash_sales = cash_sales + VALUES(cash_sales),
            card_orders = card_orders + VALUES(card_orders),
            card_sales = card_sales + VALUES(card_sales);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(38, 46, 11, 1, 2.50, 2.50),
(39, 46, 19, 2, 1.50, 3.00),
(40, 47, 27, 2, 5.50, 11.00),
(41, 47, 17, 2, 2.80, 5.60),
(42, 48, 11, 1, 2.50, 2.50),
(43, 49, 19, 6, 1.50, 9.00),
(44, 49, 17, 1, 2.80, 2.80),
(45, 53, 11, 1, 2.50, 2.50),
(46, 53, 19, 1, 1.50, 1.50),
(47, 54, 11, 1, 2.50, 2.50),
(48, 54, 19, 1, 1.50, 1.50),
(49, 55, 11, 2, 2.50, 5.00),
(50, 55, 19, 2, 1.50, 3.00),
(51, 56, 27, 1, 5.50, 5.50),
(52, 56, 17, 1, 2.80, 2.80),
(53, 57, 11, 1, 2.50, 2.50),
(54, 57, 19, 1, 1.50, 1.50),
(55, 58, 11, 1, 2.50, 2.50),
(56, 58, 19, 1, 1.50, 1.50),
(57, 59, 11, 1, 2.50, 2.50),
(58, 59, 13, 1, 1.20, 1.20),
(59, 60, 11, 1, 2.50, 2.50),
(60, 60, 19, 1, 1.50, 1.50),
(61, 60, 22, 1, 1.80, 1.80),
(63, 62, 28, 4, 1.00, 4.00),
(64, 63, 19, 1, 1.50, 1.50),
(65, 64, 19, 2, 1.50, 3.00),
(66, 65, 26, 2, 4.00, 8.00),
(67, 66, 26, 2, 4.00, 8.00),
(68, 67, 24, 2, 2.00, 4.00),
(69, 68, 21, 1, 2.50, 2.50),
(70, 69, 12, 1, 2.00, 2.00),
(71, 70, 19, 3, 1.50, 4.50),
(72, 71, 20, 10, 2.20, 22.00),
(73, 72, 27, 5, 5.50, 27.50),
(74, 73, 20, 7, 2.20, 15.40),
(75, 74, 18, 15, 4.50, 67.50),
(76, 75, 27, 4, 5.50, 22.00);

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `after_order_item_insert` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE current_stock INT;
    
    -- Get current stock level
    SELECT stock_level INTO current_stock
    FROM inventory 
    WHERE id = NEW.product_id;
    
    -- Record inventory transaction first
    INSERT INTO inventory_transactions (
        product_id, 
        order_id, 
        clerk_id,
        transaction_type,
        quantity,
        previous_stock,
        new_stock
    )
    SELECT 
        NEW.product_id,
        NEW.order_id,
        o.clerk_id,
        'sale',
        NEW.quantity,
        current_stock,
        current_stock - NEW.quantity
    FROM orders o
    WHERE o.id = NEW.order_id;
    
    -- Then update inventory stock level
    UPDATE inventory 
    SET stock_level = current_stock - NEW.quantity 
    WHERE id = NEW.product_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `productingredients`
--

CREATE TABLE `productingredients` (
  `Product_ID` int(11) NOT NULL,
  `Inventory_ID` int(11) NOT NULL,
  `Quantity_Needed` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productingredients`
--

INSERT INTO `productingredients` (`Product_ID`, `Inventory_ID`, `Quantity_Needed`) VALUES
(9, 3, 0.08),
(9, 14, 0.01),
(9, 35, 0.002),
(9, 37, 0.02),
(9, 38, 0.02),
(10, 4, 0.08),
(10, 14, 0.01),
(10, 35, 0.002),
(10, 37, 0.03),
(10, 55, 0.02),
(11, 3, 0.08),
(11, 14, 0.01),
(11, 35, 0.002),
(11, 37, 0.02),
(11, 61, 0.02),
(12, 3, 0.1),
(12, 14, 0.02),
(12, 35, 0.002),
(12, 37, 0.03),
(12, 50, 0.005),
(13, 2, 0.1),
(13, 35, 0.002),
(13, 37, 0.02),
(13, 38, 0.03),
(13, 43, 0.01),
(14, 5, 0.05),
(14, 14, 0.01),
(14, 35, 0.002),
(14, 37, 0.02),
(14, 38, 0.01),
(15, 3, 0.08),
(15, 16, 0.02),
(15, 35, 0.002),
(15, 37, 0.02),
(15, 38, 0.02),
(16, 4, 0.08),
(16, 14, 0.02),
(16, 35, 0.002),
(16, 39, 0.02),
(16, 42, 0.03),
(17, 5, 0.08),
(17, 14, 0.01),
(17, 35, 0.002),
(17, 37, 0.02),
(17, 42, 0.03),
(18, 4, 0.08),
(18, 14, 0.02),
(18, 35, 0.002),
(18, 38, 0.02),
(18, 64, 0.01),
(19, 6, 0.08),
(19, 14, 0.01),
(19, 23, 0.03),
(19, 35, 0.002),
(19, 37, 0.02),
(20, 1, 0.1),
(20, 30, 0.02),
(20, 35, 0.002),
(20, 38, 0.02),
(20, 60, 0.02),
(21, 5, 0.08),
(21, 14, 0.01),
(21, 35, 0.002),
(21, 38, 0.02),
(21, 43, 0.02),
(22, 5, 0.05),
(22, 14, 0.02),
(22, 35, 0.002),
(22, 37, 0.02),
(22, 38, 0.03),
(23, 5, 0.05),
(23, 14, 0.02),
(23, 22, 0.02),
(23, 35, 0.002),
(23, 37, 0.02),
(24, 5, 0.05),
(24, 14, 0.01),
(24, 35, 0.002),
(24, 37, 0.02),
(24, 45, 0.001),
(25, 2, 0.08),
(25, 14, 0.01),
(25, 35, 0.002),
(25, 37, 0.02),
(25, 38, 0.02),
(26, 4, 0.08),
(26, 14, 0.02),
(26, 35, 0.002),
(26, 38, 0.02),
(26, 47, 0.001),
(27, 4, 0.08),
(27, 16, 0.02),
(27, 35, 0.002),
(27, 37, 0.02),
(27, 61, 0.02),
(28, 6, 0.08),
(28, 35, 0.002),
(28, 38, 0.03),
(28, 39, 0.02),
(28, 43, 0.01);

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `code`, `description`, `discount_type`, `discount_value`, `start_date`, `end_date`, `status`) VALUES
(1, 'WELCOME10', 'Welcome discount 10%', 'percentage', 10.00, '2024-01-01', '2024-12-31', 'active'),
(2, 'SAVE5', 'RM5 off for orders above RM50', 'fixed', 5.00, '2024-01-01', '2024-12-31', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `small_inventory`
--

CREATE TABLE `small_inventory` (
  `Inventory_ID` int(11) NOT NULL,
  `Ingredient_Name` varchar(100) DEFAULT NULL,
  `Ingredient_kg` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `small_inventory`
--

INSERT INTO `small_inventory` (`Inventory_ID`, `Ingredient_Name`, `Ingredient_kg`) VALUES
(1, 'Whole Wheat Flour', 100),
(2, 'Whole Grain Flour', 100),
(3, 'Bread Flour', 99.68),
(4, 'Cake Flour', 49.92),
(5, 'Pastry Flour', 59.870000000000005),
(6, 'Self-Rising Flour', 10.080000000000007),
(7, 'Alternative Non-Wheat Flour', 20),
(8, 'Cornmeal', 10),
(9, 'Cornstarch', 70),
(10, 'Baking Soda', 10),
(11, 'Baking Powder', 10),
(12, 'Yeast', 10),
(13, 'Sourdough Starters', 10),
(14, 'Granulated Sugar', 98.68999999999996),
(15, 'Powdered Sugar', 100),
(16, 'Brown Sugar', 99.98),
(17, 'Castor Sugar', 100),
(18, 'Coarse Sugar', 100),
(19, 'Date Sugar', 20),
(20, 'Coconut Sugar', 20),
(21, 'Maple Sugar', 20),
(22, 'Strawberry Jams', 40),
(23, 'Blueberry Jams', 36.279999999999994),
(24, 'Peanut Butter', 50),
(25, 'Red Food Coloring', 5),
(26, 'Blue Food Coloring', 5),
(27, 'Green Food Coloring', 5),
(28, 'Orange Food Coloring', 5),
(29, 'Yellow Food Coloring', 5),
(30, 'Honey', 50),
(31, 'Molasses', 20),
(32, 'Maple Syrup', 30),
(33, 'Cane Syrup', 20),
(34, 'Agave Syrup', 20),
(35, 'Granulated Table Salt', 19.738000000000014),
(36, 'Sea Salt', 20),
(37, 'Unsalted Butter', 47.37999999999997),
(38, 'Large Eggs', 119.97),
(39, 'Milk', 100),
(40, 'Buttermilk', 100),
(41, 'Alternative Milks', 100),
(42, 'Cream Cheese', 99.97),
(43, 'Vegetable Oil', 50),
(44, 'Shortening', 20),
(45, 'Pure Vanilla Extract', 10),
(46, 'Almond Extract', 10),
(47, 'Lemon Extract', 10),
(48, 'Mint Extract', 10),
(49, 'Whole Vanilla Bean', 10),
(50, 'Ground Cinnamon', 20),
(51, 'Ground Cloves', 10),
(52, 'Allspice', 10),
(53, 'Ginger', 10),
(54, 'Bar Chocolate', 50),
(55, 'Chocolate Chips', 20),
(56, 'Unsweetened Cocoa Powder', 20),
(57, 'Dutch-Process Cocoa Powder', 10),
(58, 'Raisins', 20),
(59, 'Cranberries', 100),
(60, 'Rolled Oats', 50),
(61, 'Nuts', 49.899999999999984),
(62, 'Sprinkles', 10),
(63, 'Crystalized Ginger', 10),
(64, 'Matcha Powder', 100),
(65, 'Chia Seeds', 50),
(66, 'Coconut', 100);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `daily_sales`
--
ALTER TABLE `daily_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_clerk_date` (`clerk_id`,`date`);

--
-- Indexes for table `employee_ids`
--
ALTER TABLE `employee_ids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `guest`
--
ALTER TABLE `guest`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_status` (`status`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `clerk_id` (`clerk_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_clerk_date` (`clerk_id`,`order_date`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `fk_orders_guest` (`guest_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orderitems_order` (`order_id`),
  ADD KEY `fk_orderitems_product` (`product_id`);

--
-- Indexes for table `productingredients`
--
ALTER TABLE `productingredients`
  ADD PRIMARY KEY (`Product_ID`,`Inventory_ID`),
  ADD KEY `Inventory_ID` (`Inventory_ID`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `small_inventory`
--
ALTER TABLE `small_inventory`
  ADD PRIMARY KEY (`Inventory_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `daily_sales`
--
ALTER TABLE `daily_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employee_ids`
--
ALTER TABLE `employee_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `guest`
--
ALTER TABLE `guest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `small_inventory`
--
ALTER TABLE `small_inventory`
  MODIFY `Inventory_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daily_sales`
--
ALTER TABLE `daily_sales`
  ADD CONSTRAINT `daily_sales_ibfk_1` FOREIGN KEY (`clerk_id`) REFERENCES `guest` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`clerk_id`) REFERENCES `guest` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_clerk` FOREIGN KEY (`clerk_id`) REFERENCES `guest` (`id`),
  ADD CONSTRAINT `fk_orders_guest` FOREIGN KEY (`guest_id`) REFERENCES `guest` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_orderitems_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_orderitems_product` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`id`);

--
-- Constraints for table `productingredients`
--
ALTER TABLE `productingredients`
  ADD CONSTRAINT `productingredients_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `productingredients_ibfk_2` FOREIGN KEY (`Inventory_ID`) REFERENCES `small_inventory` (`Inventory_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
