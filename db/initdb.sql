-- Adminer 5.4.2 MariaDB 11.8.6-MariaDB-ubu2404 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

CREATE DATABASE `db_example_gg_632b3722` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_example_gg_632b3722`;

CREATE DATABASE `db_www_404error_com_13d32bbf` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_www_404error_com_13d32bbf`;

CREATE DATABASE `db_www_blackfrydaysales_com_2bc479e6` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_www_blackfrydaysales_com_2bc479e6`;

CREATE DATABASE `db_www_monitor_com_c7d67c06` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_www_monitor_com_c7d67c06`;

CREATE DATABASE `db_www_mrcamel_com_5efcd47a` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_www_mrcamel_com_5efcd47a`;

CREATE DATABASE `db_www_randomizer_com_8ba7f97a` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `db_www_randomizer_com_8ba7f97a`;

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
(1,	1,	'example.gg',	'2026-04-04 18:33:44'),
(2,	2,	'www.mrcamel.com',	'2026-04-08 20:13:33'),
(3,	3,	'www.monitor.com',	'2026-04-08 20:20:47'),
(4,	4,	'www.404error.com',	'2026-04-08 20:27:00'),
(5,	5,	'www.blackfrydaysales.com',	'2026-04-08 20:30:57'),
(6,	6,	'www.randomizer.com',	'2026-04-10 13:48:55');

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
(1,	'test',	'$2y$10$weOfeB1Vzvb1yDVZ3aQqt.f/CGuhHo6lvXio8j284Q8qpUWpmF1RW',	'94VOCUNjHUgArKMX1Pr58nlXaGZ2aDFxVUdXMXZmWVBqclIxb2c9PQ=='),
(2,	'MrCamel',	'$2y$10$J6WPbVmldRG0vT3Hc23tQum/pFODwRS3ZXYeP.8c/LJACoaDc/50y',	'QD/i45omH9mawVkcxsGNe0JBY2YyNXkyVzlxZkczTWtON2QrdVE9PQ=='),
(3,	'Monitor',	'$2y$10$8Zt/ckmzV85h0C0uXOhZK.VrkI8THNHkVIPI7Kz3VkmvL7d0Rxlou',	'UyMht8N6oQeyE+eHkdkN7nBENjBnWHN4VUV3UmlrMFdUcHV5YXc9PQ=='),
(4,	'404Screens',	'$2y$10$yQOU8fBXUIiwJrYNjvWs9.29hIYiDessluRs9DYVLZlq442NGklj.',	'elCcKFoT69fLfR3oIGRrATZvVVozMWVOT01vaC8xQjNrcnJIbVE9PQ=='),
(5,	'BlackFRSales',	'$2y$10$O5wWj5zMW2lYTsAIafr6Vu2/wJW1K7pBVgsbfvzUjJarqiAwrl6/e',	'W/mkKw5zAVAUAjehIpq6AWthUWhaMUo1eE5mZVlncXpaY0EyekE9PQ=='),
(6,	'Random',	'$2y$10$0s97qUGEVuPqA/hrlAl.VOSNPjqN1e4/oFWndc1OBqrGPPmp6w.Nm',	'GPU0wVWQXxY9yTUM3M8lazErcXk0Q05BNytnVUllaGFhT3pJT2c9PQ==');

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
(1,	1,	'db_example_gg_632b3722',	'u_test_632b3722',	'eac39abe16c166c9',	'2026-04-04 18:33:44'),
(2,	2,	'db_www_mrcamel_com_5efcd47a',	'u_mrcamel_5efcd47a',	'ddb7d09505e4e6f2',	'2026-04-08 20:13:33'),
(3,	3,	'db_www_monitor_com_c7d67c06',	'u_monitor_c7d67c06',	'43e8e8417bf7d133',	'2026-04-08 20:20:47'),
(4,	4,	'db_www_404error_com_13d32bbf',	'u_404screens_13d32bbf',	'2b685f8a420a265b',	'2026-04-08 20:27:00'),
(5,	5,	'db_www_blackfrydaysales_com_2bc479e6',	'u_blackfrsales_2bc479e6',	'445de27f7f1d8b2d',	'2026-04-08 20:30:57'),
(6,	6,	'db_www_randomizer_com_8ba7f97a',	'u_random_8ba7f97a',	'5aba0bb7230d2044',	'2026-04-10 13:48:55');

-- 2026-04-10 20:22:02 UTC
