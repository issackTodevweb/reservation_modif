-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 22 août 2025 à 16:40
-- Version du serveur : 10.4.20-MariaDB
-- Version de PHP : 8.0.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `reservations_modif`
--

-- --------------------------------------------------------

--
-- Structure de la table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) CHARACTER SET latin1 NOT NULL,
  `action_details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action_type`, `action_details`, `created_at`) VALUES
(1, 12, 'Created reservation', 'Created reservation ID 0 for passenger issack Emmanuelito (Ticket: GM, Seat: 1A, Passport/CIN: 55465464, Total: 100 KMF)', '2025-08-22 09:49:20');

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `chat_notifications`
--

CREATE TABLE `chat_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `colis`
--

CREATE TABLE `colis` (
  `id` int(11) NOT NULL,
  `sender_name` varchar(255) CHARACTER SET latin1 NOT NULL,
  `sender_phone` varchar(20) CHARACTER SET latin1 NOT NULL,
  `package_description` text CHARACTER SET latin1 NOT NULL,
  `package_reference` varchar(50) CHARACTER SET latin1 NOT NULL,
  `receiver_name` varchar(255) CHARACTER SET latin1 NOT NULL,
  `receiver_phone` varchar(20) CHARACTER SET latin1 NOT NULL,
  `package_fee` decimal(10,2) NOT NULL,
  `payment_status` enum('Mvola','Espèce','Non payé') CHARACTER SET latin1 NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `crew_members`
--

CREATE TABLE `crew_members` (
  `id` int(11) NOT NULL,
  `name` varchar(100) CHARACTER SET latin1 NOT NULL,
  `role` varchar(50) CHARACTER SET latin1 NOT NULL,
  `status` enum('available','assigned','unavailable') CHARACTER SET latin1 DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `crew_members`
--

INSERT INTO `crew_members` (`id`, `name`, `role`, `status`, `created_at`) VALUES
(1, 'to', 'crew', 'available', '2025-08-22 09:51:50');

-- --------------------------------------------------------

--
-- Structure de la table `finances`
--

CREATE TABLE `finances` (
  `id` int(11) NOT NULL,
  `passenger_type` enum('Comorien Adulte','Comorien Enfant','Étranger Adulte','Étranger Enfant') NOT NULL,
  `trip_type` enum('Standard','Hors Standard') NOT NULL,
  `tariff` decimal(10,2) NOT NULL,
  `port_fee` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `direction` varchar(20) DEFAULT 'Aller'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `finances`
--

INSERT INTO `finances` (`id`, `passenger_type`, `trip_type`, `tariff`, `port_fee`, `tax`, `direction`) VALUES
(1, 'Comorien Adulte', 'Standard', '50.00', '20.00', '30.00', 'Aller');

-- --------------------------------------------------------

--
-- Structure de la table `reference`
--

CREATE TABLE `reference` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(20) NOT NULL,
  `reference_type` enum('R') NOT NULL,
  `status` enum('available','reserved') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `reference`
--

INSERT INTO `reference` (`id`, `reference_number`, `reference_type`, `status`, `created_at`) VALUES
(1, 'R-2025-0001', 'R', 'reserved', '2025-08-22 09:44:57'),
(2, 'R-2025-0002', 'R', 'available', '2025-08-22 09:44:57'),
(3, 'R-2025-0003', 'R', 'available', '2025-08-22 09:44:57'),
(4, 'R-2025-0004', 'R', 'available', '2025-08-22 09:44:57'),
(5, 'R-2025-0005', 'R', 'available', '2025-08-22 09:44:57'),
(6, 'R-2025-0006', 'R', 'available', '2025-08-22 09:44:57'),
(7, 'R-2025-0007', 'R', 'available', '2025-08-22 09:44:57'),
(8, 'R-2025-0008', 'R', 'available', '2025-08-22 09:44:57'),
(9, 'R-2025-0009', 'R', 'available', '2025-08-22 09:44:57'),
(10, 'R-2025-0010', 'R', 'available', '2025-08-22 09:44:57');

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `passenger_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `nationality` varchar(50) NOT NULL,
  `passenger_type` enum('Comorien Adulte','Comorien Enfant','Étranger Adulte','Étranger Enfant') NOT NULL,
  `with_baby` enum('Oui','Non') NOT NULL,
  `departure_date` date NOT NULL,
  `trip_id` int(11) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `ticket_type` varchar(10) NOT NULL DEFAULT '',
  `seat_number` varchar(2) NOT NULL,
  `payment_status` enum('Mvola','Espèce','Non payé') NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `passport_cin` varchar(20) NOT NULL,
  `modification` text DEFAULT NULL,
  `penalty` text DEFAULT NULL,
  `direction` varchar(20) DEFAULT 'Aller'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `passenger_name`, `phone_number`, `nationality`, `passenger_type`, `with_baby`, `departure_date`, `trip_id`, `reference_id`, `ticket_id`, `ticket_type`, `seat_number`, `payment_status`, `total_amount`, `created_at`, `passport_cin`, `modification`, `penalty`, `direction`) VALUES
