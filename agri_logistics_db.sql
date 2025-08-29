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
