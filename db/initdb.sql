-- Adminer 5.4.2 MariaDB 11.8.6-MariaDB-ubu2404 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `db_example_gg_632b3722`;
CREATE DATABASE `db_example_gg_632b3722` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_example_gg_632b3722`;

DROP DATABASE IF EXISTS `hosting_centrum`;
CREATE DATABASE `hosting_centrum` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `hosting_centrum`;

DROP TABLE IF EXISTS `domains`;
CREATE TABLE `domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `domain_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain_name` (`domain_name`),
  KEY `fk_domains_user` (`user_id`),
  CONSTRAINT `fk_domains_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `domains` (`id`, `user_id`, `domain_name`, `created_at`) VALUES
(1,	1,	'example.gg',	'2026-04-04 18:33:44');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `ftp_password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `users` (`id`, `username`, `password`, `ftp_password`) VALUES
(1,	'test',	'$2y$10$weOfeB1Vzvb1yDVZ3aQqt.f/CGuhHo6lvXio8j284Q8qpUWpmF1RW',	'94VOCUNjHUgArKMX1Pr58nlXaGZ2aDFxVUdXMXZmWVBqclIxb2c9PQ==');

DROP TABLE IF EXISTS `user_databases`;
CREATE TABLE `user_databases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `db_name` varchar(100) NOT NULL,
  `db_user` varchar(100) NOT NULL,
  `db_password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `db_name` (`db_name`),
  UNIQUE KEY `db_user` (`db_user`),
  KEY `fk_user_databases_domain` (`domain_id`),
  CONSTRAINT `fk_user_databases_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `user_databases` (`id`, `domain_id`, `db_name`, `db_user`, `db_password`, `created_at`) VALUES
(1,	1,	'db_example_gg_632b3722',	'u_test_632b3722',	'eac39abe16c166c9',	'2026-04-04 18:33:44');

-- 2026-04-04 19:12:39 UTC
