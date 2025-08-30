-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2025 at 02:50 AM
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
                           `driver_code` varchar(6) NOT NULL,
                           `user_id` int(11) DEFAULT NULL,
                           `first_name` varchar(100) NOT NULL,
                           `last_name` varchar(100) NOT NULL,
                           `license_number` varchar(50) NOT NULL,
                           `phone_number` varchar(20) DEFAULT NULL,
                           `email` varchar(100) DEFAULT NULL,
                           `vehicle_type` enum('truck','van','pickup','motorcycle') DEFAULT NULL,
                           `experience_years` int(2) DEFAULT NULL,
                           `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active',
                           `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                           `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                           `created_by` int(11) DEFAULT NULL,
                           `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`driver_id`, `driver_code`, `user_id`, `first_name`, `last_name`, `license_number`, `phone_number`, `email`, `vehicle_type`, `experience_years`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
                                                                                                                                                                                                                                              (3, 'D25001', 14, 'Korim', 'Miya', 'DM224035P060', '01683674924', 'korim.miya@example.com', 'truck', 5, 'active', '2025-07-27 16:06:08', '2025-07-27 16:06:08', NULL, NULL),
                                                                                                                                                                                                                                              (4, 'D25002', 15, 'Rohim', 'Ali', 'DM224535C014', '01834562354', 'rohim.ali@example.com', 'van', 3, 'active', '2025-07-27 16:21:30', '2025-07-27 16:21:30', NULL, NULL),
                                                                                                                                                                                                                                              (5, 'D25003', 16, 'anowar', 'ali', 'DM224565C013', '01635264784', 'anowar.ali@example.com', 'pickup', 2, 'active', '2025-07-27 18:36:50', '2025-08-12 18:50:53', NULL, 13);

-- --------------------------------------------------------

--
-- Table structure for table `dynamic_pricing`
--

