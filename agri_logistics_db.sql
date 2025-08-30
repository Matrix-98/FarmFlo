-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 22, 2025 at 03:42 PM
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
-- Table structure for table `chart_values`
--

CREATE TABLE `chart_values` (
  `id` int(11) NOT NULL,
  `chart_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `series` varchar(50) DEFAULT NULL,
  `value` decimal(15,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chart_values`
--

INSERT INTO `chart_values` (`id`, `chart_key`, `label`, `series`, `value`, `updated_at`) VALUES
(22, 'inventory_by_location', 'Tejgaon Warehouse', 'on_time', 10000.00, '2025-08-22 11:41:10'),
(23, 'inventory_by_location', 'CDA Port Storage Facility', 'on_time', 5000.00, '2025-08-22 11:41:25'),
(24, 'inventory_by_location', 'Maati O Manush Agro', 'on_time', 1500.00, '2025-08-22 11:41:37'),
(25, 'top_products', 'Miniket Rice', 'on_time', 550000.00, '2025-08-22 11:43:26'),
(26, 'top_products', 'Himsagar Mango', 'on_time', 425000.00, '2025-08-22 11:43:39'),
(29, 'top_products', 'Diamond Potato', 'on_time', 610000.00, '2025-08-22 11:46:25'),
(33, 'monthly_shipment_volume', 'March', 'on_time', 180.00, '2025-08-22 11:48:37'),
(34, 'monthly_shipment_volume', 'April', 'on_time', 240.00, '2025-08-22 11:48:47'),
(35, 'monthly_shipment_volume', 'May', 'on_time', 300.00, '2025-08-22 11:48:59'),
(36, 'monthly_shipment_volume', 'June', 'on_time', 250.00, '2025-08-22 11:49:12'),
(37, 'driver_delivery_performance', 'Anowar Ali', 'on_time', 95.00, '2025-08-22 11:51:51'),
(38, 'driver_delivery_performance', 'Anowar Ali', 'delayed', 5.00, '2025-08-22 11:52:01'),
(39, 'driver_delivery_performance', 'Rohim Ali', 'on_time', 70.00, '2025-08-22 11:52:20'),
(41, 'driver_delivery_performance', 'Rohim Ali', 'delayed', 30.00, '2025-08-22 11:52:48'),
(42, 'driver_delivery_performance', 'Korim Miya', 'on_time', 56.00, '2025-08-22 11:53:11'),
(43, 'driver_delivery_performance', 'Korim Miya', 'delayed', 60.00, '2025-08-22 11:53:18'),
(44, 'inventory_value_by_stage', 'Stored', 'on_time', 850000.00, '2025-08-22 11:54:52'),
(45, 'inventory_value_by_stage', 'In Transit', 'on_time', 400000.00, '2025-08-22 11:55:02'),
(46, 'inventory_value_by_stage', 'Damaged', 'on_time', 50000.00, '2025-08-22 11:55:11'),
(47, 'inventory_value_by_stage', 'Spoiled', 'on_time', 20000.00, '2025-08-22 11:55:20');

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

--
-- Dumping data for table `farm_production`
--

INSERT INTO `farm_production` (`production_id`, `production_code`, `farm_manager_id`, `product_id`, `seed_amount_kg`, `sowing_date`, `field_name`, `expected_harvest_date`, `actual_harvest_date`, `harvested_amount_kg`, `status`, `notes`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(17, 'FP2405', 10, 18, 50.00, '2025-08-20', 'North Field A', '2025-12-20', NULL, NULL, 'planted', 'Sown with high-yield variety seeds. Irrigation is underway.', '2025-08-22 11:27:46', '2025-08-22 12:34:54', 1, 1),
(18, 'FP2403', 10, 19, 0.10, '2025-08-07', 'Mango Orchard 2', '2026-04-21', NULL, NULL, 'planted', 'Young trees planted, regular pruning and pest control planned for the upcoming months.', '2025-08-22 11:29:29', '2025-08-22 12:34:27', 1, 1),
(50, 'FP2504', 10, 20, 50.00, '2025-08-22', 'North field', '2025-11-19', NULL, NULL, 'planted', NULL, '2025-08-22 12:50:15', '2025-08-22 12:50:15', 10, NULL);

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

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `inventory_code`, `product_id`, `location_id`, `quantity_kg`, `stage`, `order_id`, `expiry_date`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(134, 'I25001', 18, 30, 10000.00, 'available', NULL, '2026-07-23', '2025-08-22 11:34:04', '2025-08-22 11:34:04', 1, NULL),
(135, 'I25002', 19, 31, 5000.00, 'available', NULL, '2025-09-26', '2025-08-22 11:36:27', '2025-08-22 11:36:27', 1, NULL),
(136, 'I25003', 20, 32, 1500.00, 'available', NULL, '2025-10-24', '2025-08-22 11:37:39', '2025-08-22 11:37:39', 1, NULL),
(137, 'I25004', 20, 30, 10.00, 'available', NULL, '2025-09-21', '2025-08-22 13:22:19', '2025-08-22 13:27:49', 1, 1),
(138, 'I25005', 20, 30, 500.00, 'available', NULL, '2025-09-21', '2025-08-22 13:27:14', '2025-08-22 13:27:14', 1, NULL),
(139, 'I25006', 20, 30, 40.00, 'sold', 87, '2025-09-21', '2025-08-22 13:27:49', '2025-08-22 13:34:46', 1, 16);

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

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `location_code`, `name`, `address`, `type`, `latitude`, `longitude`, `created_at`, `updated_at`, `created_by`, `updated_by`, `capacity_kg`, `capacity_m3`) VALUES
(30, 'L25001', 'Tejgaon Warehouse', '123 Industrial Area, Tejgaon, Dhaka 1208', 'warehouse', 23.76670000, 90.39570000, '2025-08-22 11:05:41', '2025-08-22 11:35:09', 1, 1, 50000.00, 80.00),
(31, 'L25002', 'CDA Port Storage Facility', 'SK Mujib Road, Agrabad Commercial Area, Chattogram', 'warehouse', 22.33910000, 91.83170000, '2025-08-22 11:06:30', '2025-08-22 11:35:46', 1, 1, 75000.00, 90.00),
(32, 'L25003', 'Sylhet North Depot', 'Airport Road, Akhalia, Sylhet 3100', 'warehouse', 24.91900000, 91.86870000, '2025-08-22 11:07:16', '2025-08-22 11:35:29', 1, 1, 30000.00, 60.00),
(33, 'L25004', 'Green Acres Farm', 'Rajendrapur Cantonment, Sreepur, Gazipur', 'farm', 24.16230000, 90.43570000, '2025-08-22 11:11:14', '2025-08-22 11:11:14', 1, NULL, 0.00, 0.00),
(34, 'L25005', 'Shobuj Krishi Farm', 'Chirirbandar Upazila, Dinajpur 5240', 'farm', 25.68310000, 88.63150000, '2025-08-22 11:11:48', '2025-08-22 11:11:48', 1, NULL, 0.00, 0.00),
(35, 'L25006', 'Maati O Manush Agro', 'Sherpur, Bogra 5840', 'farm', 24.77350000, 89.41210000, '2025-08-22 11:12:10', '2025-08-22 11:12:10', 1, NULL, 0.00, 0.00),
(36, 'L25007', 'ACI Processing Unit', 'Savar Industrial Estate, Dhaka', 'processing_plant', 23.86470000, 90.26490000, '2025-08-22 11:16:58', '2025-08-22 11:16:58', 1, NULL, 0.00, 0.00),
(37, 'L25008', 'Pran Agro Processing', 'Ghorashal-Palash Road, Polash, Gazipur 1610', 'processing_plant', 24.10320000, 90.58470000, '2025-08-22 11:23:04', '2025-08-22 11:23:04', 1, NULL, 0.00, 0.00),
(38, 'L25009', 'Akij Food & Beverage', 'Kalurghat Industrial Area, Chattogram 4212', 'processing_plant', 22.35520000, 91.86770000, '2025-08-22 11:23:28', '2025-08-22 11:23:28', 1, NULL, 0.00, 0.00),
(39, '', 'Customer Delivery Address', 'Dynamic delivery address from order', 'delivery_point', NULL, NULL, '2025-08-22 13:33:09', '2025-08-22 13:33:09', NULL, NULL, NULL, NULL);

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

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_code`, `customer_id`, `total_amount`, `status`, `shipping_address`, `created_at`, `updated_at`, `order_date`, `created_by`, `updated_by`) VALUES
(87, 'O25001', 1, 1000.00, '', 'lkjhlkjhkjh', '2025-08-22 13:27:49', '2025-08-22 13:33:09', '2025-08-22 13:27:49', 1, 1);

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

--
-- Dumping data for table `order_products`
--

INSERT INTO `order_products` (`order_product_id`, `order_id`, `product_id`, `quantity_kg`, `price_at_order`) VALUES
(117, 87, 20, 40.00, 25.00);

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

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_code`, `name`, `item_type`, `batch_id`, `price_per_unit`, `packaging_details`, `description`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(18, 'P25001', 'Miniket Rice', 'Grains', 'BATCH001', 65.00, '50kg sacks', 'ocally sourced Miniket rice from the current harvest, known for its fine grain and aroma.', '2025-08-22 11:24:53', '2025-08-22 11:24:53', 1, NULL),
(19, 'P25002', 'Himsagar Mango', 'Fruits', 'HM-2025', 80.00, '25kg wooden crates', 'Freshly harvested, premium quality Himsagar mangoes from Chapainawabganj, known for their sweetness.', '2025-08-22 11:25:36', '2025-08-22 11:25:36', 1, NULL),
(20, 'P25003', 'Diamond Potato', 'Vegetables', 'DP-JUL2025', 25.00, '10kg mesh bags', 'High-grade Diamond variety potatoes, ideal for cooking and storage. Sourced from Bogra.', '2025-08-22 11:26:03', '2025-08-22 11:26:26', 1, 1);

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
  `order_id` int(11) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL COMMENT 'Links to shipment_requests table for farm request shipments'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`shipment_id`, `shipment_code`, `origin_location_id`, `destination_location_id`, `vehicle_id`, `driver_id`, `planned_departure`, `planned_arrival`, `actual_departure`, `actual_arrival`, `status`, `total_weight_kg`, `total_volume_m3`, `notes`, `damage_notes`, `failure_photo`, `created_at`, `updated_at`, `created_by`, `updated_by`, `order_id`, `request_id`) VALUES