(1, 12, 'issack Emmanuelito', '+261343852742', 'Comorien', 'Comorien Adulte', 'Non', '2025-08-23', 2, 1, 1, 'GM', '1A', 'Mvola', '100.00', '2025-08-22 09:49:20', '55465464', 'non', '0', 'Aller');

-- --------------------------------------------------------

--
-- Structure de la table `seats`
--

CREATE TABLE `seats` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `seat_number` varchar(2) NOT NULL,
  `status` enum('free','occupied','crew') DEFAULT 'free'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `seats`
--

INSERT INTO `seats` (`id`, `trip_id`, `seat_number`, `status`) VALUES
(1, 1, '1A', 'free'),
(2, 1, '1B', 'free'),
(3, 1, '1C', 'free'),
(4, 1, '2A', 'free'),
(5, 1, '2B', 'free'),
(6, 1, '2C', 'free'),
(7, 1, '2D', 'free'),
(8, 1, '3A', 'free'),
(9, 1, '3B', 'free'),
(10, 1, '3C', 'free'),
(11, 1, '3D', 'free'),
(12, 1, '3E', 'free'),
(13, 1, '3F', 'free'),
(14, 1, '4A', 'free'),
(15, 1, '4B', 'free'),
(16, 1, '4C', 'free'),
(17, 1, '4D', 'free'),
(18, 1, '4E', 'free'),
(19, 1, '4F', 'free'),
(20, 1, '5A', 'free'),
(21, 1, '5B', 'free'),
(22, 1, '5C', 'free'),
(23, 1, '5D', 'free'),
(24, 1, '5E', 'free'),
(25, 1, '5F', 'free'),
(26, 1, '6A', 'free'),
(27, 1, '6B', 'free'),
(28, 1, '6C', 'free'),
(29, 1, '6D', 'free'),
(30, 2, '1A', 'occupied'),
(31, 2, '1B', 'free'),
(32, 2, '1C', 'free'),
(33, 2, '2A', 'free'),
(34, 2, '2B', 'free'),
(35, 2, '2C', 'free'),
(36, 2, '2D', 'free'),
(37, 2, '3A', 'free'),
(38, 2, '3B', 'free'),
(39, 2, '3C', 'free'),
(40, 2, '3D', 'free'),
(41, 2, '3E', 'free'),
(42, 2, '3F', 'free'),
(43, 2, '4A', 'free'),
(44, 2, '4B', 'free'),
(45, 2, '4C', 'free'),
(46, 2, '4D', 'free'),
(47, 2, '4E', 'free'),
(48, 2, '4F', 'free'),
(49, 2, '5A', 'free'),
(50, 2, '5B', 'free'),
(51, 2, '5C', 'free'),
(52, 2, '5D', 'free'),
(53, 2, '5E', 'free'),
(54, 2, '5F', 'free'),
(55, 2, '6A', 'free'),
(56, 2, '6B', 'free'),
(57, 2, '6C', 'free'),
(58, 2, '6D', 'free'),
(59, 3, '1A', 'free'),
(60, 3, '1B', 'free'),
(61, 3, '1C', 'free'),
(62, 3, '2A', 'free'),
(63, 3, '2B', 'free'),
(64, 3, '2C', 'free'),
(65, 3, '2D', 'free'),
(66, 3, '3A', 'free'),
(67, 3, '3B', 'free'),
(68, 3, '3C', 'free'),
(69, 3, '3D', 'free'),
(70, 3, '3E', 'free'),
(71, 3, '3F', 'free'),
(72, 3, '4A', 'free'),
(73, 3, '4B', 'free'),
(74, 3, '4C', 'free'),
(75, 3, '4D', 'free'),
(76, 3, '4E', 'free'),
(77, 3, '4F', 'free'),
(78, 3, '5A', 'free'),
(79, 3, '5B', 'free'),
(80, 3, '5C', 'free'),
(81, 3, '5D', 'free'),
(82, 3, '5E', 'free'),
(83, 3, '5F', 'free'),
(84, 3, '6A', 'free'),
(85, 3, '6B', 'free'),
(86, 3, '6C', 'free'),
(87, 3, '6D', 'free'),
(88, 4, '1A', 'free'),
(89, 4, '1B', 'free'),
(90, 4, '1C', 'crew'),
(91, 4, '2A', 'free'),
(92, 4, '2B', 'free'),
(93, 4, '2C', 'free'),
(94, 4, '2D', 'free'),
(95, 4, '3A', 'free'),
(96, 4, '3B', 'free'),
(97, 4, '3C', 'free'),
(98, 4, '3D', 'free'),
(99, 4, '3E', 'free'),
(100, 4, '3F', 'free'),
(101, 4, '4A', 'free'),
(102, 4, '4B', 'free'),
(103, 4, '4C', 'free'),
(104, 4, '4D', 'free'),
(105, 4, '4E', 'free'),
(106, 4, '4F', 'free'),
(107, 4, '5A', 'free'),
(108, 4, '5B', 'free'),
(109, 4, '5C', 'free'),
(110, 4, '5D', 'free'),
(111, 4, '5E', 'free'),
(112, 4, '5F', 'free'),
(113, 4, '6A', 'free'),
(114, 4, '6B', 'free'),
(115, 4, '6C', 'free'),
(116, 4, '6D', 'free');

