-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2026 at 09:20 AM
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
-- Database: `iskonek_db`
--
DROP DATABASE IF EXISTS `iskonek_db`;
CREATE DATABASE IF NOT EXISTS `iskonek_db`;
USE `iskonek_db`;
-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `subject_id`, `enrolled_at`) VALUES
(7, 8, 7, '2026-06-17 04:53:15');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `category` enum('event','deadline','exam','activity','holiday') NOT NULL DEFAULT 'event',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migration: run this if the events table already exists in your DB
-- ALTER TABLE `events` ADD COLUMN `category` enum('event','deadline','exam','activity','holiday') NOT NULL DEFAULT 'event' AFTER `event_time`;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `event_time`, `created_by`, `created_at`) VALUES
(1, 'Final Examinations', 'Week-long final exams for all subjects.', '2026-06-15', NULL, 1, '2026-06-05 01:44:44'),
(2, 'Enrollment Period', 'Next semester enrollment opens.', '2026-07-01', NULL, 1, '2026-06-05 01:44:44');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(80) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_replies`
--

CREATE TABLE `forum_replies` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kanban_tasks`
--

CREATE TABLE `kanban_tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `subject` varchar(80) NOT NULL DEFAULT 'General',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('todo','progress','review','done') NOT NULL DEFAULT 'todo',
  `due_date` date DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `managers`
-- One row per section (UNIQUE on section_id) so each section has at most one manager.
-- The managers.user_id is kept in sync with sections.manager_id.
--

CREATE TABLE `managers` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migration: ensure the unique key exists on legacy databases
-- ALTER TABLE `managers` ADD UNIQUE KEY `uniq_section` (`section_id`);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('announcement','event') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL,
  `program` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `manager_id` int(11) DEFAULT NULL,
  `year` tinyint(4) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `section` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `program`, `created_at`, `manager_id`, `year`, `school_year`, `section`) VALUES
(1, 'BSIT 2-1', 'BSIT', '2026-06-06 13:14:21', NULL, 0, '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `section_subjects`
--

CREATE TABLE `section_subjects` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(160) NOT NULL,
  `instructor` varchar(120) DEFAULT NULL,
  `units` tinyint(4) DEFAULT 3,
  `enroll_key` varchar(40) NOT NULL,
  `color` varchar(10) DEFAULT '#33b77a',
  `semester` varchar(40) DEFAULT '2nd Sem, A.Y. 2024-25',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `code`, `name`, `instructor`, `units`, `enroll_key`, `color`, `semester`, `created_at`) VALUES
(7, 'COMP 009', 'Object Oriented Programming', 'VELASCO, MARIENEL N.', 3, 'KEY-COMP009', '#33b77a', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(8, 'COMP 010', 'Information Management', 'CASTILLO, MELANI L.', 3, 'KEY-COMP010', '#4a90d9', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(9, 'COMP 012', 'Network Administration', 'DELA REA, ARIS P.', 3, 'KEY-COMP012', '#d23c3c', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(10, 'COMP 013', 'Human Computer Interaction', 'DE CLARO, CHRISTOPHER JAY CASTRONERO', 3, 'KEY-COMP013', '#f5a623', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(11, 'COMP 014', 'Quantitative Methods with Modeling and Simulation', 'YEDRA, FE DALANGIN', 3, 'KEY-COMP014', '#9b59b6', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(12, 'ELEC IT-FE2', 'BSIT Free Elective 2', 'MADERA, JOSHUA FANDIALAN', 3, 'KEY-ELECFE2', '#1abc9c', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(13, 'INTE 202', 'Integrative Programming and Technologies 1', 'MADERA, JOSHUA FANDIALAN', 3, 'KEY-INTE202', '#e67e22', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20'),
(14, 'PATHFIT 4', 'Physical Activity Towards Health and Fitness 4', 'CALABIG, MICHAEL F.', 2, 'KEY-PATHFIT4', '#2980b9', '2nd Sem, A.Y. 2024-25', '2026-06-05 07:45:20');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('todo','in_progress','done') DEFAULT 'todo',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `mid_name` varchar(120) DEFAULT NULL,
  `last_name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `student_id` varchar(30) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `role` varchar(80) DEFAULT 'Student',
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `mid_name`, `last_name`, `email`, `student_id`, `section_id`, `role`, `password`, `avatar`, `created_at`) VALUES
(1, 'Ryan Pol Malabanan', NULL, '', 'ryan@school.edu.ph', '2022-10234', NULL, 'Student', '$2y$12$n4XRSfVJ4c3P5bQ/2fKzH.4q6aA1RY9kGwkl3tD.xUkdUfyF5jAoG', NULL, '2026-06-05 01:44:44'),
(3, 'Calyx Warden', NULL, '', 'calyxwarden0315@gmail.com', '2024-00502-ST-0', NULL, 'Admin', 'pogiako123', NULL, '2026-06-05 01:58:39'),
(5, 'Gab', NULL, '', 'gab@pup.com', '2024-00030-ST-0', NULL, 'admin', '$2y$10$w4U37CLzMUnGkxpYbv9nzuyRJFtaJo/DwhRkHJ0/EsjKnhGi90sci', NULL, '2026-06-10 03:42:21'),
(6, 'Juan', NULL, '', 'juan@pup.com', '2022-00030-ST-0', NULL, 'Student', '$2y$10$c66O0eeHta8EhP6TvMB.h.oQIRLPx14HYNVhY6Ik5vRcYhI7IfCgC', NULL, '2026-06-10 04:04:07'),
(7, 'Edward', 'I.', 'Guevarra', 'edward.guevarra42@gmail.com', '2024', NULL, 'Student', '$2y$10$sSMP94tvMbMEo.q6ME1s6.ANdi4hcyTk1LfPr.klMrfuYbDnx0RBq', NULL, '2026-06-17 04:36:45'),
(8, 'calyx', 'd.', 'seloso', 'calyxseloso@gmail.com', '2026', NULL, 'Student', '$2y$10$NQIxfAWGCHMvQleH551Sru3nZz3z/Xjfj7Mkj.5VDurc14TmgtKWe', NULL, '2026-06-17 04:43:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_enroll` (`user_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `forum_replies`
--
ALTER TABLE `forum_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `kanban_tasks`
--
ALTER TABLE `kanban_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kanban_user_status` (`user_id`,`status`);

--
-- Indexes for table `managers`
--
ALTER TABLE `managers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_manager` (`section_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `fk_section_manager` (`manager_id`);

--
-- Indexes for table `section_subjects`
--
ALTER TABLE `section_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_subject` (`section_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_section` (`section_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_replies`
--
ALTER TABLE `forum_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kanban_tasks`
--
ALTER TABLE `kanban_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `managers`
--
ALTER TABLE `managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `section_subjects`
--
ALTER TABLE `section_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_replies`
--
ALTER TABLE `forum_replies`
  ADD CONSTRAINT `forum_replies_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kanban_tasks`
--
ALTER TABLE `kanban_tasks`
  ADD CONSTRAINT `kanban_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `managers`
--
ALTER TABLE `managers`
  ADD CONSTRAINT `managers_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `managers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_section_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `section_subjects`
--
ALTER TABLE `section_subjects`
  ADD CONSTRAINT `section_subjects_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `section_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

ALTER TABLE `notifications` MODIFY COLUMN `type` ENUM('announcement','event','forum_reply') NOT NULL;

CREATE TABLE IF NOT EXISTS `forum_followers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_follow` (`user_id`,`post_id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `forum_followers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_followers_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;