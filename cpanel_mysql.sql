-- Chatting app MySQL dump for cPanel phpMyAdmin
-- Generated: 2026-08-12T04:21:36+02:00
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT,
  `username` TEXT NOT NULL,
  `password_hash` TEXT NOT NULL,
  `email` TEXT,
  `phone` TEXT,
  `avatar_path` TEXT,
  `status_message` TEXT,
  `last_seen` DATETIME,
  `online_status` TEXT DEFAULT 'offline',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `call_status` TEXT DEFAULT 'available',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `phone`, `avatar_path`, `status_message`, `last_seen`, `online_status`, `created_at`, `updated_at`, `call_status`) VALUES
('1', 'jasper', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, NULL, '2026-08-04 00:53:17', 'online', '2025-10-03 01:39:54', '2025-10-03 01:39:54', 'available'),
('2', 'alice', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, NULL, NULL, 'offline', '2025-10-03 01:39:54', '2025-10-03 01:39:54', 'available'),
('3', 'bob', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, NULL, NULL, 'offline', '2025-10-03 01:39:54', '2025-10-03 01:39:54', 'available'),
('4', 'abcd', '$2y$10$YFk.lWqk0xbmAfj2E9uc5ul9yBPsC2uGzfmV2lUOePT.EAnaIxoVO', NULL, NULL, NULL, NULL, NULL, 'offline', '2025-10-06 01:25:04', '2025-10-06 01:25:04', 'available'),
('5', 'admin', '$2y$10$VLLTjDYYEW.yOmv/Kfvgfe99.wOtWCMr2v9f/60MugXUnrvzuuUT.', NULL, NULL, NULL, NULL, NULL, 'offline', '2025-10-06 06:15:30', '2025-10-06 06:15:30', 'available'),
('6', 'vincent', '$2y$10$xRg9MvEgrgrZZlFksoRgpecNT/6LogdTYhNEvf05XOxRmscN2HBOa', NULL, NULL, NULL, NULL, NULL, 'offline', '2026-02-26 00:54:36', '2026-02-26 00:54:36', 'available'),
('7', 'yuan', '$2y$10$A9/2K5QrEtJCB0UxNTwUJOp..kLIG2zhQFvEhoOl02XVQJqVLq316', NULL, NULL, NULL, NULL, NULL, 'offline', '2026-02-26 03:24:36', '2026-02-26 03:24:36', 'available'),
('8', 'user', '$2y$10$vqVz7TX3nR9paCecCvTf1elOYVUzjvXamzChPbOP/JxHc3QxRUoI.', NULL, NULL, NULL, NULL, NULL, 'offline', '2026-06-18 13:27:37', '2026-06-18 13:27:37', 'available'),
('9', 'jasper_06', '$2y$10$.oWo7zEfi9QzkMF4bYaIWuXn7NhHkOenCs7rh2Ex6cANeHmSj8jRu', NULL, NULL, NULL, NULL, NULL, 'offline', '2026-08-03 13:24:52', '2026-08-03 13:24:52', 'available'),
('10', 'kaizhi', '$2y$10$zYteNwC7e.MbHpF8h4RDnelhrXx5zo0cg8Y6K1cS/Ct6YiD1rY7WK', NULL, NULL, NULL, NULL, NULL, 'offline', '2026-08-03 13:26:41', '2026-08-03 13:26:41', 'available');

