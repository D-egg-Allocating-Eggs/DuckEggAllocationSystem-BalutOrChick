START TRANSACTION;

CREATE TABLE `egg` (
  `egg_id` int AUTO_INCREMENT PRIMARY KEY,
  `total_egg` int(11) NOT NULL,
  `status` varchar(30) NOT NULL,
  `date_started_incubation` date NOT NULL,
  `balut_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `chick_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_id` int AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_activity_logs` (
  `log_id` int AUTO_INCREMENT PRIMARY KEY,
  `action` varchar(100) DEFAULT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;