CREATE TABLE `dynamic_pricing` (
                                   `pricing_id` int(11) NOT NULL,
                                   `product_id` int(11) NOT NULL,
                                   `base_price` decimal(10,2) NOT NULL,
                                   `current_price` decimal(10,2) NOT NULL,
                                   `price_factor` decimal(5,2) DEFAULT 1.00,
                                   `demand_level` enum('low','medium','high','critical') DEFAULT 'medium',
                                   `supply_level` enum('low','medium','high','excess') DEFAULT 'medium',
                                   `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                                   `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `farm_production`
--

CREATE TABLE `farm_production` (
                                   `production_id` int(11) NOT NULL,
                                   `production_code` varchar(6) NOT NULL,
                                   `farm_manager_id` int(11) NOT NULL,
                                   `product_id` int(11) NOT NULL,
                                   `seed_amount_kg` decimal(10,2) NOT NULL,
                                   `sowing_date` date DEFAULT NULL,
                                   `field_name` varchar(255) DEFAULT NULL,
                                   `expected_harvest_date` date DEFAULT NULL,
                                   `actual_harvest_date` date DEFAULT NULL,
                                   `harvested_amount_kg` decimal(10,2) DEFAULT NULL,
                                   `status` enum('planted','growing','ready_for_harvest','harvested','completed') NOT NULL DEFAULT 'planted',
                                   `notes` text DEFAULT NULL,
                                   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                                   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                                   `created_by` int(11) DEFAULT NULL,
                                   `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
                             `inventory_id` int(11) NOT NULL,
                             `inventory_code` varchar(6) NOT NULL,
                             `product_id` int(11) NOT NULL,
                             `location_id` int(11) NOT NULL,
                             `quantity_kg` decimal(10,2) NOT NULL,
                             `stage` enum('available','reserved','in-transit','sold','lost','damaged') NOT NULL DEFAULT 'available',
                             `order_id` int(11) DEFAULT NULL,
                             `expiry_date` date NOT NULL,
                             `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                             `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                             `created_by` int(11) DEFAULT NULL,
                             `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
                             `location_id` int(11) NOT NULL,
                             `location_code` varchar(6) NOT NULL,
                             `name` varchar(100) NOT NULL,
                             `address` text DEFAULT NULL,
                             `type` enum('farm','warehouse','processing_plant','delivery_point','other') NOT NULL,
                             `latitude` decimal(10,8) DEFAULT NULL,
                             `longitude` decimal(11,8) DEFAULT NULL,
                             `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                             `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                             `created_by` int(11) DEFAULT NULL,
                             `updated_by` int(11) DEFAULT NULL,
                             `capacity_kg` decimal(10,2) DEFAULT NULL,
                             `capacity_m3` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
                          `order_id` int(11) NOT NULL,
                          `order_code` varchar(6) NOT NULL,
                          `customer_id` int(11) NOT NULL,
                          `total_amount` decimal(10,2) NOT NULL,
                          `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
                          `shipping_address` text NOT NULL,
                          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                          `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
                          `created_by` int(11) DEFAULT NULL,
                          `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_products`
--

CREATE TABLE `order_products` (
                                  `order_product_id` int(11) NOT NULL,
                                  `order_id` int(11) NOT NULL,
                                  `product_id` int(11) NOT NULL,
                                  `quantity_kg` decimal(10,2) NOT NULL,
                                  `price_at_order` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
                            `product_id` int(11) NOT NULL,
                            `product_code` varchar(6) NOT NULL,
                            `name` varchar(150) NOT NULL,
                            `item_type` varchar(100) NOT NULL,
                            `batch_id` varchar(50) DEFAULT NULL,
                            `price_per_unit` decimal(10,2) DEFAULT NULL,
                            `packaging_details` varchar(255) DEFAULT NULL,
                            `description` text DEFAULT NULL,
                            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                            `created_by` int(11) DEFAULT NULL,
                            `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_requests`
--

CREATE TABLE `registration_requests` (
                                         `request_id` int(11) NOT NULL,
                                         `username` varchar(50) NOT NULL,
                                         `password_hash` varchar(255) NOT NULL,
                                         `customer_type` enum('direct','retailer') NOT NULL,
                                         `email` varchar(100) NOT NULL,
                                         `phone` varchar(20) DEFAULT NULL,
                                         `request_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
                             `shipment_id` int(11) NOT NULL,
                             `shipment_code` varchar(6) NOT NULL,
                             `origin_location_id` int(11) NOT NULL,
                             `destination_location_id` int(11) NOT NULL,
                             `vehicle_id` int(11) DEFAULT NULL,
                             `driver_id` int(11) DEFAULT NULL,
                             `planned_departure` datetime NOT NULL,
                             `planned_arrival` datetime NOT NULL,
                             `actual_departure` datetime DEFAULT NULL,
                             `actual_arrival` datetime DEFAULT NULL,
                             `status` enum('pending','assigned','in_transit','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'pending',
                             `total_weight_kg` decimal(10,2) DEFAULT NULL,
                             `total_volume_m3` decimal(10,2) DEFAULT NULL,
                             `notes` text DEFAULT NULL,
                             `damage_notes` text DEFAULT NULL,
                             `failure_photo` varchar(255) DEFAULT NULL COMMENT 'Photo path for failed shipments',
                             `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                             `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                             `created_by` int(11) DEFAULT NULL,
                             `updated_by` int(11) DEFAULT NULL,
                             `order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipment_products`
--

CREATE TABLE `shipment_products` (
                                     `shipment_id` int(11) NOT NULL,
                                     `product_id` int(11) NOT NULL,
                                     `quantity_kg` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supply_chain_events`
--

CREATE TABLE `supply_chain_events` (
                                       `event_id` int(11) NOT NULL,
                                       `event_type` enum('production_started','harvested','in_transit','delivered','damaged','expired','shipment_started','shipment_out_for_delivery','shipment_delivered','shipment_failed','shipment_reverted') NOT NULL,
                                       `product_id` int(11) DEFAULT NULL,
                                       `quantity_kg` decimal(10,2) DEFAULT NULL,
                                       `location_id` int(11) DEFAULT NULL,
                                       `shipment_id` int(11) DEFAULT NULL,
                                       `order_id` int(11) DEFAULT NULL,
                                       `event_date` timestamp NOT NULL DEFAULT current_timestamp(),
                                       `notes` text DEFAULT NULL,
                                       `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tracking_data`
--

CREATE TABLE `tracking_data` (
                                 `tracking_id` int(11) NOT NULL,
                                 `shipment_id` int(11) NOT NULL,
                                 `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
                                 `latitude` decimal(10,8) DEFAULT NULL,
                                 `longitude` decimal(11,8) DEFAULT NULL,
                                 `temperature` decimal(5,2) DEFAULT NULL,
                                 `humidity` decimal(5,2) DEFAULT NULL,
                                 `delivery_status` enum('in_transit','out_for_delivery','delivered','failed') DEFAULT 'in_transit',
                                 `order_notes` text DEFAULT NULL,
                                 `recorded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
                         `user_id` int(11) NOT NULL,
                         `user_code` varchar(6) NOT NULL,
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

INSERT INTO `users` (`user_id`, `user_code`, `username`, `password_hash`, `role`, `email`, `phone`, `created_at`, `updated_at`, `created_by`, `updated_by`, `customer_type`) VALUES
                                                                                                                                                                                 (1, 'U00001', 'admin', '$2y$10$l891hwXiSray2IaMDrQ4IOhdwqgZeKOJu7MnDhNpveeLTjJa7AmnW', 'admin', 'admin@gmail.com', '01611111111', '2025-07-23 16:29:05', '2025-07-27 13:59:03', 1, 1, 'direct'),
                                                                                                                                                                                 (9, 'U00009', 'customer', '$2y$10$Ld0raCzp.4BHOMzy7qhSCeV.eLvkusAL25RdDnt1E5sfwV2No6n4u', 'customer', 'customer@gmail.com', '01699999999', '2025-07-27 14:00:18', '2025-07-27 14:00:18', 1, NULL, 'direct'),
                                                                                                                                                                                 (10, 'U00010', 'farmManager', '$2y$10$iqZqUeACG26RskGRQkvCa..AIwL3ICqwXcpgQ3YjdgsXzE6pxFIZS', 'farm_manager', 'farmManager@gmail.com', '01600000000', '2025-07-27 14:01:40', '2025-07-27 14:01:40', 1, NULL, 'direct'),
                                                                                                                                                                                 (11, 'U00011', 'warehouseManager', '$2y$10$u39pKYlENK/0KuZtQ1L.K..nWfkK0PqImWDyozdYbaq9x9Va8bIgK', 'warehouse_manager', 'warehoueManager@gmail.com', '01688888888', '2025-07-27 14:03:13', '2025-07-27 14:03:13', 1, NULL, 'direct'),
                                                                                                                                                                                 (12, 'U00012', 'retailer', '$2y$10$rZvmUs5t7jgWeH3OPfls2.u7IYmih2StiKi9.JDwHBm3dM006rRd6', 'customer', 'retailer@gmail.com', '01744444444', '2025-07-27 14:04:06', '2025-07-27 14:04:06', 1, NULL, 'retailer'),
                                                                                                                                                                                 (13, 'U00013', 'logisticsManager', '$2y$10$lxRCrmtpwqMMedStcOLRieoVt8ZOn0mVyRYcy/pvhDH4BDS5XwvPa', 'logistics_manager', 'logisticsManager@gmail.com', '01566666666', '2025-07-27 14:05:03', '2025-07-27 14:05:03', 1, NULL, 'direct'),
                                                                                                                                                                                 (14, 'U00014', 'driver', '$2y$10$zTkbq7zSa.pRgxLLevYy9OsU/4vNi43kbeMBDNUeHTljIr0Xfv8oW', 'driver', 'driver@gmail.com', '01683674924', '2025-07-27 16:05:15', '2025-08-08 21:33:53', 1, 1, 'direct'),
                                                                                                                                                                                 (15, 'U00015', 'driver1', '$2y$10$gUmv4U9H6fGU6Si9GdKB3.l2Mdu9UpvLtkNKQXuASUHypJ9PaIBSS', 'driver', 'driver1@gmail.com', '01834562354', '2025-07-27 16:20:21', '2025-07-27 16:20:21', 1, NULL, 'direct'),
                                                                                                                                                                                 (16, 'U00016', 'driver2', '$2y$10$zkF4JVDnl61eAovFZ/Saouk8IRjE9IuIIkmo.4sIfWdo7W9tQ0rka', 'driver', 'driver2@gmail.com', '01635264783', '2025-07-27 18:34:55', '2025-07-27 18:36:09', 1, 1, 'direct');

-- --------------------------------------------------------

--
-- Table structure for table `user_assigned_locations`
--

CREATE TABLE `user_assigned_locations` (
                                           `user_id` int(11) NOT NULL,
                                           `location_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_dashboard_visits`
--

CREATE TABLE `user_dashboard_visits` (
                                         `visit_id` int(11) NOT NULL,
                                         `user_id` int(11) NOT NULL,
                                         `role` varchar(50) NOT NULL,
                                         `last_visit` timestamp NOT NULL DEFAULT current_timestamp(),
                                         `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_dashboard_visits`
--

INSERT INTO `user_dashboard_visits` (`visit_id`, `user_id`, `role`, `last_visit`, `created_at`) VALUES
                                                                                                    (1, 1, 'admin', '2025-08-14 00:27:59', '2025-08-13 18:18:24'),
                                                                                                    (2, 9, 'customer', '2025-08-13 19:50:07', '2025-08-13 18:18:24'),
                                                                                                    (3, 10, 'farm_manager', '2025-08-13 20:40:21', '2025-08-13 18:18:24'),
                                                                                                    (4, 11, 'warehouse_manager', '2025-08-13 23:37:36', '2025-08-13 18:18:24'),
                                                                                                    (5, 12, 'customer', '2025-08-13 23:37:49', '2025-08-13 18:18:24'),
                                                                                                    (6, 13, 'logistics_manager', '2025-08-13 23:40:12', '2025-08-13 18:18:24'),
                                                                                                    (7, 14, 'driver', '2025-08-13 23:03:12', '2025-08-13 18:18:24'),
                                                                                                    (8, 15, 'driver', '2025-08-14 00:17:20', '2025-08-13 18:18:24'),
                                                                                                    (9, 16, 'driver', '2025-08-13 18:18:24', '2025-08-13 18:18:24'),
                                                                                                    (10, 21, 'warehouse_manager', '2025-08-13 20:51:37', '2025-08-13 18:18:24'),
                                                                                                    (36, 23, 'customer', '2025-08-13 20:37:47', '2025-08-13 20:36:48');

-- --------------------------------------------------------