DROP TABLE IF EXISTS `friend_requests`;
CREATE TABLE `friend_requests` (
  `id` INT AUTO_INCREMENT,
  `requester_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `status` TEXT NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `friend_requests` (`id`, `requester_id`, `receiver_id`, `status`, `created_at`) VALUES
('1', '3', '1', 'accepted', '2025-10-06 01:27:53'),
('2', '5', '1', 'accepted', '2025-10-06 06:18:56'),
('3', '10', '9', 'accepted', '2026-08-03 13:26:52');

DROP TABLE IF EXISTS `friendships`;
CREATE TABLE `friendships` (
  `user_id` INT NOT NULL,
  `friend_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`friend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `friendships` (`user_id`, `friend_id`, `created_at`) VALUES
('1', '2', '2025-10-06 01:27:53'),
('2', '1', '2025-10-06 01:27:53'),
('1', '3', '2025-10-06 02:08:42'),
('3', '1', '2025-10-06 02:08:42'),
('1', '5', '2025-10-06 06:19:02'),
('5', '1', '2025-10-06 06:19:02'),
('9', '10', '2026-08-03 13:26:59'),
('10', '9', '2026-08-03 13:26:59');

DROP TABLE IF EXISTS `user_blocks`;
CREATE TABLE `user_blocks` (
  `id` INT AUTO_INCREMENT,
  `blocker_id` INT NOT NULL,
  `blocked_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` INT AUTO_INCREMENT,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `message_text` TEXT,
  `message_type` TEXT DEFAULT 'text',
  `file_path` TEXT,
  `file_name` TEXT,
  `file_size` INT,
  `duration` INT,
  `reply_to_message_id` INT,
  `edited_at` DATETIME,
  `deleted_at` DATETIME,
  `delivery_status` TEXT DEFAULT 'sent',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message_text`, `message_type`, `file_path`, `file_name`, `file_size`, `duration`, `reply_to_message_id`, `edited_at`, `deleted_at`, `delivery_status`, `created_at`) VALUES
