CREATE DATABASE IF NOT EXISTS `sim_kopi_2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sim_kopi_2`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','kasir') NOT NULL DEFAULT 'kasir',
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255),
  `phone` varchar(20),
  `email` varchar(255),
  `address` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `category_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) DEFAULT '0.00',
  `stock` int NOT NULL DEFAULT '0',
  `min_stock` int DEFAULT '0',
  `barcode` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_barcode_unique` (`barcode`),
  KEY `products_category_id_foreign` (`category_id`),
  KEY `products_supplier_id_foreign` (`supplier_id`),
  KEY `products_name_index` (`name`),
  KEY `products_status_index` (`status`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `final_amount` decimal(10,2) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) DEFAULT '0.00',
  `payment_method` enum('cash','card','digital','transfer') DEFAULT 'cash',
  `status` enum('completed','cancelled','refunded') DEFAULT 'completed',
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoice_number_unique` (`invoice_number`),
  KEY `sales_user_id_foreign` (`user_id`),
  KEY `sales_created_at_index` (`created_at`),
  KEY `sales_status_index` (`status`),
  CONSTRAINT `sales_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_id_foreign` (`sale_id`),
  KEY `sale_items_product_id_foreign` (`product_id`),
  CONSTRAINT `sale_items_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sale_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` enum('purchase','sale','adjustment','initial','return') NOT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_product_id_foreign` (`product_id`),
  KEY `stock_movements_user_id_foreign` (`user_id`),
  KEY `stock_movements_supplier_id_foreign` (`supplier_id`),
  KEY `stock_movements_type_index` (`type`),
  KEY `stock_movements_created_at_index` (`created_at`),
  CONSTRAINT `stock_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `stock_movements_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@simkopi.com', '$2y$12$BBCtJFN5EFhl49C/OP63WevcnjVDTRxiNE9blY/soYGal79PFcLxi', 'admin', '081234567890', 'active', NOW(), NOW()),
(2, 'Kasir Satu', 'kasir@simkopi.com', '$2y$12$BBCtJFN5EFhl49C/OP63WevcnjVDTRxiNE9blY/soYGal79PFcLxi', 'kasir', '081234567891', 'active', NOW(), NOW()),
(3, 'Kasir Dua', 'kasir2@simkopi.com', '$2y$12$BBCtJFN5EFhl49C/OP63WevcnjVDTRxiNE9blY/soYGal79PFcLxi', 'kasir', '081234567892', 'active', NOW(), NOW());

