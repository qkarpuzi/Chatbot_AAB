-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 11, 2026 at 07:26 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aab_chatbot_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int NOT NULL,
  `user_question` text COLLATE utf8mb4_general_ci NOT NULL,
  `bot_response` text COLLATE utf8mb4_general_ci,
  `matched_type` enum('location','faq','unknown') COLLATE utf8mb4_general_ci DEFAULT 'unknown',
  `matched_location_id` int DEFAULT NULL,
  `matched_faq_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `default_responses`
--

CREATE TABLE `default_responses` (
  `default_response_id` int NOT NULL,
  `response_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `directions`
--

CREATE TABLE `directions` (
  `direction_id` int NOT NULL,
  `from_location_id` int DEFAULT NULL,
  `to_location_id` int DEFAULT NULL,
  `instruction` text COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `faq_id` int NOT NULL,
  `question` text COLLATE utf8mb4_general_ci NOT NULL,
  `answer` text COLLATE utf8mb4_general_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq_categories`
--

CREATE TABLE `faq_categories` (
  `category_id` int NOT NULL,
  `category_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq_keywords`
--

CREATE TABLE `faq_keywords` (
  `faq_keyword_id` int NOT NULL,
  `keyword` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `faq_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keywords`
--

CREATE TABLE `keywords` (
  `keyword_id` int NOT NULL,
  `keyword` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `intent_type` enum('location','faq') COLLATE utf8mb4_general_ci NOT NULL,
  `location_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `floor` int DEFAULT NULL,
  `room_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `x_coord` decimal(10,2) DEFAULT NULL,
  `y_coord` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `name`, `description`, `floor`, `room_number`, `x_coord`, `y_coord`, `is_active`) VALUES
(1, 'Administrata', 'Kryhen shërbime administrative si regjistrimi, pagesat dhe marrja e dokumenteve zyrtare.', 0, '0', 0.00, 0.00, 0),
(2, 'Alba Dent', 'Ofron shërbime stomatologjike.', 0, '0', 0.00, 0.00, 0),
(3, 'Arkiva', 'Vend për ruajtjen e dokumenteve zyrtare dhe të dhënave administrative të studentëve dhe institucionit.', 0, '0', 0.00, 0.00, 0),
(4, 'Biblioteka', 'Ofron ambient të qetë për studim dhe kërkim; studentët mund të përdorin libra, kompjuterë dhe burime të tjera akademike.', 0, '0', 0.00, 0.00, 0),
(5, 'Dekanati Juridik', 'Njësia e Fakultetit Juridik që menaxhon studimet dhe aktivitetet akademike të studentëve.', 0, '0', 0.00, 0.00, 0),
(6, 'Dekanati i Administratës Publike ', 'Njësi akademike që organizon dhe menaxhon studimet në fushën e administratës publike dhe shërbimeve shtetërore.', 0, '0', 0.00, 0.00, 0),
(7, 'Dekanati Ekonomik ', 'Njësia që menaxhon studimet dhe aktivitetet akademike në fushën e ekonomisë dhe biznesit.', 0, '0', 0.00, 0.00, 0),
(8, 'Dekanati për Gjuhë të Huaja ', 'Njësi akademike në fushën e gjuhëve të huaja dhe përkthimit.', 0, '0', 0.00, 0.00, 0),
(9, 'Dekanati i Komunikimit Masiv ', 'Mbulon studimet në media, gazetari dhe komunikim.', 0, '0', 0.00, 0.00, 0),
(10, 'Dekanati i Shkencave Kompjuterike ', 'Merret me programim, IT dhe teknologji.', 0, '0', 0.00, 0.00, 0),
(11, 'Dekanati i Shkencave Sociale ', 'Përfshin studime në sociologji, psikologji dhe fusha të ngjashme.', 0, '0', 0.00, 0.00, 0),
(12, 'Digital Support ', 'ështetje digjitale për sistemet dhe shërbimet online.', 0, '0', 0.00, 0.00, 0),
(13, 'Drejtoria e Administratës', 'Menaxhon çështjet administrative të institucionit.', 0, '0', 0.00, 0.00, 0),
(14, 'Drejtoria e IT ', 'Kujdeset për infrastrukturën dhe sistemet e teknologjisë informative.', 0, '0', 0.00, 0.00, 0),
(15, 'Drejtoria e Logjistikës ', 'Organizon burimet, pajisjet dhe furnizimet.', 0, '0', 0.00, 0.00, 0),
(16, 'Help Desk I ', 'Ndihmë teknike bazike për përdoruesit.', 0, '0', 0.00, 0.00, 1),
(17, 'Help Desk II ', 'Ndihmë teknike e avancuar për probleme komplekse.', 0, '0', 0.00, 0.00, 1),
(18, 'Informata', 'Zyrë për informim dhe udhëzime për studentë dhe staf.', 0, '0', 0.00, 0.00, 0),
(19, 'Instituti', 'Njësi kërkimore dhe zhvillimore akademike.', 0, '0', 0.00, 0.00, 0),
(20, 'IT – Help Obj-6', 'Pikë ndihme IT për Objektin 6.', 0, '0', 0.00, 0.00, 0),
(21, 'PriBank', 'Shërbime bankare dhe financiare për studentë dhe staf', 0, '0', 0.00, 0.00, 0),
(22, 'QAPI', 'Pikë shërbimi për aplikime dhe procedura administrative.', 0, '0', 0.00, 0.00, 0),
(23, 'SDO', 'Zyrë për shërbime dhe dokumentacione studentore.', 0, '0', 0.00, 0.00, 0),
(24, 'Unioni i Studentëve ', 'Organizatë që përfaqëson studentët.', 0, '0', 0.00, 0.00, 0),
(25, 'Zyra e Diplomave ', 'Menaxhon lëshimin dhe verifikimin e diplomave.', 0, '0', 0.00, 0.00, 0),
(26, 'Zyra e Financave', 'Kujdeset për pagesa dhe çështje financiare.', 0, '0', 0.00, 0.00, 0),
(27, 'Zyra e Furnizimit ', 'Menaxhon pajisje dhe furnizime.', 0, '0', 0.00, 0.00, 0),
(28, 'Zyra e IT', 'Ofron mbështetje teknike dhe IT.', 0, '0', 0.00, 0.00, 0),
(29, 'Zyra e Rikthimeve ', 'Menaxhon rikthimin e studentëve në studime.', 0, '0', 0.00, 0.00, 0),
(30, 'Zyra e Transfereve ', 'Merret me transferimet e studentëve.', 0, '0', 0.00, 0.00, 0),
(31, 'Zyra Franceze', 'Zyrë për bashkëpunime dhe programe me Francën.', 0, '0', 0.00, 0.00, 0),
(32, 'Zyra për Karrierë ', 'Ndihmon studentët me punësim dhe zhvillim karriere.', 0, '0', 0.00, 0.00, 0),
(33, 'Zyra për Siguri Kibernetike ', 'Kujdeset për sigurinë digjitale dhe mbrojtjen e sistemeve', 0, '0', 0.00, 0.00, 0),
(34, 'Tualetet', 'Në çdo kat ka nga 3 tualete për studentë dhe staf.', 0, '0', 0.00, 0.00, 0),
(35, 'Ashensori ', 'Në ndërtesë ka ashensor për lëvizje të lehtë ndërmjet kateve.', 0, '0', 0.00, 0.00, 0),
(36, 'A-01', 'Salla për ligjërata dhe ushtrime.', 0, '0', 0.00, 0.00, 0),
(37, 'A-02', 'Salla për ligjërata dhe ushtrime.', 0, '0', 0.00, 0.00, 0),
(38, 'A-03', 'Salla për ligjërata dhe ushtrime.', 0, '0', 0.00, 0.00, 0),
(39, 'A-04', 'Salla për ligjërata dhe ushtrime.', 0, '0', 0.00, 0.00, 0),
(40, 'A-05', 'Salla për ligjërata dhe ushtrime.', 0, '0', 0.00, 0.00, 0),
(41, 'A-06', 'Salla për ligjërata dhe ushtrime.', 0, '0', 0.00, 0.00, 0),
(42, 'Dekanati Infermieri', 'Zyra për informata dhe çështje akademike të studentëve të infermierisë.', 1, '1', 1.00, 1.00, 1),
(43, 'Lab VII Radiologji', 'Laborator për ushtrime praktike në radiologji.', 1, '1', 1.00, 1.00, 1),
(44, 'Librar', 'Hapësirë për lexim, studim dhe huazim librash.', 1, '1', 1.00, 1.00, 1),
(45, 'Lingua', 'Qendër për mësimin e gjuhëve të huaja.', 1, '1', 1.00, 1.00, 1),
(46, 'Salla e Testimit ', 'Sallë për teste dhe provime.', 1, '101', 1.00, 1.00, 1),
(47, 'Salla e Testimit ', 'Sallë për teste dhe provime.', 1, '102', 1.00, 1.00, 1),
(48, 'Studio e Fotografisë', 'Hapësirë për fotografi dhe projekte multimediale.', 1, '1', 1.00, 1.00, 1),
(49, 'Teatri Faruk Begolli', 'Sallë për shfaqje, evente dhe aktivitete kulturore.', 1, '1', 1.00, 1.00, 1),
(50, 'Zyra e Asistentëve – Infermieri', 'Zyrë për konsultime me asistentët.', 1, '1', 1.00, 1.00, 1),
(51, 'Tualetet', 'Në çdo kat ka nga 3 tualete për studentë dhe staf.', 1, '1', 1.00, 1.00, 1),
(52, 'Ashensori', 'Në ndërtesë ka ashensor për lëvizje të lehtë ndërmjet kateve.', 1, '1', 1.00, 1.00, 1),
(53, 'Byfeja', 'Hapësirë ku studentët dhe stafi mund të blejnë ushqim dhe pije të lehta, si dhe të pushojnë gjatë pauzave. ', 1, '1', 1.00, 1.00, 1),
(54, 'A-101', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(55, 'A-104', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(56, 'A-105', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(57, 'A-106', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(58, 'A-107', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(59, 'A-108', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(60, 'A-109', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(61, 'A-110', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(62, 'A-111', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(63, 'A-112', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(64, 'A-113', 'Klasa për ligjërata dhe ushtrime.', 1, '1', 1.00, 1.00, 1),
(65, 'A-106 – Montazhë', 'Hapësirë për montazhë video dhe punë multimediale.', 1, '1', 1.00, 1.00, 1),
(66, 'A-LAB I – Informatikë', 'Laborator për ushtrime kompjuterike.', 1, '1', 1.00, 1.00, 1),
(67, 'A-LAB II – Informatikë', 'Laborator për programim dhe teknologji.', 1, '1', 1.00, 1.00, 1),
(68, 'A-LAB III – Informatikë', 'Laborator për ushtrime praktike në IT.', 1, '1', 1.00, 1.00, 1),
(69, 'A-202', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(70, 'A-203', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(71, 'A-204', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(72, 'A-205', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(73, 'A-206', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(74, 'A-207', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(75, 'A-208', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(76, 'A-209', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(77, 'A-210', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(78, 'A-211', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(79, 'A-212', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(80, 'A-213', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(81, 'A-214', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(82, 'A-215', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(83, 'A-216', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(84, 'A-217', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(85, 'A-218', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(86, 'A-219', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(87, 'A-220', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(88, 'A-221', '– Salla për ligjërata, ushtrime dhe seminare.', 2, '2', 2.00, 2.00, 2),
(89, 'A-LAB IV', 'Laboratorë për ushtrime praktike në informatikë.', 2, '2', 2.00, 2.00, 2),
(90, 'A-LAB V', 'Laboratorë për ushtrime praktike në informatikë.', 2, '2', 2.00, 2.00, 2),
(91, 'A-LAB VI', 'Laboratorë për ushtrime praktike në informatikë.', 2, '2', 2.00, 2.00, 2),
(92, 'A-LAB VII', 'Laboratorë për ushtrime praktike në informatikë.', 2, '2', 2.00, 2.00, 2),
(93, 'LAB Anatomi', 'Laborator për studimin e anatomisë njerëzore.', 2, '2', 2.00, 2.00, 2),
(94, 'LAB Fiziologji', 'Laborator për ushtrime në fiziologji.', 2, '2', 2.00, 2.00, 2),
(95, 'LAB Kriminalistikë', 'Laborator për analiza dhe praktika kriminalistike.', 2, '2', 2.00, 2.00, 2),
(96, 'LAB Paraklinik I', 'Laboratorë për ushtrime paraklinike mjekësore.', 2, '2', 2.00, 2.00, 2),
(97, 'LAB Paraklinik II', 'Laboratorë për ushtrime paraklinike mjekësore.', 2, '2', 2.00, 2.00, 2),
(98, 'LAB I–VII Infermieri', 'Laboratorë për praktikë dhe simulime në infermieri.', 2, '2', 2.00, 2.00, 2),
(99, 'Salla e Fakultetit Juridik', 'Sallë për aktivitete dhe ligjërata juridike.', 2, '2', 2.00, 2.00, 2),
(100, 'Salla e Gjykimit', 'Sallë simulimi për procese gjyqësore praktike.', 2, '2', 2.00, 2.00, 2),
(101, 'Tualetet', 'Në çdo kat ka nga 3 tualete për studentë dhe staf.', 2, '2', 2.00, 2.00, 2),
(102, 'Ashensori', 'Në ndërtesë ka ashensor për lëvizje të lehtë ndërmjet kateve.', 2, '2', 2.00, 2.00, 2),
(103, 'A-302 deri A-319', 'Klasa për ligjërata dhe ushtrime akademike.', 3, '3', 3.00, 3.00, 3),
(104, 'EDU-LAB', 'Hapësirë për projekte edukative dhe teknologjike.', 3, '3', 3.00, 3.00, 3),
(105, 'HR', 'Zyra e burimeve njerëzore.', 3, '3', 3.00, 3.00, 3),
(106, 'Koordinatorët e Cilësisë', 'Zyra për kontroll dhe sigurim të cilësisë akademike.', 3, '3', 3.00, 3.00, 3),
(107, 'Laboratori Digjital', 'Hapësirë për teknologji dhe media digjitale.', 3, '3', 3.00, 3.00, 3),
(108, 'PR', 'Zyra për marrëdhënie me publikun.', 3, '3', 3.00, 3.00, 3),
(109, 'Prorektore për Bashkëpunime të Jashtme', 'Zyra për partneritete dhe bashkëpunime.', 3, '3', 3.00, 3.00, 3),
(110, 'Prorektore për Cilësi dhe Çështje Akademike', 'Zyra për menaxhim akademik dhe cilësi.', 3, '3', 3.00, 3.00, 3),
(111, 'Prorektori për Kërkime Shkencore', 'Zyra për aktivitete kërkimore dhe shkencore.', 3, '3', 3.00, 3.00, 3),
(112, 'Qendra Mediale', 'Hapësirë për media dhe komunikim.', 3, '3', 3.00, 3.00, 3),
(113, 'Rektorati', 'Administrata kryesore e kolegjit.', 3, '3', 3.00, 3.00, 3),
(114, 'Salla e Konferencave', 'Sallë për konferenca dhe seminare.', 3, '3', 3.00, 3.00, 3),
(115, 'Salla e Profesorëve', 'Hapësirë për profesorët dhe stafin akademik.', 3, '3', 3.00, 3.00, 3),
(116, 'Zyra e Marketingut', 'Zyra për promovim dhe marketing.', 3, '3', 3.00, 3.00, 3),
(117, 'Zyra e Projekteve', 'Zyra për menaxhimin e projekteve.', 3, '3', 3.00, 3.00, 3),
(118, 'Tualetet', 'Në çdo kat ka nga 3 tualete për studentë dhe staf.', 3, '3', 3.00, 3.00, 3),
(119, 'Ashensori', 'Në ndërtesë ka ashensor për lëvizje të lehtë ndërmjet kateve.', 3, '3', 3.00, 3.00, 3),
(120, 'ATV', 'Hapësirë mediale dhe televizive për produksion dhe transmetim.', 4, '4', 4.00, 4.00, 4),
(121, 'Tualetet', 'Në çdo kat ka nga 3 tualete për studentë dhe staf.', 4, '4', 4.00, 4.00, 4),
(122, 'Ashensori', 'Në ndërtesë ka ashensor për lëvizje të lehtë ndërmjet kateve.', 4, '4', 4.00, 4.00, 4),
(123, 'Teatri Kamertal', 'Sallë për shfaqje, evente, prezantime dhe aktivitete kulturore.', 4, '4.4', 4.00, 4.00, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `matched_location_id` (`matched_location_id`),
  ADD KEY `matched_faq_id` (`matched_faq_id`);

--
-- Indexes for table `default_responses`
--
ALTER TABLE `default_responses`
  ADD PRIMARY KEY (`default_response_id`);

--
-- Indexes for table `directions`
--
ALTER TABLE `directions`
  ADD PRIMARY KEY (`direction_id`),
  ADD KEY `from_location_id` (`from_location_id`),
  ADD KEY `to_location_id` (`to_location_id`);

--
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`faq_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `faq_categories`
--
ALTER TABLE `faq_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `faq_keywords`
--
ALTER TABLE `faq_keywords`
  ADD PRIMARY KEY (`faq_keyword_id`),
  ADD KEY `faq_id` (`faq_id`),
  ADD KEY `idx_faq_keyword` (`keyword`);

--
-- Indexes for table `keywords`
--
ALTER TABLE `keywords`
  ADD PRIMARY KEY (`keyword_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `idx_keyword` (`keyword`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `idx_location_name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `default_responses`
--
ALTER TABLE `default_responses`
  MODIFY `default_response_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `directions`
--
ALTER TABLE `directions`
  MODIFY `direction_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `faq_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faq_categories`
--
ALTER TABLE `faq_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faq_keywords`
--
ALTER TABLE `faq_keywords`
  MODIFY `faq_keyword_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `keywords`
--
ALTER TABLE `keywords`
  MODIFY `keyword_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`matched_location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`matched_faq_id`) REFERENCES `faq` (`faq_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `directions`
--
ALTER TABLE `directions`
  ADD CONSTRAINT `directions_ibfk_1` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `directions_ibfk_2` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `faq`
--
ALTER TABLE `faq`
  ADD CONSTRAINT `faq_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `faq_categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `faq_keywords`
--
ALTER TABLE `faq_keywords`
  ADD CONSTRAINT `faq_keywords_ibfk_1` FOREIGN KEY (`faq_id`) REFERENCES `faq` (`faq_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `keywords`
--
ALTER TABLE `keywords`
  ADD CONSTRAINT `keywords_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
