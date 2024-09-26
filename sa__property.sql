-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2024 年 09 月 05 日 17:20
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `sa__property`
--
CREATE DATABASE IF NOT EXISTS `sa__property` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sa__property`;

-- --------------------------------------------------------

--
-- 資料表結構 `borrowlist`
--

CREATE TABLE `borrowlist` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `understand` tinyint(1) NOT NULL,
  `borrow_place` varchar(5) NOT NULL,
  `borrow_department` varchar(10) NOT NULL,
  `borrow_person_name` varchar(10) NOT NULL,
  `phone` varchar(12) NOT NULL,
  `email` varchar(50) NOT NULL,
  `borrow_date` date NOT NULL,
  `returned_date` date NOT NULL,
  `sa_lending_person_name` varchar(255) DEFAULT NULL,
  `sa_lending_date` varchar(255) DEFAULT NULL,
  `sa_id_take` varchar(255) NOT NULL DEFAULT '0',
  `sa_deposit_take` varchar(255) NOT NULL DEFAULT '0',
  `sa_id_deposit_box_number` varchar(255) NOT NULL DEFAULT '-1',
  `sa_return_person_name` varchar(255) DEFAULT NULL,
  `sa_returned_date` varchar(255) DEFAULT NULL,
  `sa_id_returned` varchar(255) NOT NULL DEFAULT '0',
  `sa_deposit_returned` varchar(255) NOT NULL DEFAULT '0',
  `sa_remark` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `filling_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `borrowlist`
--

INSERT INTO `borrowlist` (`id`, `understand`, `borrow_place`, `borrow_department`, `borrow_person_name`, `phone`, `email`, `borrow_date`, `returned_date`, `sa_lending_person_name`, `sa_lending_date`, `sa_id_take`, `sa_deposit_take`, `sa_id_deposit_box_number`, `sa_return_person_name`, `sa_returned_date`, `sa_id_returned`, `sa_deposit_returned`, `sa_remark`, `created_at`, `updated_at`, `filling_time`) VALUES
(1, 1, '進德', '學生會', '測試', '0912345678', 'nuce.student@gmail.com', '2024-08-31', '2025-01-01', '呂宗墾', '2024-09-02', '1', '1', '18', '呂宗墾', '2024-09-04', '1', '1', '阿巴阿巴', NULL, '2024-09-03 21:10:04', '2024-08-31 14:24:35'),
(3, 1, '寶山', '學生會', '測試', '0912345678', 'nuce.student@gmail.com', '2024-08-31', '2025-01-31', '呂宗墾', '2024-09-04', '1', '1', '4', '呂宗墾', '2024-09-04', '1', '1', '', NULL, '2024-09-03 23:00:48', '2024-08-31 14:49:31'),
(4, 1, '進德', '學生會', '測試', '0912345678', 'nuce.student@gmail.com', '2024-09-02', '2024-09-30', '鄭弘易', '2024-09-02', '1', '1', '1', '蔣光宗', '2024-09-04', '1', '1', 'N/A', NULL, '2024-09-03 21:14:51', '2024-09-02 15:24:33');

-- --------------------------------------------------------

--
-- 資料表結構 `borrow_item`
--

CREATE TABLE `borrow_item` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `borrow_id` varchar(255) NOT NULL,
  `property_id` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `returned_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `borrow_item`
--

INSERT INTO `borrow_item` (`id`, `borrow_id`, `property_id`, `status`, `returned_date`, `created_at`, `updated_at`) VALUES
(1, '1', 'J2000001', 0, NULL, NULL, '2024-09-02 00:12:52'),
(2, '1', 'J2000002', 0, NULL, NULL, '2024-09-03 21:10:04'),
(3, '3', 'B3000001', 0, NULL, NULL, '2024-09-03 23:00:48'),
(4, '4', 'J2000004', 0, NULL, NULL, '2024-09-03 21:14:51');

-- --------------------------------------------------------

--
-- 資料表結構 `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `info`
--

