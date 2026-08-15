-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 19, 2026 at 06:03 AM
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
-- Database: `hotel_jebal`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Jebal Homes Admin', 'admin@jebalhomes.com', '$2y$10$qUxMi011QkaqBK9d9f6P7OxCIR5fJLtxt4j4FNvjalX6ACuLFI1m2', 'admin', 1, NULL, '2026-06-19 08:27:53', '2026-06-19 09:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `room_name` varchar(150) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `guests` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `message` text DEFAULT NULL,
  `status` enum('Pending','Confirmed','Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_status` enum('Payment Pending','Paid','Failed','Cancelled','Refunded') NOT NULL DEFAULT 'Payment Pending',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'LKR',
  `invoice_id` int(10) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(80) DEFAULT NULL,
  `invoice_file_path` varchar(255) DEFAULT NULL,
  `invoice_generated_at` datetime DEFAULT NULL,
  `email_status` varchar(50) NOT NULL DEFAULT 'Pending',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `full_name`, `email`, `phone`, `room_name`, `check_in_date`, `check_out_date`, `guests`, `message`, `status`, `payment_status`, `amount`, `currency`, `invoice_id`, `invoice_number`, `invoice_file_path`, `invoice_generated_at`, `email_status`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 'Kumar Rajan', 'kumar@example.com', '+94771234567', 'Ground Floor Room 1', '2026-06-24', '2026-06-26', 2, 'Need parking space.', 'Pending', 'Payment Pending', 17000.00, 'LKR', NULL, NULL, NULL, NULL, 'Sent', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54'),
(2, 'Nivetha Siva', 'nivetha@example.com', '+94772345678', 'Family Room', '2026-06-29', '2026-07-01', 4, 'Family stay.', 'Confirmed', 'Paid', 28000.00, 'LKR', NULL, NULL, NULL, NULL, 'Sent', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54'),
(3, 'Arun Thevan', 'arun@example.com', '+94773456789', 'Private Cottage', '2026-07-04', '2026-07-05', 2, 'Late check-in possible?', 'Cancelled', 'Cancelled', 18000.00, 'LKR', NULL, NULL, NULL, NULL, 'Sent', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `enquiry_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_email` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `email_type` varchar(80) NOT NULL,
  `status` enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(190) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('New','Read','Replied') NOT NULL DEFAULT 'New',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enquiries`
--

INSERT INTO `enquiries` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 'Suresh Kanthan', 'suresh@example.com', '+94774567890', 'Room availability', 'Do you have a room available this weekend?', 'New', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54'),
(2, 'Meena Raj', 'meena@example.com', '+94775678901', 'Family room details', 'Please send family room details and price.', 'Read', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54'),
(3, 'David Fernando', 'david@example.com', '+94776789012', 'Parking', 'Is parking available for guests?', 'Replied', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(80) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_email` varchar(190) NOT NULL,
  `room_name` varchar(150) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'LKR',
  `payment_method` varchar(60) NOT NULL DEFAULT 'PayHere',
  `payment_date` datetime DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `order_id` varchar(100) NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'LKR',
  `status` enum('Payment Pending','Paid','Failed','Cancelled','Refunded') NOT NULL DEFAULT 'Payment Pending',
  `method` varchar(60) NOT NULL DEFAULT 'PayHere',
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `invoice_id` int(10) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `order_id`, `payment_id`, `amount`, `currency`, `status`, `method`, `gateway_response`, `invoice_id`, `invoice_number`, `created_at`, `updated_at`) VALUES
(1, 2, 'JH-ORDER-00002', 'PH-SAMPLE-00002', 28000.00, 'LKR', 'Paid', 'PayHere', '{\"sample\": true, \"status_message\": \"Sample paid payment\"}', NULL, NULL, '2026-06-19 08:27:54', '2026-06-19 08:27:54');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(10) UNSIGNED NOT NULL,
  `room_name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `max_guests` int(10) UNSIGNED NOT NULL DEFAULT 2,
  `bed_type` varchar(100) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'LKR',
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities`)),
  `status` enum('Available','Unavailable','Maintenance') NOT NULL DEFAULT 'Available',
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `slug`, `description`, `max_guests`, `bed_type`, `base_price`, `currency`, `amenities`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Ground Floor Room 1', 'ground-floor-room-1', 'Comfortable ground floor room with attached bathroom and easy access.', 2, 'Double Bed', 8500.00, 'LKR', '[\"Air Conditioning\", \"Free WiFi\", \"Television\", \"Attached Bathroom\", \"Parking\", \"Garden Access\"]', 'Available', 1, '2026-06-19 08:27:53', '2026-06-19 08:27:53'),
(2, 'Ground Floor Room 2', 'ground-floor-room-2', 'Clean and peaceful ground floor room suitable for short stays.', 2, 'Double Bed', 8500.00, 'LKR', '[\"Air Conditioning\", \"Free WiFi\", \"Television\", \"Attached Bathroom\", \"Parking\"]', 'Available', 2, '2026-06-19 08:27:53', '2026-06-19 08:27:53'),
(3, 'First Floor Room 1', 'first-floor-room-1', 'First floor room with balcony and comfortable stay facilities.', 2, 'Double Bed', 9500.00, 'LKR', '[\"Air Conditioning\", \"Free WiFi\", \"Television\", \"Attached Bathroom\", \"Balcony\", \"Parking\"]', 'Available', 3, '2026-06-19 08:27:53', '2026-06-19 08:27:53'),
(4, 'First Floor Room 2', 'first-floor-room-2', 'Quiet first floor room with balcony and attached bathroom.', 2, 'Double Bed', 9500.00, 'LKR', '[\"Air Conditioning\", \"Free WiFi\", \"Television\", \"Attached Bathroom\", \"Balcony\", \"Parking\"]', 'Available', 4, '2026-06-19 08:27:53', '2026-06-19 08:27:53'),
(5, 'Family Room', 'family-room', 'Spacious room suitable for families and small groups.', 4, 'Double Bed + Single Beds', 14000.00, 'LKR', '[\"Air Conditioning\", \"Free WiFi\", \"Television\", \"Attached Bathroom\", \"Parking\", \"Garden Access\"]', 'Available', 5, '2026-06-19 08:27:53', '2026-06-19 08:27:53'),
(6, 'Private Cottage', 'private-cottage', 'Private cottage for guests who prefer more privacy and space.', 4, 'Double Bed', 18000.00, 'LKR', '[\"Air Conditioning\", \"Free WiFi\", \"Television\", \"Attached Bathroom\", \"Parking\", \"Garden Access\"]', 'Available', 6, '2026-06-19 08:27:53', '2026-06-19 08:27:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_admin_sessions_token_hash` (`token_hash`),
  ADD KEY `idx_admin_sessions_user` (`admin_user_id`),
  ADD KEY `idx_admin_sessions_expires` (`expires_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_admin_users_email` (`email`),
  ADD KEY `idx_admin_users_active` (`is_active`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bookings_room_dates_status` (`room_name`,`status`,`check_in_date`,`check_out_date`),
  ADD KEY `idx_bookings_status` (`status`),
  ADD KEY `idx_bookings_payment_status` (`payment_status`),
  ADD KEY `idx_bookings_created_at` (`created_at`),
  ADD KEY `idx_bookings_email` (`email`),
  ADD KEY `fk_bookings_invoice` (`invoice_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_logs_booking` (`booking_id`),
  ADD KEY `idx_email_logs_enquiry` (`enquiry_id`),
  ADD KEY `idx_email_logs_status` (`status`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_enquiries_status` (`status`),
  ADD KEY `idx_enquiries_created_at` (`created_at`),
  ADD KEY `idx_enquiries_email` (`email`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_invoices_invoice_number` (`invoice_number`),
  ADD KEY `idx_invoices_booking_id` (`booking_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_payments_order_id` (`order_id`),
  ADD KEY `idx_payments_booking_id` (`booking_id`),
  ADD KEY `idx_payments_status` (`status`),
  ADD KEY `idx_payments_created_at` (`created_at`),
  ADD KEY `fk_payments_invoice` (`invoice_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rate_limits_lookup` (`ip_address`,`action`,`created_at`),
  ADD KEY `idx_rate_limits_created_at` (`created_at`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rooms_room_name` (`room_name`),
  ADD UNIQUE KEY `uniq_rooms_slug` (`slug`),
  ADD KEY `idx_rooms_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `fk_admin_sessions_user` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `fk_email_logs_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_logs_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

CREATE TABLE IF NOT EXISTS gallery_folders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  folder_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  image_file_name VARCHAR(180) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gallery_images_folder
    FOREIGN KEY (folder_id) REFERENCES gallery_folders(id)
    ON DELETE CASCADE,
  INDEX idx_gallery_images_folder_sort (folder_id, sort_order),
  INDEX idx_gallery_images_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS experience_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(80) NOT NULL,
  location VARCHAR(150) DEFAULT NULL,
  description TEXT NOT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE experience_items
ADD COLUMN distance VARCHAR(100) NULL AFTER location;

CREATE TABLE IF NOT EXISTS `website_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `website_settings` (`setting_key`, `setting_value`) VALUES
('business_name', 'Tulip Guest Inn'),
('address', 'Tulip Guest Inn, Sri Lanka'),
('phone', '+94 77 123 4567'),
('reception_contact_number', '+94 21 222 4567'),
('whatsapp_reservation_number', '+94 77 123 4567'),
('email', 'reservations@jebalguesthouse.com'),
('business_hours', 'Daily · 7:00 AM – 10:00 PM'),
('facebook_link', ''),
('instagram_link', ''),
('map_embed_url', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

CREATE TABLE IF NOT EXISTS admin_password_otps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  attempts INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_id (admin_id),
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
);

ALTER TABLE admin_password_otps
ADD COLUMN expires_at_epoch INT NOT NULL DEFAULT 0 AFTER expires_at;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS is_booking_for_other TINYINT(1) NOT NULL DEFAULT 0 AFTER phone,
  ADD COLUMN IF NOT EXISTS staying_guest_name VARCHAR(150) DEFAULT NULL AFTER is_booking_for_other,
  ADD COLUMN IF NOT EXISTS staying_guest_email VARCHAR(190) DEFAULT NULL AFTER staying_guest_name,
  ADD COLUMN IF NOT EXISTS staying_guest_phone VARCHAR(50) DEFAULT NULL AFTER staying_guest_email,
  ADD COLUMN IF NOT EXISTS staying_guest_note TEXT DEFAULT NULL AFTER staying_guest_phone;


-- Optional manual migration. The PHP helper also creates this table automatically.
CREATE TABLE IF NOT EXISTS booking_audit_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED NULL,
    order_id VARCHAR(120) NULL,
    event_type VARCHAR(80) NOT NULL,
    event_title VARCHAR(160) NOT NULL,
    event_message TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_audit_booking_id (booking_id),
    KEY idx_booking_audit_event_type (event_type),
    KEY idx_booking_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    related_type VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    reply_to_email VARCHAR(190) NULL,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    email_type VARCHAR(80) NOT NULL,
    status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_queue_job (related_type, related_id, email_type, recipient_email),
    KEY idx_email_queue_status_available (status, available_at, id),
    KEY idx_email_queue_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fix older email_queue tables that were created before the cron worker fields existed.
ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS reply_to_email VARCHAR(190) NULL AFTER recipient_email;
ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS body_html MEDIUMTEXT NULL AFTER subject;
ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER attempts;
ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL AFTER available_at;
ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER locked_at;

-- Required for the gallery folder ordering used by the gallery admin and public APIs.
-- Run this once on the `hotel_tulip` database.

ALTER TABLE gallery_folders
ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 1 AFTER status;

CREATE TABLE IF NOT EXISTS `offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `subtitle` varchar(150) DEFAULT NULL,
  `description` text NOT NULL,
  `discount_label` varchar(50) NOT NULL,
  `validity_label` varchar(150) NOT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `status` enum('active','inactive','expired','upcoming') NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_offers_public` (`status`, `start_date`, `end_date`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_calendar_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'booking.com',
    external_uid VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    summary VARCHAR(255) NULL,
    status VARCHAR(40) NULL,
    external_last_modified DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_room_uid (provider, room_id, external_uid),
    KEY idx_external_room_dates (room_id, start_date, end_date, is_active),
    CONSTRAINT fk_external_calendar_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_calendar_sync_status (
    room_id INT UNSIGNED NOT NULL PRIMARY KEY,
    provider VARCHAR(40) NOT NULL DEFAULT 'booking.com',
    last_sync_started_at DATETIME NULL,
    last_sync_completed_at DATETIME NULL,
    last_sync_status VARCHAR(30) NULL,
    last_sync_error TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_external_sync_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Run once for Tulip quick-action workflow persistence.
-- No columns are added or removed; only the allowed ENUM values are extended.
ALTER TABLE bookings
  MODIFY status ENUM('Pending','Confirmed','Checked In','Checked Out','Cancelled','No Show') NOT NULL DEFAULT 'Pending',
  MODIFY payment_status ENUM('Payment Pending','Paid','Failed','Cancelled','Refunded','No Pay') NOT NULL DEFAULT 'Payment Pending';

-- Run once for Tulip quick-action workflow persistence.
-- No columns are added or removed; only the allowed ENUM values are extended.
ALTER TABLE bookings
  MODIFY status ENUM('Pending','Confirmed','Checked In','Checked Out','Cancelled','No Show') NOT NULL DEFAULT 'Pending',
  MODIFY payment_status ENUM('Payment Pending','Paid','Failed','Cancelled','Refunded','No Pay') NOT NULL DEFAULT 'Payment Pending';

ALTER TABLE payments
  MODIFY status ENUM('Payment Pending','Paid','Failed','Cancelled','Refunded','No Pay') NOT NULL DEFAULT 'Payment Pending';

CREATE TABLE IF NOT EXISTS booking_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_no VARCHAR(40) NULL,
  primary_booking_id INT UNSIGNED NULL,
  total_guests INT UNSIGNED NOT NULL,
  total_rooms INT UNSIGNED NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'LKR',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_booking_groups_booking_no (booking_no),
  KEY idx_booking_groups_primary_booking (primary_booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_group_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS is_group_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER booking_group_id;

-- Tulip Guest Inn
-- Migration: multiple-room booking support
-- Run this file in the Tulip database before deploying the related PHP file.
-- It is safe to import again because every schema change is conditional.

CREATE TABLE IF NOT EXISTS `booking_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_no` VARCHAR(40) NULL,
  `primary_booking_id` INT UNSIGNED NULL,
  `total_guests` INT UNSIGNED NOT NULL,
  `total_rooms` INT UNSIGNED NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'LKR',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_groups_booking_no` (`booking_no`),
  KEY `idx_booking_groups_primary_booking` (`primary_booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add bookings.booking_group_id when it does not exist.
SET @migration_sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'bookings'
        AND COLUMN_NAME = 'booking_group_id'
    ),
    'SELECT 1',
    'ALTER TABLE `bookings` ADD COLUMN `booking_group_id` BIGINT UNSIGNED NULL AFTER `id`'
  )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Add bookings.is_group_primary when it does not exist.
SET @migration_sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'bookings'
        AND COLUMN_NAME = 'is_group_primary'
    ),
    'SELECT 1',
    'ALTER TABLE `bookings` ADD COLUMN `is_group_primary` TINYINT(1) NOT NULL DEFAULT 0 AFTER `booking_group_id`'
  )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Add the composite index used by grouped admin queries and status updates.
SET @migration_sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'bookings'
        AND INDEX_NAME = 'idx_bookings_group_primary'
    ),
    'SELECT 1',
    'ALTER TABLE `bookings` ADD INDEX `idx_bookings_group_primary` (`booking_group_id`, `is_group_primary`)'
  )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = NULL;

-- Verification output. Expected values: 1, 1 and 1.
SELECT
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'booking_groups') AS `booking_groups_table`,
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'booking_group_id') AS `booking_group_id_column`,
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'is_group_primary') AS `is_group_primary_column`;