('1', '1', '2', 'Hello Alice! How are you?', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', '2025-10-03 01:39:54'),
('2', '2', '1', 'Hi Jasper! I''m doing great, thanks!', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-03 01:39:54'),
('3', '1', '3', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'delivered', '2025-10-06 02:08:49'),
('4', '1', '3', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'delivered', '2025-10-06 02:08:53'),
('5', '1', '5', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 06:19:10'),
('6', '5', '1', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 06:19:36'),
('7', '1', '5', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 06:24:19'),
('8', '1', '5', 'xdvsgdsbdbd', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 06:24:42'),
('9', '5', '1', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:01:39'),
('10', '5', '1', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:02:01'),
('11', '5', '1', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:09:03'),
('12', '1', '5', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:09:46'),
('13', '1', '5', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:24:39'),
('14', '1', '5', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:24:47'),
('15', '5', '1', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:24:58'),
('16', '5', '1', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2025-10-06 07:37:55'),
('17', '10', '9', 'kz', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2026-08-03 13:27:22'),
('18', '9', '10', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2026-08-03 13:27:36'),
('19', '9', '10', 'hihihiihihihi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2026-08-03 13:30:30'),
('20', '9', '10', 'hi', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2026-08-03 13:50:19'),
('21', '9', '10', '****', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2026-08-03 14:12:54'),
('22', '10', '9', '**** u too', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'read', '2026-08-03 14:13:17');

DROP TABLE IF EXISTS `message_reactions`;
CREATE TABLE `message_reactions` (
  `id` INT AUTO_INCREMENT,
  `message_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `reaction` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `group_chats`;
CREATE TABLE `group_chats` (
  `id` INT AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `description` TEXT,
  `avatar_path` TEXT,
  `creator_id` INT NOT NULL,
  `max_members` INT DEFAULT 256,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `group_chats` (`id`, `name`, `description`, `avatar_path`, `creator_id`, `max_members`, `created_at`, `updated_at`) VALUES
('1', 'General Chat', 'Welcome to our chat group!', NULL, '1', '256', '2025-10-03 01:39:54', '2025-10-03 01:39:54');

DROP TABLE IF EXISTS `group_members`;
CREATE TABLE `group_members` (
  `group_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` TEXT DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_message_id` INT,
  PRIMARY KEY (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `group_members` (`group_id`, `user_id`, `role`, `joined_at`, `last_read_message_id`) VALUES
('1', '1', 'owner', '2025-10-03 01:39:54', NULL),
('1', '2', 'member', '2025-10-03 01:39:54', NULL),
('1', '3', 'member', '2025-10-03 01:39:54', NULL);

DROP TABLE IF EXISTS `group_messages`;
CREATE TABLE `group_messages` (
  `id` INT AUTO_INCREMENT,
  `group_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `message_text` TEXT,
  `message_type` TEXT DEFAULT 'text',
  `file_path` TEXT,
  `file_name` TEXT,
  `file_size` INT,
  `duration` INT,
  `reply_to_message_id` INT,
  `mentioned_users` TEXT,
  `edited_at` DATETIME,
  `deleted_at` DATETIME,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `group_messages` (`id`, `group_id`, `sender_id`, `message_text`, `message_type`, `file_path`, `file_name`, `file_size`, `duration`, `reply_to_message_id`, `mentioned_users`, `edited_at`, `deleted_at`, `created_at`) VALUES
('1', '1', '1', 'Welcome everyone to our chat group!', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-03 01:39:54'),
('2', '1', '2', 'Thanks for creating this group!', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-03 01:39:54'),
('3', '1', '3', 'Happy to be here!', 'text', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-03 01:39:54');

DROP TABLE IF EXISTS `group_message_reactions`;
CREATE TABLE `group_message_reactions` (
  `id` INT AUTO_INCREMENT,
  `message_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `reaction` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `forums`;
CREATE TABLE `forums` (
  `id` INT AUTO_INCREMENT,
  `title` TEXT NOT NULL,
  `description` TEXT,
  `creator_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `forums` (`id`, `title`, `description`, `creator_id`, `created_at`) VALUES
('1', 'General Discussion', 'Talk about anything here!', '1', '2025-10-03 01:45:24'),
('2', 'Tech Talk', 'Discuss technology and programming', '1', '2025-10-03 01:45:24');

DROP TABLE IF EXISTS `forum_posts`;
CREATE TABLE `forum_posts` (
  `id` INT AUTO_INCREMENT,
  `forum_id` INT NOT NULL,
  `author_id` INT NOT NULL,
  `title` TEXT NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `forum_posts` (`id`, `forum_id`, `author_id`, `title`, `content`, `created_at`, `updated_at`) VALUES
('1', '1', '1', 'Welcome to our forum!', 'This is the first post on our forum. Feel free to share your thoughts and ideas here!', '2025-10-03 01:45:24', '2025-10-03 01:45:24'),
('2', '1', '2', 'Hello everyone!', 'Nice to meet you all. Looking forward to great discussions!', '2025-10-03 01:45:24', '2025-10-03 01:45:24'),
('3', '2', '3', 'Latest programming trends', 'What programming languages are you learning this year?', '2025-10-03 01:45:24', '2025-10-03 01:45:24');

DROP TABLE IF EXISTS `post_likes`;
CREATE TABLE `post_likes` (
  `post_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `post_comments`;
CREATE TABLE `post_comments` (
  `id` INT AUTO_INCREMENT,
  `post_id` INT NOT NULL,
  `author_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `post_comments` (`id`, `post_id`, `author_id`, `content`, `created_at`) VALUES
('1', '2', '6', 'fuck u', '2026-02-26 01:59:05');

DROP TABLE IF EXISTS `post_shares`;
CREATE TABLE `post_shares` (
  `id` INT AUTO_INCREMENT,
  `post_id` INT NOT NULL,
  `sharer_id` INT NOT NULL,
  `shared_with_id` INT NOT NULL,
  `message` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `voice_call_sessions`;
CREATE TABLE `voice_call_sessions` (
  `id` INT AUTO_INCREMENT,
  `caller_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `call_type` TEXT NOT NULL DEFAULT 'voice',
  `status` TEXT NOT NULL DEFAULT 'calling',
  `room_id` TEXT,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `connected_at` DATETIME,
  `ended_at` DATETIME,
  `duration` INT DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `voice_call_sessions` (`id`, `caller_id`, `receiver_id`, `call_type`, `status`, `room_id`, `started_at`, `connected_at`, `ended_at`, `duration`) VALUES
('2', '1', '2', 'voice', 'ended', 'diag_1785804797', '2026-08-04 00:53:17', NULL, '2026-08-04 00:53:17', '0');

DROP TABLE IF EXISTS `voice_call_signals`;
CREATE TABLE `voice_call_signals` (
  `id` INT AUTO_INCREMENT,
  `call_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `signal_type` TEXT NOT NULL,
  `payload` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;