CREATE TABLE `info` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(10) NOT NULL,
  `info` varchar(20) NOT NULL DEFAULT 'HW',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2024_08_29_013145_create_info_table', 2),
(6, '2024_08_29_025027_create_property_table', 3),
(7, '2024_08_29_072811_add_columns_to_property_table', 4),
(8, '2024_08_30_114830_create_borrowlist_table', 5),
(9, '2024_08_30_121037_create_borrow_item_table', 5),
(10, '2024_08_31_052655_add_columns_to_borrowlist_table', 6),
(11, '2024_08_31_052856_add_columns_to_property_table', 6),
(12, '2024_08_31_061748_update_borrowlist_table', 7),
(13, '2024_09_02_014505_create_responsible_person', 8),
(14, '2024_09_04_072250_add_columns_in_property_table', 9),
(15, '2024_09_04_072304_add_columns_in_property_table', 10);

-- --------------------------------------------------------

--
-- 資料表結構 `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `property`
--

CREATE TABLE `property` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ssid` varchar(8) NOT NULL,
  `class` varchar(20) NOT NULL,
  `name` varchar(30) NOT NULL,
  `second_name` varchar(30) DEFAULT NULL,
  `order_number` int(11) NOT NULL,
  `price` int(11) DEFAULT NULL,
  `department` varchar(10) NOT NULL,
  `depositary` varchar(10) NOT NULL,
  `belong_place` varchar(5) NOT NULL,
  `get_day` date NOT NULL,
  `format` varchar(50) DEFAULT NULL,
  `remark` varchar(50) DEFAULT NULL,
  `enable_lending` tinyint(1) NOT NULL DEFAULT 0,
  `lending_status` tinyint(1) NOT NULL DEFAULT 0,
  `property_status` varchar(255) DEFAULT NULL,
  `img_url` varchar(50) DEFAULT NULL,
  `school_property` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `property`
--

