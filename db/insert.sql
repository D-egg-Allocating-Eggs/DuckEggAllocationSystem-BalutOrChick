START TRANSACTION;

INSERT INTO `egg` (`total_egg`, `status`, `date_started_incubation`, `balut_count`, `failed_count`, `chick_count`) VALUES
(50, 'incubating', '2026-03-05', 0, 0, 0);

INSERT INTO `users` (`username`) VALUES
('admin');

INSERT INTO `user_activity_logs` (`action`, `log_date`) VALUES
('insert_test_data', '2026-03-05 01:34:17');

COMMIT;