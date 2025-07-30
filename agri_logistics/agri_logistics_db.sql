-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2025 at 07:42 PM
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
-- Database: `agri_logistics_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `shipment_id` int(11) DEFAULT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`driver_id`, `user_id`, `first_name`, `last_name`, `license_number`, `phone_number`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(3, 14, 'Korim', 'Miya', 'DM224035P060', '01683674924', 'active', '2025-07-27 16:06:08', '2025-07-27 16:06:08', NULL, NULL),
(4, 15, 'Rohim', 'Ali', 'DM224535C014', '01834562354', 'active', '2025-07-27 16:21:30', '2025-07-27 16:21:30', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `stage` enum('post-harvest','processing','storage','in-transit','damaged','sold') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `location_id`, `quantity`, `unit`, `stage`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(2, 4, 5, 400.00, 'kg', 'storage', '2025-07-27 15:29:55', '2025-07-27 16:34:13', 1, 11),
(3, 5, 4, 750.00, 'kg', 'storage', '2025-07-27 15:34:49', '2025-07-27 16:34:25', 1, 11);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `type` enum('farm','warehouse','processing_plant','delivery_point','other') NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `name`, `address`, `type`, `latitude`, `longitude`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(4, 'warehouse kadamtali', 'meraj nagar cng stand, kadamtali, dhaka -1236', 'warehouse', 23.68797610, 90.45680751, '2025-07-27 15:20:31', '2025-07-27 15:20:31', 1, NULL),
(5, 'warehouse mirpur', '1038 E Monipur Rd, Dhaka 1216', 'warehouse', 23.79881533, 90.36162950, '2025-07-27 15:21:23', '2025-07-27 15:21:23', 1, NULL),
(6, 'sadik agro', 'Muhammadpur beriband beside bhanga masjid ,Dhaka, Bangladesh, Dhaka 1219', 'farm', 23.75911259, 90.35055159, '2025-07-27 15:22:58', '2025-07-27 15:22:58', 1, NULL),
(7, 'sarker agro', 'delduar, tangail', 'farm', 24.15316453, 89.97352906, '2025-07-27 15:25:28', '2025-07-27 15:25:28', 1, NULL),
(8, 'retailer address', 'baitul mukarram moshjid 6 no gate, gulistan, dhaka', 'delivery_point', 23.72947438, 90.41433278, '2025-07-27 16:17:30', '2025-07-27 16:17:30', 1, NULL),
(9, 'customer address', 'Azampur kachabazar, Uttara, Dhaka', 'delivery_point', 23.86817666, 90.41122713, '2025-07-27 16:18:21', '2025-07-27 16:18:21', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `total_amount`, `status`, `shipping_address`, `created_at`, `updated_at`, `order_date`, `created_by`, `updated_by`) VALUES
(7, 9, 3600.00, 'shipped', 'Azampur kachabazar, Uttara, Dhaka', '2025-07-27 16:09:54', '2025-07-27 16:41:16', '2025-07-27 16:09:54', 9, 1),
(8, 12, 1330.00, 'shipped', 'baitul mukarram moshjid 6 no gate, gulistan, dhaka', '2025-07-27 16:12:38', '2025-07-27 16:24:26', '2025-07-27 16:12:38', 12, 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_products`
--

