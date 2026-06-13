-- Pet Store Inventory Database Backup
-- Generated: 2026-06-04 10:40:24
-- Database: pet_store_inventory


CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO cart VALUES ('3', '4', '1', '1', '2025-04-15 15:25:50');
INSERT INTO cart VALUES ('62', '7', '1', '1', '2026-06-01 09:33:19');
INSERT INTO cart VALUES ('63', '12', '1', '1', '2026-06-01 14:58:08');
INSERT INTO cart VALUES ('65', '15', '1', '1', '2026-06-02 08:50:37');

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `category` enum('Food','Toy','Accessory') NOT NULL,
  `stock` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO products VALUES ('1', 'Infinity Cat Food 1kg', 'Infinity Cat Food provides balanced nutrition for a healthy and active cat. Made with quality ingredients for everyday wellness.', 'Food', '83', '120.00', '2026-01-01', 'infinity.png');
INSERT INTO products VALUES ('18', 'Signature Kidney & eye Care Cat Treat 50g', 'Signature Kidney & Eye Care Cat Treat supports kidney and eye health with carefully selected ingredients. A tasty and nutritious reward for your cat.', 'Food', '179', '130.00', '2026-03-15', 'Signature_Kidney___eye_Care_Cat_Treat-removebg-preview.png');
INSERT INTO products VALUES ('19', 'Pet Choice Adult Dog Food 15Kg', 'Pet Choice Adult Dog Food provides complete nutrition for adult dogs. Formulated to support daily health, energy, and overall well-being.', 'Food', '10', '1200.00', '2026-02-15', 'download.png');

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_credential` varchar(255) DEFAULT NULL,
  `delivery_location` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO transactions VALUES ('11', '2', '1', '1', '120.00', 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 20:17:25');
INSERT INTO transactions VALUES ('12', '2', '18', '1', '130.00', 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 20:17:25');
INSERT INTO transactions VALUES ('13', '2', '19', '1', '1200.00', 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 20:17:25');
INSERT INTO transactions VALUES ('14', '1', '1', '1', '120.00', 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 20:18:03');
INSERT INTO transactions VALUES ('15', '1', '18', '1', '130.00', 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 20:18:03');
INSERT INTO transactions VALUES ('16', '1', '19', '1', '1200.00', 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 20:18:03');
INSERT INTO transactions VALUES ('17', '2', '19', '1', '1200.00', 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 20:22:15');
INSERT INTO transactions VALUES ('18', '2', '18', '1', '130.00', 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 20:22:15');
INSERT INTO transactions VALUES ('19', '2', '1', '1', '120.00', 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 20:22:15');
INSERT INTO transactions VALUES ('20', '1', '1', '1', '120.00', 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 20:24:02');
INSERT INTO transactions VALUES ('21', '1', '18', '1', '130.00', 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 20:24:02');
INSERT INTO transactions VALUES ('22', '1', '19', '1', '1200.00', 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 20:24:02');
INSERT INTO transactions VALUES ('23', '1', '1', '1', '120.00', 'PayPal', 'asas', 'as', 'Pending', '2025-04-16 20:28:22');
INSERT INTO transactions VALUES ('24', '1', '1', '2', '240.00', 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 20:30:46');
INSERT INTO transactions VALUES ('25', '1', '18', '1', '130.00', 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 20:30:46');
INSERT INTO transactions VALUES ('26', '1', '19', '1', '1200.00', 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 20:30:46');
INSERT INTO transactions VALUES ('27', '1', '1', '1', '120.00', 'GCash', 'saa', 'asa', 'Pending', '2025-04-16 20:33:32');
INSERT INTO transactions VALUES ('28', '14', '18', '1', '130.00', 'GCash', '232', 'qwe', 'Pending', '2026-06-02 08:34:17');
INSERT INTO transactions VALUES ('29', '14', '18', '1', '130.00', 'MasterCard', '534', '223', 'Pending', '2026-06-02 08:38:40');
INSERT INTO transactions VALUES ('30', '14', '18', '1', '130.00', 'GCash', '324', '3r', 'Pending', '2026-06-02 08:40:44');
INSERT INTO transactions VALUES ('31', '14', '18', '1', '130.00', 'PayPal', '23', 'dsf', 'Pending', '2026-06-02 08:43:14');
INSERT INTO transactions VALUES ('32', '15', '1', '1', '120.00', 'GCash', '09171234567', '123 Main Street, Barangay San Jose, Manila, 1000', 'Pending', '2026-06-02 08:47:33');
INSERT INTO transactions VALUES ('33', '14', '18', '1', '130.00', 'GCash', 'wewe', '34', 'Pending', '2026-06-02 08:52:29');
INSERT INTO transactions VALUES ('34', '14', '18', '1', '130.00', 'Debit Card', '34567', 'fds', 'Pending', '2026-06-02 08:54:05');
INSERT INTO transactions VALUES ('35', '14', '18', '1', '130.00', 'GCash', '3456', '23456', 'Pending', '2026-06-02 09:05:04');
INSERT INTO transactions VALUES ('36', '14', '1', '1', '120.00', 'GCash', '324', '324', 'Pending', '2026-06-02 09:09:27');
INSERT INTO transactions VALUES ('37', '14', '1', '1', '120.00', 'GCash', '324', '324', 'Pending', '2026-06-02 09:12:19');
INSERT INTO transactions VALUES ('38', '16', '1', '1', '120.00', 'Apple Pay', '123', 'test', 'Cancelled', '2026-06-04 15:40:08');

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 20000.00,
  `profile_pic` varchar(255) DEFAULT NULL,
  `delivery_full_name` varchar(255) DEFAULT NULL,
  `delivery_phone` varchar(20) DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_postal_code` varchar(20) DEFAULT NULL,
  `delivery_address` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO users VALUES ('1', 'user1', '$2y$10$n6yzwVvGmPsf1LgGqRDyd.rP5lk3B18kk4FLfIjW/5/r8xAXLAgT.', 'user', '15290.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('2', 'admin1', '$2y$10$5mXF5yCq6t5OS22P11cLzOM5H0DZJ9j3leSa91wD90NpDgG/8Mw5e', 'admin', '17100.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('3', 'user2', '$2y$10$budUIkhwn312oWBMrwNRyOrrqGi7E1c3GT4aVjVef1Ngj9KQ7Zg2C', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('4', 'pads', '$2y$10$9vgzPaC.WOfDahghMWSlquMYEwfcvjsnfeFkbchqVb.2qBLRqL4k2', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('6', 'admin123', '$2y$10$c/u1atGaRA4oYPxd.H7HIOPtACkCR8RGX1GZTBcP7gtYZmFatKTQm', 'admin', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('7', 'user123', '$2y$10$KpJdjSQTfxvm7C2BADUpV.nEKAP9WfsnIG5TZqy58WWBfrEFyA2YC', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('8', 'test123', '$2y$10$iLxVWzW/TXxEPsVmF1XS8eKduaHjgX4F097gKoa6hjVE2Apb0zw0u', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('9', 'testuser123', '$2y$10$zvHc.EGa7H1gxKkYDLkhXOicpFHxJpWTqwrjBGE9uwz70o12gqhYq', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('12', 'newuser2026_updated', '$2y$10$JEycZLC8/xLGh3x/SEcsuO153Jf/NTO5eAk/.4BPj6bXUNlOddKfW', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('13', 'testuser789', '$2y$10$NHsabmnuQnI1G.eOw2QpDeTUErmBGQZzM4flfDg7DmW2Kejkh2XyS', 'user', '20000.00', '', '', '', '', '', '');
INSERT INTO users VALUES ('14', 'qweqwe', '$2y$10$ciTaCzf8ItdWLi.1I5LTCOCT8lOG5/rLs.wSeiyZRA7kycnq50wF.', 'user', '18790.00', 'uploads/profiles/profile_14_1780541335.png', '', '', '', '', '');
INSERT INTO users VALUES ('15', 'testuser', '$2y$10$5nPViDFySKnMZKr2kxiJWejTWmaFfNsu6Y1VKPC0AU.tk4oh5xUQW', 'user', '19865.60', '', '', '', '', '', '');
INSERT INTO users VALUES ('16', 'adminn', '$2y$10$whrDQE1czLyhaB7oeuEAouDXwbbmUiWDxC2V.PKgzQGta7XlFQ/eu', 'admin', '19865.60', '', '', '', '', '', '');
