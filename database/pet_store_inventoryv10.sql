-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 04, 2026 at 10:57 AM
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
-- Database: `pet_store_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `created_at`) VALUES
(3, 4, 1, 1, '2025-04-15 07:25:50'),
(62, 7, 1, 1, '2026-06-01 01:33:19'),
(63, 12, 1, 1, '2026-06-01 06:58:08'),
(65, 15, 1, 1, '2026-06-02 00:50:37');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `category` enum('Food','Toy','Accessory') NOT NULL,
  `stock` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `category`, `stock`, `price`, `expiry_date`, `image`) VALUES
(1, 'Infinity Cat Food 1kg', 'Infinity Cat Food provides balanced nutrition for a healthy and active cat. Made with quality ingredients for everyday wellness.', 'Food', 82, 120.00, '2026-01-01', 'infinity.png'),
(18, 'Signature Kidney & eye Care Cat Treat 50g', 'Signature Kidney & Eye Care Cat Treat supports kidney and eye health with carefully selected ingredients. A tasty and nutritious reward for your cat.', 'Food', 179, 130.00, '2026-03-15', 'Signature_Kidney___eye_Care_Cat_Treat-removebg-preview.png'),
(19, 'Pet Choice Adult Dog Food 15Kg', 'Pet Choice Adult Dog Food provides complete nutrition for adult dogs. Formulated to support daily health, energy, and overall well-being.', 'Food', 10, 1200.00, '2026-02-15', 'download.png');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_credential` varchar(255) DEFAULT NULL,
  `delivery_location` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `product_id`, `quantity`, `total_price`, `payment_method`, `payment_credential`, `delivery_location`, `status`, `transaction_date`) VALUES
(11, 2, 1, 1, 120.00, 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 12:17:25'),
(12, 2, 18, 1, 130.00, 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 12:17:25'),
(13, 2, 19, 1, 1200.00, 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 12:17:25'),
(14, 1, 1, 1, 120.00, 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 12:18:03'),
(15, 1, 18, 1, 130.00, 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 12:18:03'),
(16, 1, 19, 1, 1200.00, 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 12:18:03'),
(17, 2, 19, 1, 1200.00, 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 12:22:15'),
(18, 2, 18, 1, 130.00, 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 12:22:15'),
(19, 2, 1, 1, 120.00, 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 12:22:15'),
(20, 1, 1, 1, 120.00, 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 12:24:02'),
(21, 1, 18, 1, 130.00, 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 12:24:02'),
(22, 1, 19, 1, 1200.00, 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 12:24:02'),
(23, 1, 1, 1, 120.00, 'PayPal', 'asas', 'as', 'Pending', '2025-04-16 12:28:22'),
(24, 1, 1, 2, 240.00, 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 12:30:46'),
(25, 1, 18, 1, 130.00, 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 12:30:46'),
(26, 1, 19, 1, 1200.00, 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 12:30:46'),
(27, 1, 1, 1, 120.00, 'GCash', 'saa', 'asa', 'Pending', '2025-04-16 12:33:32'),
(28, 14, 18, 1, 130.00, 'GCash', '232', 'qwe', 'Pending', '2026-06-02 00:34:17'),
(29, 14, 18, 1, 130.00, 'MasterCard', '534', '223', 'Pending', '2026-06-02 00:38:40'),
(30, 14, 18, 1, 130.00, 'GCash', '324', '3r', 'Pending', '2026-06-02 00:40:44'),
(31, 14, 18, 1, 130.00, 'PayPal', '23', 'dsf', 'Pending', '2026-06-02 00:43:14'),
(32, 15, 1, 1, 120.00, 'GCash', '09171234567', '123 Main Street, Barangay San Jose, Manila, 1000', 'Pending', '2026-06-02 00:47:33'),
(33, 14, 18, 1, 130.00, 'GCash', 'wewe', '34', 'Pending', '2026-06-02 00:52:29'),
(34, 14, 18, 1, 130.00, 'Debit Card', '34567', 'fds', 'Pending', '2026-06-02 00:54:05'),
(35, 14, 18, 1, 130.00, 'GCash', '3456', '23456', 'Pending', '2026-06-02 01:05:04'),
(36, 14, 1, 1, 120.00, 'GCash', '324', '324', 'Pending', '2026-06-02 01:09:27'),
(37, 14, 1, 1, 120.00, 'GCash', '324', '324', 'Pending', '2026-06-02 01:12:19'),
(38, 16, 1, 1, 120.00, 'Apple Pay', '123', 'test', 'Cancelled', '2026-06-04 07:40:08'),
(39, 16, 1, 1, 120.00, 'GCash', 'paypalemail@gmail.com', 'test', 'Pending', '2026-06-04 08:48:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 20000.00,
  `profile_pic` varchar(255) DEFAULT NULL,
  `delivery_full_name` varchar(255) DEFAULT NULL,
  `delivery_phone` varchar(20) DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_postal_code` varchar(20) DEFAULT NULL,
  `delivery_address` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `balance`, `profile_pic`, `delivery_full_name`, `delivery_phone`, `delivery_city`, `delivery_postal_code`, `delivery_address`) VALUES
(1, 'user1', '$2y$10$n6yzwVvGmPsf1LgGqRDyd.rP5lk3B18kk4FLfIjW/5/r8xAXLAgT.', 'user', 15290.00, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'admin1', '$2y$10$5mXF5yCq6t5OS22P11cLzOM5H0DZJ9j3leSa91wD90NpDgG/8Mw5e', 'admin', 17100.00, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'user2', '$2y$10$budUIkhwn312oWBMrwNRyOrrqGi7E1c3GT4aVjVef1Ngj9KQ7Zg2C', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'pads', '$2y$10$9vgzPaC.WOfDahghMWSlquMYEwfcvjsnfeFkbchqVb.2qBLRqL4k2', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'admin123', '$2y$10$c/u1atGaRA4oYPxd.H7HIOPtACkCR8RGX1GZTBcP7gtYZmFatKTQm', 'admin', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'user123', '$2y$10$KpJdjSQTfxvm7C2BADUpV.nEKAP9WfsnIG5TZqy58WWBfrEFyA2YC', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'test123', '$2y$10$iLxVWzW/TXxEPsVmF1XS8eKduaHjgX4F097gKoa6hjVE2Apb0zw0u', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'testuser123', '$2y$10$zvHc.EGa7H1gxKkYDLkhXOicpFHxJpWTqwrjBGE9uwz70o12gqhYq', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'newuser2026_updated', '$2y$10$JEycZLC8/xLGh3x/SEcsuO153Jf/NTO5eAk/.4BPj6bXUNlOddKfW', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'testuser789', '$2y$10$NHsabmnuQnI1G.eOw2QpDeTUErmBGQZzM4flfDg7DmW2Kejkh2XyS', 'user', 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'qweqwe', '$2y$10$ciTaCzf8ItdWLi.1I5LTCOCT8lOG5/rLs.wSeiyZRA7kycnq50wF.', 'user', 18790.00, 'uploads/profiles/profile_14_1780541335.png', NULL, NULL, NULL, NULL, NULL),
(15, 'testuser', '$2y$10$5nPViDFySKnMZKr2kxiJWejTWmaFfNsu6Y1VKPC0AU.tk4oh5xUQW', 'user', 19865.60, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'adminn', '$2y$10$whrDQE1czLyhaB7oeuEAouDXwbbmUiWDxC2V.PKgzQGta7XlFQ/eu', 'admin', 19731.20, NULL, 'John Doe', '0987654321', 'Manila', '1000', 'test');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