INSERT INTO `property` (`id`, `ssid`, `class`, `name`, `second_name`, `order_number`, `price`, `department`, `depositary`, `belong_place`, `get_day`, `format`, `remark`, `enable_lending`, `lending_status`, `property_status`, `img_url`, `school_property`, `created_at`, `updated_at`) VALUES
(1, 'J1000001', '不斷電系統', 'UPS不斷電系統123', NULL, 1, 32650, '資訊組', '鄭弘易', '進德', '2022-02-22', 'VERTIV GXT5-3000LVRT2UXL', '測試測試', 0, 0, '正常', '1.jpg', 0, NULL, '2024-09-04 23:41:35'),
(2, 'J2000001', '什項用具', '麥克風', '動圈式麥克風', 1, 2836, '保管組', '呂宗墾', '進德', '2024-08-01', 'Carol E dur-916S 珍珠白', '可外借', 1, 0, '正常', '', 0, NULL, '2024-08-30 22:25:12'),
(3, 'J2000002', '什項用具', '麥克風', '動圈式麥克風', 2, 2836, '保管組', '呂宗墾', '進德', '2024-08-01', 'Carol E dur-916S 珍珠白', '可外借', 1, 0, '正常', '', 0, NULL, '2024-09-03 21:10:04'),
(4, 'J2000003', '什項用具', '麥克風', '動圈式麥克風', 3, 2836, '保管組', '呂宗墾', '進德', '2024-08-01', 'Carol E dur-916S 香檳金', '可外借', 1, 0, '正常', '', 0, NULL, NULL),
(5, 'J2000004', '什項用具', '麥克風', '動圈式麥克風', 4, 2836, '保管組', '呂宗墾', '進德', '2024-08-01', 'Carol E dur-916S 香檳金', '可外借', 1, 0, '正常', '', 0, NULL, '2024-09-03 21:14:51'),
(6, 'B3000001', '音響設備', '行動式音箱主機（含麥克風）', '', 4, 27500, '保管組', '呂宗墾', '寶山', '2013-01-01', 'OKAYO GPA-850WU', '113.05.30 更換無線麥克風模組\n113.07.31 更新麥克風及購買防塵套', 1, 0, '', '', 0, NULL, '2024-09-03 23:00:48'),
(7, 'B3000002', '音響設備', '行動式音箱主機（含麥克風）', '', 5, 20000, '保管組', '呂宗墾', '寶山', '2013-01-01', 'MIPRO MA-707', '', 1, 0, '', '', 0, NULL, NULL),
(8, 'B3000002', '音響設備', '行動式音箱主機（含麥克風）', '', 6, 30000, '保管組', '呂宗墾', '寶山', '2024-05-30', 'TEV TA-680D（含兩支無線麥克風）', '', 1, 0, '', '', 0, NULL, NULL),
(9, '34000001', '個人電腦', '個人電腦主機', '', 2, 10000, '保管組', '呂宗墾', '307', '2016-09-01', 'G41 + GTX 750', '', 0, 0, '', '', 0, NULL, NULL),
(10, '44000001', '個人電腦', '個人電腦主機', '', 3, 10000, '保管組', '呂宗墾', '405', '2016-09-01', 'G41 + GTX 750 Ti', '', 0, 0, '', '', 0, NULL, NULL),
(11, 'J3000001', '直播擷取卡', '直播擷取卡', NULL, 1, 5990, '保管組', '呂宗墾', '進德', '2021-05-13', 'Kaijet j5create EFP-2 JVA06', NULL, 1, 0, NULL, '11.jpg', 0, '2024-09-04 23:23:11', '2024-09-05 00:02:05'),
(12, 'J3000001', '音響設備', '行動式音箱主機（含麥克風）', NULL, 1, 27500, '保管組', '呂宗墾', '進德', '2013-01-01', 'OKAYO GPA-850WU', NULL, 1, 0, NULL, NULL, 0, '2024-09-05 00:14:58', '2024-09-05 00:16:05'),
(13, 'J3000002', '音響設備', '行動式音箱主機（含麥克風）', NULL, 2, 20000, '保管組', '呂宗墾', '進德', '2013-01-01', 'MIPRO MA-707', NULL, 1, 0, NULL, NULL, 0, '2024-09-05 00:17:08', '2024-09-05 00:17:08'),
(14, 'J3000003', '音響設備', '行動式音箱主機（含麥克風）', NULL, 3, 47000, '保管組', '呂宗墾', '進德', '2022-10-03', 'TEV TA-780D（含兩支無線麥克風） + TEV TS-780 + 2支音箱腳架', NULL, 1, 0, NULL, NULL, 0, '2024-09-05 00:17:59', '2024-09-05 00:17:59');

-- --------------------------------------------------------

--
-- 資料表結構 `responsible_person`
--

CREATE TABLE `responsible_person` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  `status` smallint(6) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `responsible_person`
--

INSERT INTO `responsible_person` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 0, NULL, '2024-09-01 21:48:10'),
(2, '呂宗墾', 1, NULL, '2024-09-01 21:50:53'),
(3, '鄭弘易', 1, NULL, NULL),
(4, '蔣光宗', 1, NULL, NULL),
(5, '鄭欣怡', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('zH4cRYKScvV1PHSVBCWTHwWjtfs74K4AxqWFIzRg', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaUlBaXUzSGYxQXB6UExLRmEzbUxrNzRVanNkbTZzckNQcXNXbVVOeiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzA6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9tYWludGFpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1725524323);

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `borrowlist`
--
ALTER TABLE `borrowlist`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `borrow_item`
--
ALTER TABLE `borrow_item`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- 資料表索引 `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- 資料表索引 `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- 資料表索引 `info`
--
ALTER TABLE `info`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- 資料表索引 `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- 資料表索引 `property`
--
ALTER TABLE `property`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `responsible_person`
--
ALTER TABLE `responsible_person`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `borrowlist`
--
ALTER TABLE `borrowlist`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `borrow_item`
--
ALTER TABLE `borrow_item`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `info`
--
ALTER TABLE `info`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `property`
--
ALTER TABLE `property`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `responsible_person`
--
ALTER TABLE `responsible_person`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