CREATE TABLE `order_products` (
  `order_product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `price_at_order` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_products`
--

INSERT INTO `order_products` (`order_product_id`, `order_id`, `product_id`, `quantity`, `unit`, `price_at_order`) VALUES
(7, 7, 4, 40.00, 'kg', 90.00),
(8, 8, 5, 10.00, 'kg', 190.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `crop_type` varchar(100) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `planting_date` date DEFAULT NULL,
  `harvest_date` date DEFAULT NULL,
  `storage_requirements` text DEFAULT NULL,
  `shelf_life_days` int(11) DEFAULT NULL,
  `packaging_details` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `weight_per_unit` decimal(10,3) DEFAULT NULL COMMENT 'Weight of one unit of this product (e.g., in kg)',
  `volume_per_unit` decimal(10,3) DEFAULT NULL COMMENT 'Volume of one unit of this product (e.g., in m³)',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `batch_id` varchar(255) DEFAULT NULL,
  `price_per_unit` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `crop_type`, `product_name`, `planting_date`, `harvest_date`, `storage_requirements`, `shelf_life_days`, `packaging_details`, `description`, `created_at`, `updated_at`, `weight_per_unit`, `volume_per_unit`, `created_by`, `updated_by`, `batch_id`, `price_per_unit`) VALUES
(4, 'fruit', 'mango', '2025-06-01', '2025-07-20', 'Temp:25-35C,  Humidity:90% RH', 30, '20 KG crates', 'Mango desctiption here......', '2025-07-27 15:27:56', '2025-07-27 17:22:17', NULL, NULL, 1, 1, 'MNG-20250727-A', 90.00),
(5, 'fruit', 'orange', '2025-07-01', '2025-07-19', 'Temp:25-35C,  Humidity:90% RH', 30, '5kg crates', 'orange desctiption here....', '2025-07-27 15:28:58', '2025-07-27 17:22:37', NULL, NULL, 1, 1, 'ORG-20250727-A', 190.00);

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `shipment_id` int(11) NOT NULL,
  `origin_location_id` int(11) NOT NULL,
  `destination_location_id` int(11) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `planned_departure` datetime NOT NULL,
  `planned_arrival` datetime NOT NULL,
  `actual_departure` datetime DEFAULT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `status` enum('pending','assigned','picked_up','in_transit','delivered','delayed','cancelled') NOT NULL DEFAULT 'pending',
  `total_weight_kg` decimal(10,2) DEFAULT NULL,
  `total_volume_m3` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`shipment_id`, `origin_location_id`, `destination_location_id`, `vehicle_id`, `driver_id`, `planned_departure`, `planned_arrival`, `actual_departure`, `actual_arrival`, `status`, `total_weight_kg`, `total_volume_m3`, `notes`, `created_at`, `updated_at`, `created_by`, `updated_by`, `order_id`) VALUES
(8, 5, 9, 3, 3, '2025-07-27 22:44:00', '2025-07-28 22:44:00', NULL, NULL, 'assigned', 40.00, 1.00, 'delivery note here........', '2025-07-27 16:44:46', '2025-07-27 16:45:16', 1, 1, 7),
(9, 4, 8, 2, 4, '2025-07-27 22:45:00', '2025-07-28 22:45:00', NULL, NULL, 'picked_up', 10.00, 1.00, 'delivery note hereee.....', '2025-07-27 16:46:11', '2025-07-27 16:46:17', 1, 1, 8);

-- --------------------------------------------------------

--
-- Table structure for table `shipment_products`
--

