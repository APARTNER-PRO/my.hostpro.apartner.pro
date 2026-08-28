-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-5.7:3306
-- Час створення: Сер 28 2026 р., 13:15
-- Версія сервера: 5.7.44
-- Версія PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `billing`
--

-- --------------------------------------------------------

--
-- Структура таблиці `event_logs`
--

CREATE TABLE `event_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `event` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` enum('info','warning','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `event_logs`
--

INSERT INTO `event_logs` (`id`, `event`, `email`, `level`, `message`, `context`, `created_at`) VALUES
(1, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:36:21'),
(2, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:37:22'),
(3, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:38:40'),
(4, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:41:01'),
(5, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:41:35'),
(6, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:42:10'),
(7, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:49:41'),
(8, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:49:54'),
(9, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:51:54'),
(10, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:53:13'),
(11, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:54:33'),
(12, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:56:39'),
(13, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 00:57:04'),
(14, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:14:20'),
(15, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:25:40'),
(16, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:12'),
(17, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:16'),
(18, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:17'),
(19, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:17'),
(20, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:17'),
(21, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:17'),
(22, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:17'),
(23, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:18'),
(24, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:49'),
(25, 'auth.failed', 'roman@matviy.pp.ua', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:30:54'),
(26, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:31:15'),
(27, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 01:57:58'),
(28, 'ticket.create', 'roman@matviy.pp.ua', 'info', 'New ticket created', '{\"subject\": \"хостинг\", \"ticket_id\": \"1\"}', '2026-06-04 16:42:01'),
(29, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 16:46:23'),
(30, 'ticket.ai_reply_failed', NULL, 'error', 'Failed to generate response from OpenRouter API. Primary model (openrouter/auto) error: [HTTP Code 402. Response: {\"error\":{\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 396. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\",\"code\":402,\"metadata\":{\"provider_name\":null,\"previous_errors\":[{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 23. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65535 tokens, but can only afford 23. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65535 tokens, but can only afford 178. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65535 tokens, but can only afford 178. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65535 tokens, but can only afford 178. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 40960 tokens, but can only afford 95. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 16384 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 75. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 95. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 95. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 101. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 32768 tokens, but can only afford 285. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 142. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 198. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 65536 tokens, but can only afford 375. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 8192 tokens, but can only afford 158. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"}]}},\"user_id\":\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\"}]. Fallback model (google/gemini-2.5-flash:free) error: [HTTP Code 404. Response: {\"error\":{\"message\":\"No endpoints found for google/gemini-2.5-flash:free.\",\"code\":404},\"user_id\":\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\"}].', '{\"ticket_id\": 1}', '2026-06-04 17:16:18'),
(31, 'ticket.ai_reply_failed', NULL, 'error', 'Failed to generate response from OpenRouter API. Primary model (openrouter/auto) error: [HTTP Code 402. Response: {\"error\":{\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 396. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\",\"code\":402,\"metadata\":{\"provider_name\":null,\"previous_errors\":[{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 23. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 23. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 178. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 178. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 178. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 95. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 75. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 95. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 95. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 101. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 285. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 142. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 198. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 375. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 118. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"},{\"code\":402,\"message\":\"This request requires more credits, or fewer max_tokens. You requested up to 1024 tokens, but can only afford 158. To increase, visit https://openrouter.ai/settings/credits and upgrade to a paid account\"}]}},\"user_id\":\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\"}]. Fallback models also failed: {\"google\\/gemini-2.0-flash-exp:free\":\"HTTP Code 404. Response: {\\\"error\\\":{\\\"message\\\":\\\"No endpoints found for google\\/gemini-2.0-flash-exp:free.\\\",\\\"code\\\":404},\\\"user_id\\\":\\\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\\\"}\",\"meta-llama\\/llama-3.1-8b-instruct:free\":\"HTTP Code 404. Response: {\\\"error\\\":{\\\"message\\\":\\\"No endpoints found for meta-llama\\/llama-3.1-8b-instruct:free.\\\",\\\"code\\\":404},\\\"user_id\\\":\\\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\\\"}\",\"mistralai\\/mistral-7b-instruct:free\":\"HTTP Code 404. Response: {\\\"error\\\":{\\\"message\\\":\\\"No endpoints found for mistralai\\/mistral-7b-instruct:free.\\\",\\\"code\\\":404},\\\"user_id\\\":\\\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\\\"}\",\"qwen\\/qwen-2-7b-instruct:free\":\"HTTP Code 404. Response: {\\\"error\\\":{\\\"message\\\":\\\"No endpoints found for qwen\\/qwen-2-7b-instruct:free.\\\",\\\"code\\\":404},\\\"user_id\\\":\\\"user_3D32wB8q9vTeH6W3WY1VGJKKKqF\\\"}\"}', '{\"ticket_id\": 1}', '2026-06-04 17:18:52'),
(32, 'ticket.reply', 'roman@matviy.pp.ua', 'info', 'Ticket replied by admin', '{\"ticket_id\": 1}', '2026-06-04 17:29:50'),
(33, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 17:33:07'),
(34, 'ticket.close', 'roman@matviy.pp.ua', 'info', 'Ticket closed by client', '{\"ticket_id\": 1}', '2026-06-04 17:35:02'),
(35, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 17:54:01'),
(36, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:01:05'),
(37, 'admin.invoice_created', NULL, 'info', 'Created invoice #1 for user 1', '{\"amount\": 3000, \"currency\": \"UAH\", \"invoice_id\": \"1\"}', '2026-06-04 21:04:12'),
(38, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:06:23'),
(39, 'auth.failed', 'test@apartner.pro', 'warning', 'Failed login attempt', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:12:48'),
(40, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:12:57'),
(41, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:14:56'),
(42, 'invoice.paid_mock', 'roman@matviy.pp.ua', 'info', 'Invoice #1 marked as PAID via mock paddle', '{\"method\": \"paddle\", \"invoice_id\": 1}', '2026-06-04 21:23:57'),
(43, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:47:57'),
(44, 'admin.invoice_created', NULL, 'info', 'Created invoice #2 for user 1', '{\"amount\": 3000, \"currency\": \"UAH\", \"invoice_id\": \"2\"}', '2026-06-04 21:48:20'),
(45, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:48:36'),
(46, 'paddle.checkout_error', 'roman@matviy.pp.ua', 'error', 'Paddle transaction error [400]: Invalid request.', '{\"invoice_id\": 2}', '2026-06-04 21:53:50'),
(47, 'auth.login', 'roman@matviy.pp.ua', 'info', 'Client login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 21:59:57'),
(48, 'paddle.checkout_error', 'roman@matviy.pp.ua', 'error', 'Paddle transaction error [400]: Invalid request.', '{\"invoice_id\": 2}', '2026-06-04 22:00:04'),
(49, 'paddle.checkout_error', 'roman@matviy.pp.ua', 'error', 'Paddle transaction error [400]: Invalid request.', '{\"invoice_id\": 2}', '2026-06-04 22:00:12'),
(50, 'paddle.checkout_error', 'roman@matviy.pp.ua', 'error', 'Paddle transaction error [400]: A Default Payment Link has not yet been defined within the Paddle Dashboard for this account, find this under checkout settings.', '{\"invoice_id\": 2}', '2026-06-04 22:01:42'),
(51, 'paddle.checkout_error', 'roman@matviy.pp.ua', 'error', 'Paddle transaction error [400]: A Default Payment Link has not yet been defined within the Paddle Dashboard for this account, find this under checkout settings.', '{\"invoice_id\": 2}', '2026-06-04 22:01:53'),
(52, 'paddle.checkout_created', 'roman@matviy.pp.ua', 'info', 'Paddle transaction created for invoice #2', '{\"invoice_id\": 2, \"transaction_id\": \"txn_01kta3sbm49356av2e3kjtnk4p\"}', '2026-06-04 22:04:25'),
(53, 'paddle.checkout_created', 'roman@matviy.pp.ua', 'info', 'Paddle transaction created for invoice #2', '{\"invoice_id\": 2, \"transaction_id\": \"txn_01kta3tp4bmfs3qbvtmtt0dwab\"}', '2026-06-04 22:05:09'),
(54, 'paddle.checkout_created', 'roman@matviy.pp.ua', 'info', 'Paddle transaction created for invoice #2', '{\"invoice_id\": 2, \"transaction_id\": \"txn_01kta3wrmjg8jzh9yxb1wnaf5p\"}', '2026-06-04 22:06:17'),
(55, 'paddle.checkout_created', 'roman@matviy.pp.ua', 'info', 'Paddle transaction created for invoice #2', '{\"invoice_id\": 2, \"transaction_id\": \"txn_01kta3zr1ggcj2twhpr8q6pmsr\"}', '2026-06-04 22:07:54'),
(56, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-06-04 22:50:31'),
(57, 'paddle.checkout_created', 'roman@matviy.pp.ua', 'info', 'Paddle transaction created for invoice #2', '{\"invoice_id\": 2, \"transaction_id\": \"txn_01kta6sdkghyx6njd50cp8jtp1\"}', '2026-06-04 22:56:53'),
(58, 'ticket.reply', 'roman@matviy.pp.ua', 'info', 'Ticket replied by admin', '{\"ticket_id\": 1}', '2026-06-04 23:34:24'),
(59, 'ticket.reply', 'roman@matviy.pp.ua', 'info', 'Ticket replied by admin', '{\"ticket_id\": 1}', '2026-06-04 23:34:44'),
(60, 'auth.login', 'test@apartner.pro', 'info', 'Admin login', '{\"ip\": \"127.0.0.1\"}', '2026-08-28 10:28:04');

-- --------------------------------------------------------

--
-- Структура таблиці `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `status` enum('unpaid','paid','cancelled','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `due_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `invoices`
--

INSERT INTO `invoices` (`id`, `user_id`, `amount`, `currency`, `status`, `due_date`, `created_at`, `updated_at`) VALUES
(1, 1, 3000.00, 'UAH', 'paid', '2026-10-05', '2026-06-04 21:04:12', '2026-06-04 21:23:57'),
(2, 1, 3000.00, 'UAH', 'unpaid', '2026-06-04', '2026-06-04 21:48:20', '2026-06-04 21:48:20');

-- --------------------------------------------------------

--
-- Структура таблиці `paddle_cache`
--

CREATE TABLE `paddle_cache` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_json` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `fetched_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблиці `settings`
--

CREATE TABLE `settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `settings`
--

INSERT INTO `settings` (`key`, `value`) VALUES
('payment_methods', '{\"paddle\":true,\"monobank\":false,\"wayforpay\":false}');

-- --------------------------------------------------------

--
-- Структура таблиці `tickets`
--

CREATE TABLE `tickets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','pending','replied','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `tickets`
--

INSERT INTO `tickets` (`id`, `user_id`, `subject`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'хостинг', 'replied', '2026-06-04 16:42:01', '2026-06-04 23:34:44');

-- --------------------------------------------------------

--
-- Структура таблиці `ticket_messages`
--

CREATE TABLE `ticket_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `sender_role` enum('admin','client') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `ticket_messages`
--

INSERT INTO `ticket_messages` (`id`, `ticket_id`, `sender_id`, `sender_role`, `message`, `created_at`) VALUES
(1, 1, 1, 'client', 'чи можна оновити php', '2026-06-04 16:42:01'),
(2, 1, 0, 'admin', 'Привіт!  \n\nЗвісно, ви можете оновити версію PHP на вашому хостинг‑аккаунті Programist Hosting. Нижче наведено покрокову інструкцію, що відповідає політиці та можливості платформи **hostpro.apartner.pro**.\n\n---\n\n### Як змінити версію PHP\n\n| Крок | Дія |\n|------|-----|\n| 1 | Увійдіть до **панелі управління** вашого хостинг‑акаунту (Control Panel). |\n| 2 | У меню виберіть **„Налаштування сайту”** → **„Версії PHP”**. |\n| 3 | У списку **„Поточна версія PHP”** виберіть потрібну версію (наприклад, **PHP 8.2**). |\n| 4 | Натисніть **«Зберегти»**. Оновлення наступить миттєво, і ваші скрипти будуть запускатися на новій версії. |\n\n> **Джерело:** інструкції та скріншоти знайдено на сторінці **hostpro.apartner.pro/uk/php-versions** (розділ *Зміна версії PHP*).\n\n---\n\n### Що треба врахувати перед оновленням\n\n1. **Свмісність вашого коду**  \n   - PHP 8.x вводить суворіші типізації та видалення деяких функцій (наприклад, `each()`, `$HTTP_...` і т.д.). Перевірте код на сумісність із новими вимогами.  \n   - Якщо ви використовуєте сторонні бібліотеки, переконайтеся, що вони підтримують вибрану версію PHP.\n\n2. **Тестування**  \n   - Після зміни версії рекомендується перевірити роботу всіх сторінок сайту, особливоформ та плагінів.  \n   - На панелі **„Логи”** (hostpro.apartner.pro/uk/logs) можна переглянути помилки PHP, які з’явились після оновлення.\n\n3. **Підтримка старих проектів**  \n   - Якщо ваш сайт розрахований на більш стару версію (наприклад, PHP 7.4), перед оновленням створіть копію бази даних та файлів, щоб у випадку,sizeof проблеми можна було швидко відкочити.\n\n4. **Обмеження та ліміти**  \n   - На всіх різних тарифах хостингу Programist Hosting доступні версії PHP від **5.6** до **8.2**. Ви не можете переįнити за межами цього діапазону.  \n   - Для безкоштовного тарифу **Free** зменшена кількість можливих версій (зазвичай лише 7.4 і 8.0), подрібно перевірте у «Версії PHP» у вашій панелі.\n\n---\n\n### Додаткові ресурси\n\n- **Документація з зміни версії PHP** – [hostpro.apartner.pro/uk/php-versions](https://hostpro.apartner.pro/uk/php-versions)  \n- **Підказки щодо сумісності коду** – [hostpro.apartner.pro/uk/php-compatibility](https://hostpro.apartner.pro/uk/php-compatibility)  \n- **Технічна підтримка** – у разі сумнівів чи труднощів згідно з оновленням – відповariat вашому кабінеті або зв’яжтеся через чат‑під', '2026-06-04 17:29:50'),
(3, 1, 1, 'client', 'дякую', '2026-06-04 17:34:51'),
(4, 1, 0, 'admin', 'Радий допомогти! Якщо у вас залишилися питання або потрібна додаткова підтримка, будь ласка дайте знати — я завжди готовий допомогти.', '2026-06-04 23:34:24'),
(5, 1, 0, 'admin', 'Радий допомогти! Якщо щось ще потрібно — дайте знати.', '2026-06-04 23:34:44');

-- --------------------------------------------------------

--
-- Структура таблиці `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','client') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `name`, `created_at`, `updated_at`) VALUES
(1, 'roman@matviy.pp.ua', '$2y$10$vGPG3uQC0aShdHjduXj.a.yPjdA4qo3IaG8WvhTghP1EaDMiIlytq', 'client', 'roman55', '2026-06-04 01:05:43', '2026-06-04 17:53:31'),
(2, 'test2@example.com', '$2y$10$cE2tcCwBqbjb9V9GDBMd8.ZuKYcV2cIAukw/r6n5xckGv379tH2vi', 'client', 'test2', '2026-06-04 01:19:04', '2026-06-04 01:36:53');

-- --------------------------------------------------------

--
-- Структура таблиці `webhook_log`
--

CREATE TABLE `webhook_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paddle_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('ok','error','ignored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `payload` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `processed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `event_logs`
--
ALTER TABLE `event_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_event` (`event`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_created` (`created_at`);

--
-- Індекси таблиці `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Індекси таблиці `paddle_cache`
--
ALTER TABLE `paddle_cache`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`);

--
-- Індекси таблиці `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Індекси таблиці `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Індекси таблиці `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Індекси таблиці `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- Індекси таблиці `webhook_log`
--
ALTER TABLE `webhook_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `event_logs`
--
ALTER TABLE `event_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT для таблиці `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблиці `paddle_cache`
--
ALTER TABLE `paddle_cache`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблиці `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблиці `ticket_messages`
--
ALTER TABLE `ticket_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблиці `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблиці `webhook_log`
--
ALTER TABLE `webhook_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD CONSTRAINT `ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