(63, 'S25001', 33, 30, NULL, 5, '2025-08-23 08:00:00', '2025-08-22 13:22:00', NULL, '2025-08-22 19:22:19', 'delivered', 50.00, 0.05, 'Created from shipment request: SR2508001', NULL, NULL, '2025-08-22 13:21:52', '2025-08-22 13:22:19', 1, 1, NULL, NULL),
(64, 'S25002', 33, 30, NULL, 3, '2025-08-23 08:00:00', '2025-08-22 13:27:00', NULL, '2025-08-22 19:27:14', 'delivered', 500.00, 0.50, 'Created from shipment request: SR2508002', NULL, NULL, '2025-08-22 13:26:46', '2025-08-22 13:27:14', 1, 1, NULL, NULL),
(65, 'S25003', 36, 39, 3, 5, '2025-08-22 19:32:00', '2025-08-22 13:34:00', NULL, '2025-08-22 19:34:46', 'delivered', NULL, NULL, '', NULL, NULL, '2025-08-22 13:33:09', '2025-08-22 13:34:46', 1, 16, 87, NULL);

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
-- Table structure for table `shipment_requests`
--

CREATE TABLE `shipment_requests` (
  `request_id` int(11) NOT NULL,
  `request_code` varchar(20) NOT NULL,
  `production_id` int(11) NOT NULL,
  `farm_manager_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_kg` decimal(10,2) NOT NULL,
  `request_date` date NOT NULL,
  `preferred_pickup_date` date NOT NULL,
  `status` enum('pending','approved','rejected','converted_to_order') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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

--
-- Dumping data for table `supply_chain_events`
--

INSERT INTO `supply_chain_events` (`event_id`, `event_type`, `product_id`, `quantity_kg`, `location_id`, `shipment_id`, `order_id`, `event_date`, `notes`, `created_by`) VALUES
(80, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 20:50:04', NULL, 1),
(81, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 20:51:07', NULL, 1),
(82, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:04:42', NULL, 1),
(83, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:04:51', NULL, 1),
(84, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:06:46', NULL, 1),
(85, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:11:38', NULL, 1),
(86, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:11:44', NULL, 1),
(87, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:18:16', NULL, 1),
(88, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:18:25', NULL, 1),
(89, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:30:06', NULL, 1),
(90, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:31:49', NULL, 1),
(91, '', NULL, NULL, NULL, NULL, NULL, '2025-08-21 21:31:50', NULL, 1),
(92, '', NULL, NULL, NULL, 63, NULL, '2025-08-22 13:22:19', NULL, 1),
(93, '', NULL, NULL, NULL, 63, NULL, '2025-08-22 13:22:19', NULL, 1),
(94, '', NULL, NULL, NULL, 64, NULL, '2025-08-22 13:27:14', NULL, 1),
(95, '', NULL, NULL, NULL, 64, NULL, '2025-08-22 13:27:14', NULL, 1),
(96, '', NULL, NULL, NULL, 65, NULL, '2025-08-22 13:34:46', NULL, 16);

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

--
-- Dumping data for table `tracking_data`
--

INSERT INTO `tracking_data` (`tracking_id`, `shipment_id`, `recorded_at`, `latitude`, `longitude`, `temperature`, `humidity`, `delivery_status`, `order_notes`, `recorded_by`) VALUES
(21, 65, '2025-08-22 15:34:34', 45.00000000, 23.00000000, 56.00, 56.00, 'in_transit', 'adsfa', 16);

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

--
-- Dumping data for table `user_assigned_locations`
--

INSERT INTO `user_assigned_locations` (`user_id`, `location_id`) VALUES
(11, 30),
(11, 31),
(11, 32);

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
(1, 1, 'admin', '2025-08-22 13:41:48', '2025-08-13 18:18:24'),
(2, 9, 'customer', '2025-08-21 13:29:19', '2025-08-13 18:18:24'),
(3, 10, 'farm_manager', '2025-08-22 13:22:54', '2025-08-13 18:18:24'),
(4, 11, 'warehouse_manager', '2025-08-13 23:37:36', '2025-08-13 18:18:24'),
(5, 12, 'customer', '2025-08-13 23:37:49', '2025-08-13 18:18:24'),
(6, 13, 'logistics_manager', '2025-08-22 13:38:50', '2025-08-13 18:18:24'),
(7, 14, 'driver', '2025-08-22 13:33:43', '2025-08-13 18:18:24'),
(8, 15, 'driver', '2025-08-22 13:34:02', '2025-08-13 18:18:24'),
(9, 16, 'driver', '2025-08-22 13:34:13', '2025-08-13 18:18:24'),
(10, 21, 'warehouse_manager', '2025-08-13 20:51:37', '2025-08-13 18:18:24'),
(36, 23, 'customer', '2025-08-13 20:37:47', '2025-08-13 20:36:48');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `vehicle_code` varchar(6) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `license_plate` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `fuel_type` enum('diesel','petrol','electric','hybrid','lpg') DEFAULT NULL,
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

INSERT INTO `vehicles` (`vehicle_id`, `vehicle_code`, `user_id`, `license_plate`, `type`, `manufacturer`, `model`, `year`, `fuel_type`, `capacity_weight`, `capacity_volume`, `status`, `current_latitude`, `current_longitude`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(2, 'V25001', 16, 'DHAKA-ট-11-9999', 'Truck', 'Manufacturer A', 'Model X', 2020, 'diesel', 500.00, 4.00, 'available', 23.70517444, 90.44526617, '2025-07-27 15:57:02', '2025-08-12 18:51:30', 1, 13),
(3, 'V25002', 15, 'DHAKA-ট-11-9934', 'Truck', 'Manufacturer B', 'Model Y', 2019, 'petrol', 500.00, 4.00, 'available', 99.99999999, 999.99999999, '2025-07-27 15:57:23', '2025-08-12 18:51:22', 1, 13),
(4, 'V25003', 14, 'DHAKA-ট-11-9945', 'Truck', 'Manufacturer C', 'Model Z', 2024, 'electric', 500.00, 4.00, 'available', 45.00000000, 67.00000000, '2025-07-27 18:31:50', '2025-08-12 19:05:05', 1, 13);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chart_values`
--
ALTER TABLE `chart_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chart_key` (`chart_key`);

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
  ADD UNIQUE KEY `driver_code` (`driver_code`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_drivers_created_by` (`created_by`),
  ADD KEY `fk_drivers_updated_by` (`updated_by`),
  ADD KEY `idx_drivers_driver_code` (`driver_code`),
  ADD KEY `idx_drivers_status` (`status`),
  ADD KEY `idx_drivers_vehicle_type` (`vehicle_type`),
  ADD KEY `idx_drivers_user_id` (`user_id`);

--
-- Indexes for table `dynamic_pricing`
--
ALTER TABLE `dynamic_pricing`
  ADD PRIMARY KEY (`pricing_id`),
  ADD KEY `fk_dynamic_pricing_product` (`product_id`),
  ADD KEY `fk_dynamic_pricing_created_by` (`created_by`);

--
-- Indexes for table `farm_production`
--
ALTER TABLE `farm_production`
  ADD PRIMARY KEY (`production_id`),
  ADD UNIQUE KEY `production_code` (`production_code`),
  ADD UNIQUE KEY `unique_production_code` (`production_code`),
  ADD KEY `fk_fp_farm_manager` (`farm_manager_id`),
  ADD KEY `fk_fp_product` (`product_id`),
  ADD KEY `fk_fp_created_by` (`created_by`),
  ADD KEY `fk_fp_updated_by` (`updated_by`),
  ADD KEY `idx_farm_production_production_code` (`production_code`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `inventory_code` (`inventory_code`),
  ADD KEY `fk_inventory_created_by` (`created_by`),
  ADD KEY `fk_inventory_updated_by` (`updated_by`),
  ADD KEY `fk_inventory_product_id` (`product_id`),
  ADD KEY `idx_inventory_inventory_code` (`inventory_code`),
  ADD KEY `idx_inventory_order_id` (`order_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`),
  ADD UNIQUE KEY `location_code` (`location_code`),
  ADD KEY `fk_locations_created_by` (`created_by`),
  ADD KEY `fk_locations_updated_by` (`updated_by`),
  ADD KEY `idx_locations_location_code` (`location_code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `fk_orders_created_by` (`created_by`),
  ADD KEY `fk_orders_updated_by` (`updated_by`),
  ADD KEY `idx_orders_order_code` (`order_code`);

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
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `fk_products_created_by` (`created_by`),
  ADD KEY `fk_products_updated_by` (`updated_by`),
  ADD KEY `idx_products_product_code` (`product_code`),
  ADD KEY `idx_products_batch_id` (`batch_id`);

--
-- Indexes for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`shipment_id`),
  ADD UNIQUE KEY `shipment_code` (`shipment_code`),
  ADD KEY `fk_shipments_created_by` (`created_by`),
  ADD KEY `fk_shipments_updated_by` (`updated_by`),
  ADD KEY `fk_shipments_order_id` (`order_id`),
  ADD KEY `idx_shipments_shipment_code` (`shipment_code`),
  ADD KEY `fk_shipments_request_id` (`request_id`);

--
-- Indexes for table `shipment_products`
--
ALTER TABLE `shipment_products`
  ADD PRIMARY KEY (`shipment_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `shipment_requests`
--
ALTER TABLE `shipment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `production_id` (`production_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_shipment_requests_status` (`status`),
  ADD KEY `idx_shipment_requests_farm_manager` (`farm_manager_id`);

--
-- Indexes for table `supply_chain_events`
--
ALTER TABLE `supply_chain_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_supply_chain_product` (`product_id`),
  ADD KEY `fk_supply_chain_location` (`location_id`),
  ADD KEY `fk_supply_chain_shipment` (`shipment_id`),
  ADD KEY `fk_supply_chain_order` (`order_id`),
  ADD KEY `fk_supply_chain_created_by` (`created_by`);

--
-- Indexes for table `tracking_data`
--
ALTER TABLE `tracking_data`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `shipment_id` (`shipment_id`),
  ADD KEY `tracking_data_ibfk_2` (`recorded_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_code` (`user_code`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_created_by` (`created_by`),
  ADD KEY `fk_users_updated_by` (`updated_by`),
  ADD KEY `idx_users_user_code` (`user_code`);

--
-- Indexes for table `user_assigned_locations`
--
ALTER TABLE `user_assigned_locations`
  ADD PRIMARY KEY (`user_id`,`location_id`),
  ADD KEY `fk_ual_location` (`location_id`);

--
-- Indexes for table `user_dashboard_visits`
--
ALTER TABLE `user_dashboard_visits`
  ADD PRIMARY KEY (`visit_id`),
  ADD UNIQUE KEY `user_role_unique` (`user_id`,`role`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_last_visit` (`last_visit`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `vehicle_code` (`vehicle_code`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `fk_vehicles_created_by` (`created_by`),
  ADD KEY `fk_vehicles_updated_by` (`updated_by`),
  ADD KEY `fk_vehicles_user_id` (`user_id`),
  ADD KEY `idx_vehicles_vehicle_code` (`vehicle_code`),
  ADD KEY `idx_vehicles_status` (`status`),
  ADD KEY `idx_vehicles_fuel_type` (`fuel_type`),
  ADD KEY `idx_vehicles_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chart_values`
--
ALTER TABLE `chart_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `dynamic_pricing`
--
ALTER TABLE `dynamic_pricing`
  MODIFY `pricing_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farm_production`
--
ALTER TABLE `farm_production`
  MODIFY `production_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `order_products`
--
ALTER TABLE `order_products`
  MODIFY `order_product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `shipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `shipment_requests`
--
ALTER TABLE `shipment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supply_chain_events`
--
ALTER TABLE `supply_chain_events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `tracking_data`
--
ALTER TABLE `tracking_data`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `user_dashboard_visits`
--
ALTER TABLE `user_dashboard_visits`
  MODIFY `visit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
-- Constraints for table `dynamic_pricing`
--
ALTER TABLE `dynamic_pricing`
  ADD CONSTRAINT `fk_dynamic_pricing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dynamic_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `farm_production`
--
ALTER TABLE `farm_production`
  ADD CONSTRAINT `fk_fp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fp_farm_manager` FOREIGN KEY (`farm_manager_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
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
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_products`
--
ALTER TABLE `order_products`
  ADD CONSTRAINT `fk_order_products_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_products_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `fk_shipments_request_id` FOREIGN KEY (`request_id`) REFERENCES `shipment_requests` (`request_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipments_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `shipment_products`
--
ALTER TABLE `shipment_products`
  ADD CONSTRAINT `fk_shipment_products_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipment_products_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE;

--
-- Constraints for table `shipment_requests`
--
ALTER TABLE `shipment_requests`
  ADD CONSTRAINT `shipment_requests_ibfk_1` FOREIGN KEY (`production_id`) REFERENCES `farm_production` (`production_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipment_requests_ibfk_2` FOREIGN KEY (`farm_manager_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shipment_requests_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `supply_chain_events`
--
ALTER TABLE `supply_chain_events`
  ADD CONSTRAINT `fk_supply_chain_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supply_chain_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supply_chain_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supply_chain_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supply_chain_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE SET NULL;

--
-- Constraints for table `tracking_data`
--
ALTER TABLE `tracking_data`
  ADD CONSTRAINT `tracking_data_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tracking_data_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_assigned_locations`
--
ALTER TABLE `user_assigned_locations`
  ADD CONSTRAINT `fk_ual_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ual_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vehicles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vehicles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