CREATE TABLE `shipment_products` (
  `shipment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipment_products`
--

INSERT INTO `shipment_products` (`shipment_id`, `product_id`, `quantity`, `unit`) VALUES
(8, 4, 40.00, 'kg'),
(9, 5, 10.00, 'kg');

-- --------------------------------------------------------

--
-- Table structure for table `tracking_data`
--

CREATE TABLE `tracking_data` (
  `tracking_id` int(11) NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `status_update` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tracking_data`
--

INSERT INTO `tracking_data` (`tracking_id`, `shipment_id`, `timestamp`, `latitude`, `longitude`, `temperature`, `humidity`, `status_update`) VALUES
(3, 8, '2025-07-27 23:03:03', 23.81886675, 90.37793555, 31.00, 85.60, 'prochur bistir karone, onek jam lege geche... tai late hoite pare'),
(4, 9, '2025-07-27 23:04:58', 23.70517444, 90.44526617, 34.00, 90.00, 'hanif flyover e accident hoise tai gari daraiya ache.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','farm_manager','warehouse_manager','logistics_manager','driver','customer') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `customer_type` enum('direct','retailer') NOT NULL DEFAULT 'direct'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role`, `email`, `phone`, `created_at`, `updated_at`, `created_by`, `updated_by`, `customer_type`) VALUES
(1, 'admin', '$2y$10$l891hwXiSray2IaMDrQ4IOhdwqgZeKOJu7MnDhNpveeLTjJa7AmnW', 'admin', 'admin@gmail.com', '01611111111', '2025-07-23 16:29:05', '2025-07-27 13:59:03', 1, 1, 'direct'),
(9, 'customer', '$2y$10$Ld0raCzp.4BHOMzy7qhSCeV.eLvkusAL25RdDnt1E5sfwV2No6n4u', 'customer', 'customer@gmail.com', '01699999999', '2025-07-27 14:00:18', '2025-07-27 14:00:18', 1, NULL, 'direct'),
(10, 'farmManager', '$2y$10$iqZqUeACG26RskGRQkvCa..AIwL3ICqwXcpgQ3YjdgsXzE6pxFIZS', 'farm_manager', 'farmManager@gmail.com', '01600000000', '2025-07-27 14:01:40', '2025-07-27 14:01:40', 1, NULL, 'direct'),
(11, 'warehouseManager', '$2y$10$u39pKYlENK/0KuZtQ1L.K..nWfkK0PqImWDyozdYbaq9x9Va8bIgK', 'warehouse_manager', 'warehoueManager@gmail.com', '01688888888', '2025-07-27 14:03:13', '2025-07-27 14:03:13', 1, NULL, 'direct'),
(12, 'retailer', '$2y$10$rZvmUs5t7jgWeH3OPfls2.u7IYmih2StiKi9.JDwHBm3dM006rRd6', 'customer', 'retailer@gmail.com', '01744444444', '2025-07-27 14:04:06', '2025-07-27 14:04:06', 1, NULL, 'retailer'),
(13, 'logisticsManager', '$2y$10$lxRCrmtpwqMMedStcOLRieoVt8ZOn0mVyRYcy/pvhDH4BDS5XwvPa', 'logistics_manager', 'logisticsManager@gmail.com', '01566666666', '2025-07-27 14:05:03', '2025-07-27 14:05:03', 1, NULL, 'direct'),
(14, 'Driver', '$2y$10$zTkbq7zSa.pRgxLLevYy9OsU/4vNi43kbeMBDNUeHTljIr0Xfv8oW', 'driver', 'driver@gmail.com', '01683674924', '2025-07-27 16:05:15', '2025-07-27 16:05:27', 1, 1, 'direct'),
(15, 'driver1', '$2y$10$gUmv4U9H6fGU6Si9GdKB3.l2Mdu9UpvLtkNKQXuASUHypJ9PaIBSS', 'driver', 'driver1@gmail.com', '01834562354', '2025-07-27 16:20:21', '2025-07-27 16:20:21', 1, NULL, 'direct');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `license_plate` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `capacity_weight` decimal(10,2) DEFAULT NULL,
  `capacity_volume` decimal(10,2) DEFAULT NULL,
  `status` enum('available','in-use','maintenance','retired') NOT NULL DEFAULT 'available',
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `license_plate`, `type`, `capacity_weight`, `capacity_volume`, `status`, `current_latitude`, `current_longitude`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(2, 'DHAKA-ট-11-9999', 'Truck', 500.00, 4.00, 'available', 23.70517444, 90.44526617, '2025-07-27 15:57:02', '2025-07-27 17:04:58', 1, NULL),
(3, 'DHAKA-ট-11-9934', 'Truck', 500.00, 4.00, 'available', 23.81886675, 90.37793555, '2025-07-27 15:57:23', '2025-07-27 17:03:03', 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `shipment_id` (`shipment_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_drivers_created_by` (`created_by`),
  ADD KEY `fk_drivers_updated_by` (`updated_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `fk_inventory_created_by` (`created_by`),
  ADD KEY `fk_inventory_updated_by` (`updated_by`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `fk_locations_created_by` (`created_by`),
  ADD KEY `fk_locations_updated_by` (`updated_by`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `fk_orders_created_by` (`created_by`),
  ADD KEY `fk_orders_updated_by` (`updated_by`);

--
-- Indexes for table `order_products`
--
ALTER TABLE `order_products`
  ADD PRIMARY KEY (`order_product_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_products_created_by` (`created_by`),
  ADD KEY `fk_products_updated_by` (`updated_by`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`shipment_id`),
  ADD KEY `fk_shipments_created_by` (`created_by`),
  ADD KEY `fk_shipments_updated_by` (`updated_by`),
  ADD KEY `fk_shipments_order_id` (`order_id`);

--
-- Indexes for table `shipment_products`
--
ALTER TABLE `shipment_products`
  ADD PRIMARY KEY (`shipment_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tracking_data`
--
ALTER TABLE `tracking_data`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `shipment_id` (`shipment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_created_by` (`created_by`),
  ADD KEY `fk_users_updated_by` (`updated_by`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `fk_vehicles_created_by` (`created_by`),
  ADD KEY `fk_vehicles_updated_by` (`updated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_products`
--
ALTER TABLE `order_products`
  MODIFY `order_product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `shipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tracking_data`
--
ALTER TABLE `tracking_data`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `fk_drivers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drivers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `fk_locations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_locations_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_products`
--
ALTER TABLE `order_products`
  ADD CONSTRAINT `order_products_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `fk_shipments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipments_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `shipment_products`
--
ALTER TABLE `shipment_products`
  ADD CONSTRAINT `shipment_products_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipment_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `tracking_data`
--
ALTER TABLE `tracking_data`
  ADD CONSTRAINT `tracking_data_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vehicles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