INSERT INTO `categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Hot Coffee', 'Various hot coffee beverages', 'active', NOW(), NOW()),
(2, 'Cold Coffee', 'Iced and cold coffee drinks', 'active', NOW(), NOW()),
(3, 'Non Coffee', 'Tea, juice, and other beverages', 'active', NOW(), NOW()),
(4, 'Snacks', 'Light snacks and pastries', 'active', NOW(), NOW()),
(5, 'Desserts', 'Sweet treats and desserts', 'active', NOW(), NOW());

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PT Coffee Bean Indonesia', 'Budi Santoso', '021-12345678', 'supply@coffeebean.co.id', 'Jakarta Selatan', 'active', NOW(), NOW()),
(2, 'CV Milk & Dairy', 'Sari Dewi', '021-87654321', 'info@milkdairy.co.id', 'Bogor', 'active', NOW(), NOW()),
(3, 'Toko Snack Jaya', 'Ahmad Yani', '021-11223344', 'order@snackjaya.com', 'Depok', 'active', NOW(), NOW());

INSERT INTO `products` (`id`, `name`, `description`, `category_id`, `supplier_id`, `price`, `cost`, `stock`, `min_stock`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Espresso', 'Strong and bold coffee shot', 1, 1, 15000.00, 8000.00, 50, 10, 'active', NOW(), NOW()),
(2, 'Americano', 'Espresso with hot water', 1, 1, 18000.00, 10000.00, 45, 10, 'active', NOW(), NOW()),
(3, 'Cappuccino', 'Espresso with steamed milk and foam', 1, 1, 25000.00, 15000.00, 35, 8, 'active', NOW(), NOW()),
(4, 'Latte', 'Espresso with steamed milk', 1, 1, 28000.00, 16000.00, 40, 8, 'active', NOW(), NOW()),
(5, 'Mocha', 'Espresso with chocolate and steamed milk', 1, 1, 32000.00, 18000.00, 30, 5, 'active', NOW(), NOW()),
(6, 'Iced Coffee', 'Cold brewed coffee with ice', 2, 1, 20000.00, 12000.00, 25, 5, 'active', NOW(), NOW()),
(7, 'Iced Latte', 'Cold espresso with cold milk', 2, 1, 30000.00, 17000.00, 20, 5, 'active', NOW(), NOW()),
(8, 'Frappuccino', 'Blended iced coffee drink', 2, 1, 35000.00, 20000.00, 15, 3, 'active', NOW(), NOW()),
(9, 'Green Tea', 'Fresh green tea', 3, 2, 15000.00, 8000.00, 30, 10, 'active', NOW(), NOW()),
(10, 'Earl Grey Tea', 'Classic Earl Grey tea', 3, 2, 16000.00, 9000.00, 25, 8, 'active', NOW(), NOW()),
(11, 'Hot Chocolate', 'Rich hot chocolate drink', 3, 2, 22000.00, 13000.00, 20, 5, 'active', NOW(), NOW()),
(12, 'Fresh Orange Juice', 'Freshly squeezed orange juice', 3, 2, 18000.00, 10000.00, 15, 5, 'active', NOW(), NOW()),
(13, 'Croissant Plain', 'Fresh baked plain croissant', 4, 3, 20000.00, 12000.00, 20, 5, 'active', NOW(), NOW()),
(14, 'Croissant Chocolate', 'Croissant filled with chocolate', 4, 3, 25000.00, 15000.00, 15, 3, 'active', NOW(), NOW()),
(15, 'Sandwich Club', 'Club sandwich with chicken', 4, 3, 35000.00, 22000.00, 12, 3, 'active', NOW(), NOW()),
(16, 'Danish Pastry', 'Sweet Danish pastry', 4, 3, 18000.00, 11000.00, 18, 5, 'active', NOW(), NOW()),
(17, 'Cheesecake', 'New York style cheesecake', 5, 3, 28000.00, 16000.00, 10, 2, 'active', NOW(), NOW()),
(18, 'Tiramisu', 'Italian tiramisu dessert', 5, 3, 32000.00, 19000.00, 8, 2, 'active', NOW(), NOW()),
(19, 'Chocolate Brownie', 'Rich chocolate brownie', 5, 3, 22000.00, 13000.00, 15, 3, 'active', NOW(), NOW()),
(20, 'Apple Pie', 'Classic apple pie slice', 5, 3, 25000.00, 15000.00, 12, 3, 'active', NOW(), NOW());

INSERT INTO `stock_movements` (`product_id`, `type`, `quantity`, `reference_type`, `supplier_id`, `cost_price`, `notes`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'in', 50, 'initial', 1, 8000.00, 'Initial stock', 1, NOW(), NOW()),
(2, 'in', 45, 'initial', 1, 10000.00, 'Initial stock', 1, NOW(), NOW()),
(3, 'in', 35, 'initial', 1, 15000.00, 'Initial stock', 1, NOW(), NOW()),
(4, 'in', 40, 'initial', 1, 16000.00, 'Initial stock', 1, NOW(), NOW()),
(5, 'in', 30, 'initial', 1, 18000.00, 'Initial stock', 1, NOW(), NOW()),
(6, 'in', 25, 'initial', 1, 12000.00, 'Initial stock', 1, NOW(), NOW()),
(7, 'in', 20, 'initial', 1, 17000.00, 'Initial stock', 1, NOW(), NOW()),
(8, 'in', 15, 'initial', 1, 20000.00, 'Initial stock', 1, NOW(), NOW()),
(9, 'in', 30, 'initial', 2, 8000.00, 'Initial stock', 1, NOW(), NOW()),
(10, 'in', 25, 'initial', 2, 9000.00, 'Initial stock', 1, NOW(), NOW()),
(11, 'in', 20, 'initial', 2, 13000.00, 'Initial stock', 1, NOW(), NOW()),
(12, 'in', 15, 'initial', 2, 10000.00, 'Initial stock', 1, NOW(), NOW()),
(13, 'in', 20, 'initial', 3, 12000.00, 'Initial stock', 1, NOW(), NOW()),
(14, 'in', 15, 'initial', 3, 15000.00, 'Initial stock', 1, NOW(), NOW()),
(15, 'in', 12, 'initial', 3, 22000.00, 'Initial stock', 1, NOW(), NOW()),
(16, 'in', 18, 'initial', 3, 11000.00, 'Initial stock', 1, NOW(), NOW()),
(17, 'in', 10, 'initial', 3, 16000.00, 'Initial stock', 1, NOW(), NOW()),
(18, 'in', 8, 'initial', 3, 19000.00, 'Initial stock', 1, NOW(), NOW()),
(19, 'in', 15, 'initial', 3, 13000.00, 'Initial stock', 1, NOW(), NOW()),
(20, 'in', 12, 'initial', 3, 15000.00, 'Initial stock', 1, NOW(), NOW());

INSERT INTO `sales` (`id`, `invoice_number`, `user_id`, `customer_name`, `total_amount`, `final_amount`, `payment_amount`, `change_amount`, `payment_method`, `status`, `created_at`, `updated_at`) VALUES
(1, 'INV-20250104-0001', 2, 'John Doe', 43000.00, 43000.00, 50000.00, 7000.00, 'cash', 'completed', '2025-01-04 10:30:00', '2025-01-04 10:30:00'),
(2, 'INV-20250104-0002', 2, NULL, 25000.00, 25000.00, 25000.00, 0.00, 'card', 'completed', '2025-01-04 11:15:00', '2025-01-04 11:15:00'),
(3, 'INV-20250104-0003', 3, 'Jane Smith', 67000.00, 67000.00, 70000.00, 3000.00, 'cash', 'completed', '2025-01-04 14:20:00', '2025-01-04 14:20:00');

INSERT INTO `sale_items` (`sale_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`, `created_at`, `updated_at`) VALUES
(1, 3, 'Cappuccino', 25000.00, 1, 25000.00, '2025-01-04 10:30:00', '2025-01-04 10:30:00'),
(1, 13, 'Croissant Plain', 18000.00, 1, 18000.00, '2025-01-04 10:30:00', '2025-01-04 10:30:00'),
(2, 3, 'Cappuccino', 25000.00, 1, 25000.00, '2025-01-04 11:15:00', '2025-01-04 11:15:00'),
(3, 4, 'Latte', 28000.00, 1, 28000.00, '2025-01-04 14:20:00', '2025-01-04 14:20:00'),
(3, 5, 'Mocha', 32000.00, 1, 32000.00, '2025-01-04 14:20:00', '2025-01-04 14:20:00'),
(3, 19, 'Chocolate Brownie', 22000.00, 1, 22000.00, '2025-01-04 14:20:00', '2025-01-04 14:20:00');
