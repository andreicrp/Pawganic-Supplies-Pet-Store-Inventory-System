-- Pawganic Supplies DB Backup
-- Generated: 2026-06-13 18:19:58


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
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO cart VALUES ('3', '4', '1', '1', '2025-04-15 15:25:50');
INSERT INTO cart VALUES ('62', '7', '1', '1', '2026-06-01 09:33:19');
INSERT INTO cart VALUES ('63', '12', '1', '1', '2026-06-01 14:58:08');
INSERT INTO cart VALUES ('65', '15', '1', '1', '2026-06-02 08:50:37');
INSERT INTO cart VALUES ('67', '16', '19', '1', '2026-06-04 22:16:07');
INSERT INTO cart VALUES ('71', '21', '18', '1', '2026-06-05 19:55:56');
INSERT INTO cart VALUES ('73', '21', '1', '4', '2026-06-05 20:03:13');
INSERT INTO cart VALUES ('74', '21', '19', '2', '2026-06-05 20:04:26');
INSERT INTO cart VALUES ('88', '6', '1', '1', '2026-06-10 00:47:10');
INSERT INTO cart VALUES ('89', '14', '1', '1', '2026-06-12 01:52:00');
INSERT INTO cart VALUES ('90', '14', '18', '1', '2026-06-12 02:17:15');

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `expiry_date` datetime NOT NULL,
  `status` enum('active','expired','disabled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO coupons VALUES ('1', 'PAWGVTC4AX2O', '5.00', '2026-06-06 01:00:00', 'expired', '2026-06-05 00:39:50', '18', '', '0', ' TEST');

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO favorites VALUES ('2', '14', '18', '2026-06-07 13:53:46');
INSERT INTO favorites VALUES ('4', '14', '19', '2026-06-07 16:23:07');

CREATE TABLE `featured_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `featured_products_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO featured_products VALUES ('1', '21', '0', '2026-06-13 18:17:41');
INSERT INTO featured_products VALUES ('2', '20', '1', '2026-06-13 18:17:41');
INSERT INTO featured_products VALUES ('3', '19', '2', '2026-06-13 18:17:41');

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(255) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_username` (`ip_address`,`username`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO product_reviews VALUES ('2', '18', '6', 'admin123', '5', 'Good product!', '2026-06-12 15:36:22');
INSERT INTO product_reviews VALUES ('3', '18', '14', 'Anonymous', '5', 'Best!', '2026-06-12 17:36:45');

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `badge` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 5.00,
  `reviews_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO products VALUES ('1', 'Infinity Cat Food 1kg', 'Infinity Cat Food provides balanced nutrition for a healthy and active cat. Made with quality ingredients for everyday wellness.', 'Food', '79', '120.00', '2026-01-01', 'infinity.png', '100.00', 'SALE', '5.00', '0', '2026-06-12 15:44:44');
INSERT INTO products VALUES ('18', 'Signature Kidney & eye Care Cat Treat 50g', 'Signature Kidney & Eye Care Cat Treat supports kidney and eye health with carefully selected ingredients. A tasty and nutritious reward for your cat.', 'Food', '173', '130.00', '2026-03-15', 'Signature_Kidney___eye_Care_Cat_Treat-removebg-preview.png', '', '', '5.00', '2', '2026-06-12 15:44:44');
INSERT INTO products VALUES ('19', 'Pet Choice Adult Dog Food 15Kg', 'Pet Choice Adult Dog Food provides complete nutrition for adult dogs. Formulated to support daily health, energy, and overall well-being.', 'Food', '10', '1200.00', '2026-02-15', 'download.png', '', '', '5.00', '0', '2026-06-12 15:44:44');
INSERT INTO products VALUES ('20', 'Whiskas Cat Food Kitten Ocean Fish 1.1kg', 'Whiskas® Adult Chicken Flavor Jelly is a complete and balanced wet cat food made with real chicken and fish, enriched with 41 essential nutrients, Omega 6, biotin, and zinc to support the health, energy, skin, and coat of adult cats.', 'Food', '15', '462.00', '', 'whiskas.webp', '', 'NEW', '5.00', '0', '2026-06-13 17:42:46');
INSERT INTO products VALUES ('21', 'Fishies Fiesta Cat Food, Good Cat Grub 500g', 'Fishies Fiesta is a natural, slowly cooked cat food made with mixed fish, salmon, vegetables, and functional ingredients like taurine, lysine, moringa, turmeric, and yucca to support overall health, digestion, skin, coat, joints, and kidney function.', 'Food', '100', '298.00', '', 'Fishies Fiesta.png', '', '', '5.00', '0', '2026-06-13 18:08:37');

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
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO transactions VALUES ('11', '2', '1', '1', '120.00', 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 20:17:25', '0.00', '');
INSERT INTO transactions VALUES ('12', '2', '18', '1', '130.00', 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 20:17:25', '0.00', '');
INSERT INTO transactions VALUES ('13', '2', '19', '1', '1200.00', 'GCash', 'ewe', 'wew', 'Pending', '2025-04-16 20:17:25', '0.00', '');
INSERT INTO transactions VALUES ('14', '1', '1', '1', '120.00', 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 20:18:03', '0.00', '');
INSERT INTO transactions VALUES ('15', '1', '18', '1', '130.00', 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 20:18:03', '0.00', '');
INSERT INTO transactions VALUES ('16', '1', '19', '1', '1200.00', 'PayPal', 'asa', 'asas', 'Pending', '2025-04-16 20:18:03', '0.00', '');
INSERT INTO transactions VALUES ('17', '2', '19', '1', '1200.00', 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 20:22:15', '0.00', '');
INSERT INTO transactions VALUES ('18', '2', '18', '1', '130.00', 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 20:22:15', '0.00', '');
INSERT INTO transactions VALUES ('19', '2', '1', '1', '120.00', 'GCash', 'asa', 'asas', 'Pending', '2025-04-16 20:22:15', '0.00', '');
INSERT INTO transactions VALUES ('20', '1', '1', '1', '120.00', 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 20:24:02', '0.00', '');
INSERT INTO transactions VALUES ('21', '1', '18', '1', '130.00', 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 20:24:02', '0.00', '');
INSERT INTO transactions VALUES ('22', '1', '19', '1', '1200.00', 'GCash', 'aa', 'asas', 'Pending', '2025-04-16 20:24:02', '0.00', '');
INSERT INTO transactions VALUES ('23', '1', '1', '1', '120.00', 'PayPal', 'asas', 'as', 'Pending', '2025-04-16 20:28:22', '0.00', '');
INSERT INTO transactions VALUES ('24', '1', '1', '2', '240.00', 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 20:30:46', '0.00', '');
INSERT INTO transactions VALUES ('25', '1', '18', '1', '130.00', 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 20:30:46', '0.00', '');
INSERT INTO transactions VALUES ('26', '1', '19', '1', '1200.00', 'PayPal', 'asas', 'asas', 'Pending', '2025-04-16 20:30:46', '0.00', '');
INSERT INTO transactions VALUES ('27', '1', '1', '1', '120.00', 'GCash', 'saa', 'asa', 'Pending', '2025-04-16 20:33:32', '0.00', '');
INSERT INTO transactions VALUES ('28', '14', '18', '1', '130.00', 'GCash', '232', 'qwe', 'Pending', '2026-06-02 08:34:17', '0.00', '');
INSERT INTO transactions VALUES ('29', '14', '18', '1', '130.00', 'MasterCard', '534', '223', 'Pending', '2026-06-02 08:38:40', '0.00', '');
INSERT INTO transactions VALUES ('30', '14', '18', '1', '130.00', 'GCash', '324', '3r', 'Pending', '2026-06-02 08:40:44', '0.00', '');
INSERT INTO transactions VALUES ('31', '14', '18', '1', '130.00', 'PayPal', '23', 'dsf', 'Pending', '2026-06-02 08:43:14', '0.00', '');
INSERT INTO transactions VALUES ('32', '15', '1', '1', '120.00', 'GCash', '09171234567', '123 Main Street, Barangay San Jose, Manila, 1000', 'Pending', '2026-06-02 08:47:33', '0.00', '');
INSERT INTO transactions VALUES ('33', '14', '18', '1', '130.00', 'GCash', 'wewe', '34', 'Pending', '2026-06-02 08:52:29', '0.00', '');
INSERT INTO transactions VALUES ('34', '14', '18', '1', '130.00', 'Debit Card', '34567', 'fds', 'Pending', '2026-06-02 08:54:05', '0.00', '');
INSERT INTO transactions VALUES ('35', '14', '18', '1', '130.00', 'GCash', '3456', '23456', 'Pending', '2026-06-02 09:05:04', '0.00', '');
INSERT INTO transactions VALUES ('36', '14', '1', '1', '120.00', 'GCash', '324', '324', 'Pending', '2026-06-02 09:09:27', '0.00', '');
INSERT INTO transactions VALUES ('37', '14', '1', '1', '120.00', 'GCash', '324', '324', 'Pending', '2026-06-02 09:12:19', '0.00', '');
INSERT INTO transactions VALUES ('38', '16', '1', '1', '120.00', 'Apple Pay', '123', 'test', 'Cancelled', '2026-06-04 15:40:08', '0.00', '');
INSERT INTO transactions VALUES ('39', '16', '1', '1', '120.00', 'GCash', 'paypalemail@gmail.com', 'test', 'Pending', '2026-06-04 16:48:08', '0.00', '');
INSERT INTO transactions VALUES ('40', '17', '1', '1', '120.00', 'GCash', '09171234567', '123 Main Street, Barangay San Antonio, Manila', 'Pending', '2026-06-04 23:09:56', '0.00', '');
INSERT INTO transactions VALUES ('41', '17', '18', '1', '130.00', 'PayPal', 'test@paypal.com', '123 Main Street, Barangay San Antonio, Manila', 'Pending', '2026-06-04 23:10:46', '0.00', '');
INSERT INTO transactions VALUES ('42', '14', '1', '1', '120.00', 'GCash', '1233', '1243', 'Pending', '2026-06-04 23:14:25', '0.00', '');
INSERT INTO transactions VALUES ('43', '14', '18', '1', '130.00', 'MasterCard', '2133213', '124RFDE', 'Pending', '2026-06-04 23:21:55', '0.00', '');
INSERT INTO transactions VALUES ('44', '14', '18', '1', '130.00', 'GCash', '1234', '124RFDE', 'Pending', '2026-06-07 18:27:53', '0.00', '');
INSERT INTO transactions VALUES ('45', '14', '18', '1', '130.00', 'MasterCard', '2134234', '124RFDE', 'Pending', '2026-06-07 18:32:49', '0.00', '');
INSERT INTO transactions VALUES ('46', '14', '18', '1', '130.00', 'MasterCard', '2134234', '124RFDE', 'Pending', '2026-06-07 18:33:12', '0.00', '');
INSERT INTO transactions VALUES ('47', '14', '18', '1', '130.00', 'GCash', '213213', '124RFDE', 'Pending', '2026-06-08 01:48:56', '0.00', '');
INSERT INTO transactions VALUES ('48', '6', '1', '1', '100.00', 'GCash', '231123', 'paw street', 'Shipped', '2026-06-12 16:35:58', '0.00', '');

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `city` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `address` longtext NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO user_addresses VALUES ('1', '17', 'John Doe', '09171234567', 'Manila', '1000', '123 Main Street, Barangay San Antonio, Manila', '1', '2026-06-04 23:09:56');
INSERT INTO user_addresses VALUES ('5', '14', 'John admin', '0987654321', 'Manila', '1000', '124RFDE', '1', '2026-06-07 18:33:12');
INSERT INTO user_addresses VALUES ('15', '6', 'John admin', '0987654321', 'Manila', '1000', 'paw street', '1', '2026-06-12 16:35:58');

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
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO users VALUES ('1', 'user1', '$2y$10$n6yzwVvGmPsf1LgGqRDyd.rP5lk3B18kk4FLfIjW/5/r8xAXLAgT.', 'user', '15290.00', '', '', '', '', '', '', 'user1@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('2', 'admin1', '$2y$10$5mXF5yCq6t5OS22P11cLzOM5H0DZJ9j3leSa91wD90NpDgG/8Mw5e', 'admin', '17100.00', '', '', '', '', '', '', 'admin1@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('3', 'user2', '$2y$10$budUIkhwn312oWBMrwNRyOrrqGi7E1c3GT4aVjVef1Ngj9KQ7Zg2C', 'user', '20000.00', '', '', '', '', '', '', 'user2@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('4', 'pads', '$2y$10$9vgzPaC.WOfDahghMWSlquMYEwfcvjsnfeFkbchqVb.2qBLRqL4k2', 'user', '20000.00', '', '', '', '', '', '', 'pads@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('6', 'admin123', '$2y$10$e...s6gNOLkyr50b49eA0eK5oUEe5H9XjQjk6marrFHN5vxHx6uhe', 'admin', '19888.00', '', 'John admin', '0987654321', 'Manila', '1000', 'paw street', 'admin123@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('7', 'user123', '$2y$10$KpJdjSQTfxvm7C2BADUpV.nEKAP9WfsnIG5TZqy58WWBfrEFyA2YC', 'user', '20000.00', '', '', '', '', '', '', 'user123@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('8', 'test123', '$2y$10$iLxVWzW/TXxEPsVmF1XS8eKduaHjgX4F097gKoa6hjVE2Apb0zw0u', 'user', '20000.00', '', '', '', '', '', '', 'test123@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('9', 'testuser123', '$2y$10$zvHc.EGa7H1gxKkYDLkhXOicpFHxJpWTqwrjBGE9uwz70o12gqhYq', 'user', '20000.00', '', '', '', '', '', '', 'testuser123@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('12', 'newuser2026_updated', '$2y$10$JEycZLC8/xLGh3x/SEcsuO153Jf/NTO5eAk/.4BPj6bXUNlOddKfW', 'user', '20000.00', '', '', '', '', '', '', 'newuser2026_updated@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('13', 'testuser789', '$2y$10$NHsabmnuQnI1G.eOw2QpDeTUErmBGQZzM4flfDg7DmW2Kejkh2XyS', 'user', '20000.00', '', '', '', '', '', '', 'testuser789@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('14', 'qweqwe', '$2y$10$ciTaCzf8ItdWLi.1I5LTCOCT8lOG5/rLs.wSeiyZRA7kycnq50wF.', 'user', '17490.80', 'uploads/profiles/profile_14_1780541335.png', 'John admin', '0987654321', 'Manila', '1000', '124RFDE', 'qweqwe@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('15', 'testuser', '$2y$10$5nPViDFySKnMZKr2kxiJWejTWmaFfNsu6Y1VKPC0AU.tk4oh5xUQW', 'user', '19865.60', '', '', '', '', '', '', 'testuser@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('16', 'adminn', '$2y$10$whrDQE1czLyhaB7oeuEAouDXwbbmUiWDxC2V.PKgzQGta7XlFQ/eu', 'admin', '19731.20', '', 'John Doe', '0987654321', 'Manila', '1000', 'test', 'adminn@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('17', 'testuser2026', '$2y$10$UztBHyCzdvjmL0lLdjdL4.gvZlpraTPwFAF1loGv9AR8af.bCCC9K', 'user', '19720.00', '', 'John Doe', '09171234567', 'Manila', '1000', '123 Main Street, Barangay San Antonio, Manila', 'testuser2026@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('18', 'asdasd', '$2y$10$cOUmg7wSwnfe1UG88D3tRORzKk0T7omu0CII6gzy.iK5Vak0vlb6C', 'admin', '20000.00', '', '', '', '', '', '', 'asdasd@pawganic.local', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('20', 'newuserwuzhy8', '$2y$10$MHDGrASpMJjQvC7nc7d83u92zrD87TqsXesxjOZfBFdQRWgOZQebu', 'user', '20000.00', '', '', '', '', '', '', 'useromz66@test.com', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('21', 'user1780660072702', '$2y$10$vLUtvfg02jZhljArBCJbF.wbK5CsESzgkxR1SxLJRY9FDEieTaa8C', 'user', '20000.00', '', '', '', '', '', '', 'test1780660072702@test.com', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('23', 'Testuser12345', '$2y$10$yE533lsyQh/0F2RQlLg7uOHK.H5C9prd.CcGIBtdtGAGHktYOpCEu', 'user', '20000.00', '', '', '', '', '', '', 'admin4455@gmail.com', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('26', 'testuser_1048', '$2y$10$egHS.RkrRTivgkurMkDzoeioExgy0vdZBCqtFxW/fOG0BJDZsUp1S', 'user', '20000.00', '', '', '', '', '', '', 'test_6527@test.com', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('27', 'testuser_8181', '$2y$10$AGcejywQzrKFZZcpevce/eQtcyTSswYsmPMPN2wBa0npn9BndPIQm', 'user', '20000.00', '', '', '', '', '', '', 'test_5647@test.com', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('28', 'testuser_4611', '$2y$10$emYuK8xe6tsqgHnLtcB5Z.o0lpPwkOGzIeSv8T2iv0MAH78g0AkC2', 'user', '20000.00', '', '', '', '', '', '', 'test_1317@test.com', '2026-06-12 15:44:44');
INSERT INTO users VALUES ('30', 'Testuser67', '$2y$10$1wzyWsbug/jKbPdxR0f70.b3I/J00p20wukoQknXt5/rmEJlB5TSK', 'user', '20000.00', '', '', '', '', '', '', 'admin222@gmail.com', '2026-06-12 15:44:44');
