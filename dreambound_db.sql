-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 10:25 AM
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
-- Database: `dreambound_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `genre` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `sold_qty` int(11) DEFAULT 0,
  `book_img` varchar(255) DEFAULT 'img/default-book.jpg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `genre`, `price`, `stock`, `sold_qty`, `book_img`, `created_at`) VALUES
(1, 'The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 35.00, 120, 142, 'img/book1.jpg', '2026-06-04 08:27:17'),
(2, 'Atomic Habits', 'James Clear', 'Self-Help', 45.00, 80, 98, 'img/book2.jpg', '2026-06-04 08:27:17'),
(3, 'To Kill A Mockingbird', 'Harper Lee', 'Classic', 39.90, 2, 45, 'img/book3.jpg', '2026-06-04 08:27:17'),
(4, 'Hamlet', 'William Shakespeare', 'Drama', 25.00, 5, 12, 'img/book4.jpg', '2026-06-04 08:27:17'),
(5, 'Advanced Calculus', 'Gerald B. Folland', 'Academic', 85.00, 15, 0, 'img/book4.jpg', '2026-06-04 08:27:17'),
(6, 'Old Macroeconomics', 'N. Gregory Mankiw', 'Academic', 79.00, 20, 1, 'img/book4.jpg', '2026-06-04 08:27:17');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `book_id`, `quantity`, `created_at`) VALUES
(1, 6, 6, 1, '2026-06-06 05:28:47'),
(2, 6, 5, 1, '2026-06-06 05:29:00');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

CREATE TABLE `store_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `store_status` varchar(10) DEFAULT 'open',
  `maintenance_mode` varchar(10) DEFAULT 'inactive',
  `ship_semenanjung` decimal(10,2) DEFAULT 4.50,
  `ship_borneo` decimal(10,2) DEFAULT 8.50,
  `store_region` varchar(100) DEFAULT 'Malaysia (MYR - RM)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_settings`
--

INSERT INTO `store_settings` (`id`, `store_status`, `maintenance_mode`, `ship_semenanjung`, `ship_borneo`, `store_region`) VALUES
(1, 'open', 'inactive', 4.50, 8.50, 'Malaysia (MYR - RM)');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_message` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `log_message`, `created_at`) VALUES
(1, 'Admin James logged into Dashboard Status interface.', '2026-06-04 04:14:05'),
(2, 'Stock Update: \"The Great Gatsby\" updated (+50 items) by clerk Subari.', '2026-06-04 03:42:21'),
(3, 'Order invoice #1982 compiled and sent to packing station.', '2026-06-04 02:05:11'),
(4, 'Pengguna Fatihah Duereh telah log masuk ke dalam sistem.', '2026-06-04 09:25:25'),
(5, 'Admin Fatihah Duereh telah log keluar daripada sistem.', '2026-06-04 09:58:06'),
(6, 'User Fatihah Duereh has logged in.', '2026-06-04 10:13:39'),
(7, 'New user john smith (john@gmail.com) has registered.', '2026-06-04 12:39:58'),
(8, 'User john smith has logged in.', '2026-06-04 12:40:46'),
(9, 'User Fatihah Duereh has logged in.', '2026-06-04 12:51:06'),
(10, 'Admin Fatihah Duereh telah log keluar daripada sistem.', '2026-06-04 13:15:01'),
(11, 'User john smith has logged in.', '2026-06-04 13:17:47'),
(12, 'User Fatihah Duereh has logged in.', '2026-06-05 08:15:20'),
(13, 'User Fatihah Duereh has logged in.', '2026-06-05 10:46:47'),
(14, 'User Fatihah Duereh has logged in.', '2026-06-05 12:08:36'),
(15, 'User Fatihah Duereh has logged in.', '2026-06-05 17:54:19'),
(16, 'Admin Fatihah Duereh telah log keluar daripada sistem.', '2026-06-05 18:01:08'),
(17, 'User john smith has logged in.', '2026-06-06 01:26:29'),
(18, 'User john smith has logged in.', '2026-06-06 02:52:13'),
(19, 'User john smith has logged in.', '2026-06-06 05:28:39');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('base_shipping_rate', '5.00'),
('currency', 'MYR'),
('maintenance_mode', 'inactive'),
('store_status', 'open');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `customer_id_str` varchar(20) DEFAULT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'customer',
  `membership_tier` varchar(20) DEFAULT 'Regular',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `customer_id_str`, `fullname`, `email`, `password`, `phone`, `address`, `postcode`, `city`, `state`, `role`, `membership_tier`, `created_at`) VALUES
(5, NULL, 'Fatihah Duereh', 'fatihahduereh@gmail.com', '$2y$10$e6TInO1yAh5nAYg2jDFAOez6MDrPKeB.N5fNHNjaTgdefbW.Ca5Ru', '0190909876', NULL, NULL, NULL, NULL, 'admin', 'Regular', '2026-06-04 08:39:56'),
(6, '#CUST-0011', 'john smith', 'john@gmail.com', '$2y$10$DqJhm7b1DZe.Xliit8ewvePuLdTU2Le65SF2YVaGAKRIO0iGnffl2', '0187653456', NULL, NULL, NULL, NULL, 'customer', 'Regular', '2026-06-04 12:39:58'),
(7, '#CUST-0004', 'Siti Norhaliza', 'siti@gmail.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '011-2345678', NULL, NULL, NULL, NULL, 'customer', 'VIP', '2026-06-05 08:29:41'),
(8, '#CUST-0005', 'Muhammad Adam', 'adam.muhd@gmail.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '017-5554321', NULL, NULL, NULL, NULL, 'customer', 'Regular', '2026-06-05 08:29:41'),
(9, '#CUST-0006', 'Farah Diana', 'farah.d@yahoo.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '013-4448899', NULL, NULL, NULL, NULL, 'customer', 'New', '2026-06-05 08:29:41'),
(10, '#CUST-0007', 'Daniel Lee Kian', 'danielleekian@gmail.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '016-7772211', NULL, NULL, NULL, NULL, 'customer', 'VIP', '2026-06-05 08:29:41'),
(11, '#CUST-0008', 'Nurul Izzah', 'izzah_99@outlook.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '019-8883344', NULL, NULL, NULL, NULL, 'customer', 'Regular', '2026-06-05 08:29:41'),
(12, '#CUST-0009', 'Arvin Raj', 'arvinraj@gmail.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '012-9995566', NULL, NULL, NULL, NULL, 'customer', 'New', '2026-06-05 08:29:41'),
(13, '#CUST-0010', 'Aisha Humaira', 'aisha_h@gmail.com', '$2y$10$W5bWb7S3g4M6sD8f9g0h1uE4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k', '014-6667788', NULL, NULL, NULL, NULL, 'customer', 'Regular', '2026-06-05 08:29:41');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','flat') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `type`, `value`, `status`, `created_at`) VALUES
(1, 'DREAM5', 'percentage', 5.00, 'active', '2026-06-04 08:27:17'),
(2, 'BOOKWORM10', 'flat', 10.00, 'active', '2026-06-04 08:27:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `store_settings`
--
ALTER TABLE `store_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