-- --------------------------------------------------------

--
-- Structure de la table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `ticket_type` enum('GM','MG','GA','AM','MA','AG') NOT NULL,
  `status` enum('available','reserved') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_number`, `ticket_type`, `status`, `created_at`) VALUES
(1, 'GM2025-01', 'GM', 'reserved', '2025-08-22 09:44:25'),
(2, 'GM2025-02', 'GM', 'available', '2025-08-22 09:44:25'),
(3, 'GM2025-03', 'GM', 'available', '2025-08-22 09:44:25'),
(4, 'GM2025-04', 'GM', 'available', '2025-08-22 09:44:25'),
(5, 'GM2025-05', 'GM', 'available', '2025-08-22 09:44:25'),
(6, 'GM2025-06', 'GM', 'available', '2025-08-22 09:44:25'),
(7, 'GM2025-07', 'GM', 'available', '2025-08-22 09:44:25'),
(8, 'GM2025-08', 'GM', 'available', '2025-08-22 09:44:25'),
(9, 'GM2025-09', 'GM', 'available', '2025-08-22 09:44:25'),
(10, 'GM2025-10', 'GM', 'available', '2025-08-22 09:44:25');

-- --------------------------------------------------------

--
-- Structure de la table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `type` varchar(20) CHARACTER SET latin1 NOT NULL,
  `departure_port` varchar(50) CHARACTER SET latin1 NOT NULL,
  `arrival_port` varchar(50) CHARACTER SET latin1 NOT NULL,
  `departure_date` date NOT NULL,
  `departure_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `trips`
--

INSERT INTO `trips` (`id`, `type`, `departure_port`, `arrival_port`, `departure_date`, `departure_time`, `created_at`) VALUES
(1, 'aller', 'MWA', 'ANJ', '2025-08-22', '10:00:00', '2025-08-22 09:40:31'),
(2, 'aller', 'MWA', 'ANJ', '2025-08-23', '08:00:00', '2025-08-22 09:40:31'),
(3, 'aller', 'MWA', 'ANJ', '2025-08-24', '10:00:00', '2025-08-22 09:40:31'),
(4, 'aller', 'MWA', 'ANJ', '2025-08-25', '08:00:00', '2025-08-22 09:40:31');

-- --------------------------------------------------------

--
-- Structure de la table `trip_crew`
--

CREATE TABLE `trip_crew` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `crew_member_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `trip_crew`
--

INSERT INTO `trip_crew` (`id`, `trip_id`, `crew_member_id`, `created_at`) VALUES
(1, 2, 1, '2025-08-22 09:51:50');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$cCJ8q8gchpl7ZELUwyK9IeclTlrzy9squcMJny9wwXi.krVjiZqa2', 'admin', '2025-08-04 12:40:14'),
(2, 'manou', '$2y$10$VdgQJXwv0H2eSauNaoH76eYJWPDgSEAiieMjDnZHSQ55y1loIWPCq', '', '2025-08-04 13:05:40'),
(12, 'tatata', '$2y$10$4dcnUWq.MdJ8riuG/M8zTuiUOmijQN8CXOfoOxws7fLHMWH8vpiHy', 'user', '2025-08-17 13:23:11');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `message_id` (`message_id`);

--
-- Index pour la table `colis`
--
ALTER TABLE `colis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `crew_members`
--
ALTER TABLE `crew_members`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `finances`
--
ALTER TABLE `finances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `passenger_trip_direction` (`passenger_type`,`trip_type`,`direction`);

--
-- Index pour la table `reference`
--
ALTER TABLE `reference`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`);

--
-- Index pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trip_id` (`trip_id`,`seat_number`),
  ADD UNIQUE KEY `reference_id` (`reference_id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `seats`
--
ALTER TABLE `seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trip_id` (`trip_id`,`seat_number`);

--
-- Index pour la table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`);

--
-- Index pour la table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `trip_crew`
--
ALTER TABLE `trip_crew`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `crew_member_id` (`crew_member_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `colis`
--
ALTER TABLE `colis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `crew_members`
--
ALTER TABLE `crew_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `finances`
--
ALTER TABLE `finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `reference`
--
ALTER TABLE `reference`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `seats`
--
ALTER TABLE `seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT pour la table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `trip_crew`
--
ALTER TABLE `trip_crew`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
