-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 11, 2025 at 12:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kviz2`
--

-- --------------------------------------------------------

--
-- Table structure for table `ep_korisnik`
--

CREATE TABLE `ep_korisnik` (
  `ID` int(11) NOT NULL,
  `ime` varchar(100) NOT NULL,
  `lozinka` varchar(32) NOT NULL,
  `razinaID` int(11) NOT NULL,
  `razred_id` int(11) DEFAULT NULL,
  `aktivan` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_korisnik`
--

INSERT INTO `ep_korisnik` (`ID`, `ime`, `lozinka`, `razinaID`, `razred_id`, `aktivan`, `email`) VALUES
(1, 'Profesor', '70cf5c0095d91b8f2b9798700651df25', 1, NULL, 1, 'profesor@example.com'),
(13, 'dadoslav', '202cb962ac59075b964b07152d234b70', 2, 11, 1, 'Profesor@gmail.com'),
(14, 'dadosloav', '202cb962ac59075b964b07152d234b70', 2, 12, 1, 'sa@gmail.com'),
(15, 'Martin', '8aa87050051efe26091a13dbfdf901c6', 2, 7, 1, 'martin@gmail.com'),
(16, 'john', '8aa87050051efe26091a13dbfdf901c6', 2, 6, 1, 'john@gmail.com'),
(17, 'lanac', '747ae77ebb0ea8c503d43002581f94d0', 2, 9, 1, 'lanac.morski@gmail.com'),
(18, 'kreso', '8aa87050051efe26091a13dbfdf901c6', 2, 10, 1, 'halo@ggdgd'),
(19, 'nikola', '8aa87050051efe26091a13dbfdf901c6', 2, 3, 1, 'playertracker2025@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `ep_korisnik_teme`
--

CREATE TABLE `ep_korisnik_teme` (
  `id` int(11) NOT NULL,
  `korisnik_id` int(11) NOT NULL,
  `tema_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_korisnik_teme`
--

INSERT INTO `ep_korisnik_teme` (`id`, `korisnik_id`, `tema_id`) VALUES
(4, 13, 9),
(3, 13, 11),
(5, 14, 5),
(7, 14, 10),
(6, 14, 12),
(12, 15, 1),
(8, 15, 2),
(14, 15, 3),
(9, 15, 4),
(15, 15, 5),
(11, 15, 6),
(10, 15, 7),
(13, 15, 8),
(16, 15, 12),
(21, 16, 1),
(17, 16, 2),
(23, 16, 3),
(18, 16, 4),
(24, 16, 5),
(20, 16, 6),
(19, 16, 7),
(22, 16, 8),
(28, 16, 9),
(26, 16, 10),
(27, 16, 11),
(25, 16, 12),
(33, 17, 1),
(29, 17, 2),
(35, 17, 3),
(30, 17, 4),
(36, 17, 5),
(32, 17, 6),
(31, 17, 7),
(34, 17, 8),
(40, 17, 9),
(38, 17, 10),
(39, 17, 11),
(37, 17, 12),
(45, 18, 1),
(41, 18, 2),
(47, 18, 3),
(42, 18, 4),
(44, 18, 6),
(43, 18, 7),
(46, 18, 8),
(51, 18, 9),
(49, 18, 10),
(50, 18, 11),
(48, 18, 12),
(56, 19, 1),
(52, 19, 2),
(58, 19, 3),
(53, 19, 4),
(59, 19, 5),
(55, 19, 6),
(54, 19, 7),
(57, 19, 8),
(63, 19, 9),
(61, 19, 10),
(62, 19, 11),
(60, 19, 12);

-- --------------------------------------------------------

--
-- Table structure for table `ep_pitanja_na_testu`
--

CREATE TABLE `ep_pitanja_na_testu` (
  `ID` int(11) NOT NULL,
  `testID` int(11) NOT NULL,
  `pitanjeID` int(11) NOT NULL,
  `odgovorID` int(11) NOT NULL,
  `odabrano` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ep_pitanje`
--

CREATE TABLE `ep_pitanje` (
  `ID` int(11) NOT NULL,
  `tekst_pitanja` text NOT NULL,
  `korisnikID` int(11) NOT NULL,
  `brojBodova` int(11) NOT NULL DEFAULT 0,
  `hint` text DEFAULT NULL,
  `broj_ponudenih` int(11) NOT NULL DEFAULT 0,
  `aktivno` tinyint(1) NOT NULL DEFAULT 1,
  `temaID` int(11) NOT NULL,
  `slika` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_pitanje`
--

INSERT INTO `ep_pitanje` (`ID`, `tekst_pitanja`, `korisnikID`, `brojBodova`, `hint`, `broj_ponudenih`, `aktivno`, `temaID`, `slika`) VALUES
(21, 'Koji je glavni grad Francuske?', 1, 5, 'Grad ljubavi', 4, 1, 1, NULL),
(24, 'Koliko kontinenata postoji na Zemlji?', 1, 4, 'Broj je između 5 i 7', 4, 1, 1, NULL),
(27, 'Koja rijeka teče kroz Zagreb?', 1, 3, 'Počinje slovom S', 4, 1, 1, NULL),
(30, 'Tko je napisao \"Hamlet\"?', 1, 5, 'Poznati engleski pisac', 4, 1, 5, NULL),
(31, 'Koji je najveći ocean na svijetu?', 1, 5, 'Nalazi se između Amerike i Azije', 4, 1, 1, NULL),
(34, 'Tko je otkrio gravitaciju?', 1, 5, 'Jabuka mu je pala na glavu', 4, 1, 4, NULL),
(37, 'Koji je glavni sastojak piva?', 1, 5, 'Napravljen je od žitarica', 4, 1, 6, NULL),
(40, 'Koji metal je najlakši?', 1, 3, 'Koristi se u baterijama', 4, 1, 3, NULL),
(46, 'Kad bude Martin ćelavi?', 1, 1, NULL, 0, 1, 4, NULL),
(47, 'Koliko je visoka gora', 1, 1, NULL, 0, 1, 1, NULL),
(48, 'Kaj znači Sis', 1, 1, NULL, 0, 1, 10, NULL),
(49, 'Koje je boja kapa', 1, 1, NULL, 0, 1, 11, NULL),
(50, 'dsada', 1, 1, NULL, 0, 1, 12, 'uploads/1741103966_474501156_1286432389237726_8017745531472963064_n.jpg'),
(51, 'dsadas', 1, 1, NULL, 0, 1, 12, 'uploads/1741104618_playertracker_logo.png'),
(52, 'djksbdja', 1, 1, 'jksdbsadjkabd', 0, 1, 12, 'uploads/1741117652_477000787_596460106540980_574527163491175664_n.jpg'),
(53, 'jdadja', 1, 1, 'dnljasdla', 0, 1, 12, 'uploads/1741118067_playertracker_logo.png'),
(54, 'jwdhajd', 1, 1, 'hufash', 0, 1, 12, NULL),
(55, 'ejvja', 1, 1, 'LLHAS', 0, 1, 12, 'uploads/1741245049_474501156_1286432389237726_8017745531472963064_n.jpg'),
(56, 'dbjajks', 1, 1, '', 2, 1, 12, NULL),
(57, 'dbjajks', 1, 1, '', 2, 1, 12, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ep_prava`
--

CREATE TABLE `ep_prava` (
  `ID` int(11) NOT NULL,
  `korisnikID` int(11) NOT NULL,
  `pravoID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_prava`
--

INSERT INTO `ep_prava` (`ID`, `korisnikID`, `pravoID`) VALUES
(1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `ep_pravo`
--

CREATE TABLE `ep_pravo` (
  `ID` int(11) NOT NULL,
  `opis` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_pravo`
--

INSERT INTO `ep_pravo` (`ID`, `opis`) VALUES
(1, 'Admin\r\n'),
(2, 'Neadmin\r\n');

-- --------------------------------------------------------

--
-- Table structure for table `ep_razred`
--

CREATE TABLE `ep_razred` (
  `id` int(11) NOT NULL,
  `tip` varchar(20) NOT NULL,
  `razred` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `ep_razred`
--

INSERT INTO `ep_razred` (`id`, `tip`, `razred`) VALUES
(1, 'osnovna', 1),
(2, 'osnovna', 2),
(3, 'osnovna', 3),
(4, 'osnovna', 4),
(5, 'osnovna', 5),
(6, 'osnovna', 6),
(7, 'osnovna', 7),
(8, 'osnovna', 8),
(9, 'srednja', 1),
(10, 'srednja', 2),
(11, 'srednja', 3),
(12, 'srednja', 4);

-- --------------------------------------------------------

--
-- Table structure for table `ep_razredi`
--

CREATE TABLE `ep_razredi` (
  `id` int(11) NOT NULL,
  `razred_id` int(11) NOT NULL,
  `tema_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_razredi`
--

INSERT INTO `ep_razredi` (`id`, `razred_id`, `tema_id`) VALUES
(1, 1, 2),
(2, 1, 9),
(3, 2, 1),
(4, 2, 5),
(5, 3, 4),
(6, 3, 7),
(7, 4, 8),
(8, 4, 9),
(9, 5, 2),
(10, 5, 5),
(11, 6, 3),
(12, 6, 7),
(13, 7, 1),
(14, 7, 4),
(15, 8, 11),
(16, 9, 2),
(17, 9, 8),
(18, 9, 5),
(19, 10, 1),
(20, 10, 3),
(21, 10, 9),
(22, 11, 4),
(23, 11, 7),
(24, 11, 11),
(25, 12, 10),
(26, 12, 12);

-- --------------------------------------------------------

--
-- Table structure for table `ep_teme`
--

CREATE TABLE `ep_teme` (
  `ID` int(11) NOT NULL,
  `naziv` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_teme`
--

INSERT INTO `ep_teme` (`ID`, `naziv`) VALUES
(2, 'Astronomija'),
(4, 'Biologija'),
(7, 'Ekonomija'),
(6, 'Fizika'),
(1, 'Geografija'),
(8, 'Hrana'),
(3, 'Kemija'),
(5, 'Književnost'),
(12, 'ostalo'),
(10, 'Sigurnost informacijskih sustava'),
(11, 'Slikice'),
(9, 'Sport');

-- --------------------------------------------------------

--
-- Table structure for table `ep_test`
--

CREATE TABLE `ep_test` (
  `ID` int(11) NOT NULL,
  `korisnikID` int(11) NOT NULL,
  `kviz_id` int(11) NOT NULL,
  `rezultat` int(11) DEFAULT NULL,
  `ukupno_pitanja` int(11) DEFAULT NULL,
  `tocno_odgovori` int(11) DEFAULT NULL,
  `netocno_odgovori` int(11) DEFAULT NULL,
  `trajanje` time DEFAULT NULL,
  `broj_pokusaja` int(11) DEFAULT NULL,
  `kreirano` datetime DEFAULT current_timestamp(),
  `azurirano` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vrijeme_pocetka` datetime NOT NULL,
  `vremensko_ogranicenje` int(11) NOT NULL,
  `vrijeme_kraja` datetime GENERATED ALWAYS AS (`vrijeme_pocetka` + interval `vremensko_ogranicenje` minute) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_test`
--

INSERT INTO `ep_test` (`ID`, `korisnikID`, `kviz_id`, `rezultat`, `ukupno_pitanja`, `tocno_odgovori`, `netocno_odgovori`, `trajanje`, `broj_pokusaja`, `kreirano`, `azurirano`, `vrijeme_pocetka`, `vremensko_ogranicenje`) VALUES
(33, 18, 6, 1, 1, 1, 0, '00:00:01', 1, '2025-03-11 12:21:49', '2025-03-11 12:21:49', '2025-03-11 12:21:48', 0),
(34, 18, 4, 1, 2, 1, 1, '00:02:50', 1, '2025-03-11 12:44:04', '2025-03-11 12:44:04', '2025-03-11 12:41:14', 0),
(35, 18, 3, 0, 1, 0, 1, '00:00:12', 1, '2025-03-11 12:44:23', '2025-03-11 12:44:23', '2025-03-11 12:44:11', 0),
(36, 18, 3, 0, 1, 0, 1, '00:00:00', 1, '2025-03-11 12:44:24', '2025-03-11 12:44:24', '2025-03-11 12:44:24', 0),
(37, 18, 3, 0, 1, 0, 1, '00:00:00', 1, '2025-03-11 12:44:26', '2025-03-11 12:44:26', '2025-03-11 12:44:26', 0),
(38, 18, 3, 0, 1, 0, 1, '00:00:00', 1, '2025-03-11 12:44:28', '2025-03-11 12:44:28', '2025-03-11 12:44:28', 0),
(39, 18, 3, 1, 1, 1, 0, '00:00:16', 1, '2025-03-11 12:44:46', '2025-03-11 12:44:46', '2025-03-11 12:44:30', 0),
(40, 18, 3, 1, 1, 1, 0, '00:00:00', 1, '2025-03-11 12:45:07', '2025-03-11 12:45:07', '2025-03-11 12:45:07', 0),
(41, 19, 3, 1, 1, 1, 0, '00:01:17', 1, '2025-03-11 12:46:45', '2025-03-11 12:46:45', '2025-03-11 12:45:28', 0),
(42, 19, 12, 3, 8, 3, 5, '00:00:07', 1, '2025-03-11 12:48:45', '2025-03-11 12:48:45', '2025-03-11 12:48:38', 0),
(43, 19, 12, 2, 8, 2, 6, '00:00:08', 1, '2025-03-11 12:50:59', '2025-03-11 12:50:59', '2025-03-11 12:50:51', 0);

-- --------------------------------------------------------

--
-- Table structure for table `ep_test_odgovori`
--

CREATE TABLE `ep_test_odgovori` (
  `ID` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `question_text` text DEFAULT NULL,
  `user_answer_text` text DEFAULT NULL,
  `correct_answer_text` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ep_test_odgovori`
--

INSERT INTO `ep_test_odgovori` (`ID`, `test_id`, `question_text`, `user_answer_text`, `correct_answer_text`, `explanation`, `is_correct`) VALUES
(11, 33, 'Koji je glavni sastojak piva?', 'Ječam', 'Ječam', 'Napravljen je od žitarica', 1),
(12, 34, 'Tko je otkrio gravitaciju?', 'Isaac Newton', 'Isaac Newton', 'Jabuka mu je pala na glavu', 1),
(13, 34, 'Kad bude Martin ćelavi?', 'Za 3 godine', 'Sutra', 'Nema dodatnog objašnjenja.', 0),
(14, 35, 'Koji metal je najlakši?', 'Aluminij', 'Litij', 'Koristi se u baterijama', 0),
(15, 36, 'Koji metal je najlakši?', 'Aluminij', 'Litij', 'Koristi se u baterijama', 0),
(16, 37, 'Koji metal je najlakši?', 'Aluminij', 'Litij', 'Koristi se u baterijama', 0),
(17, 38, 'Koji metal je najlakši?', 'Aluminij', 'Litij', 'Koristi se u baterijama', 0),
(18, 39, 'Koji metal je najlakši?', 'Litij', 'Litij', 'Koristi se u baterijama', 1),
(19, 40, 'Koji metal je najlakši?', 'Litij', 'Litij', 'Koristi se u baterijama', 1),
(20, 41, 'Koji metal je najlakši?', 'Litij', 'Litij', 'Koristi se u baterijama', 1),
(21, 42, 'djksbdja', 'dasjdbakj', 'dasjdbakj', 'jksdbsadjkabd', 1),
(22, 42, 'dbjajks', 'dbashd', 'dbashd', 'Nema dodatnog objašnjenja.', 1),
(23, 42, 'dbjajks', 'dbashd', 'dbashd', 'Nema dodatnog objašnjenja.', 1),
(24, 42, 'dsada', 'dasda', 'dada', 'Nema dodatnog objašnjenja.', 0),
(25, 42, 'dsadas', 'dasdas', 'dsad', 'Nema dodatnog objašnjenja.', 0),
(26, 42, 'jdadja', 'assdbkasb', 'ashdbaks', 'dnljasdla', 0),
(27, 42, 'jwdhajd', 'fuoshf', 'fusaoh', 'hufash', 0),
(28, 42, 'ejvja', 'vabjks', 'vakjsfb', 'LLHAS', 0),
(29, 43, 'dsadas', 'dsad', 'dsad', 'Nema dodatnog objašnjenja.', 1),
(30, 43, 'dbjajks', 'dbashd', 'dbashd', 'Nema dodatnog objašnjenja.', 1),
(31, 43, 'dsada', 'dada', 'dada', 'Nema dodatnog objašnjenja.', 0),
(32, 43, 'djksbdja', 'dasjkdbas', 'dasjdbakj', 'jksdbsadjkabd', 0),
(33, 43, 'jdadja', 'assdbkasb', 'ashdbaks', 'dnljasdla', 0),
(34, 43, 'jwdhajd', 'fhuas', 'fusaoh', 'hufash', 0),
(35, 43, 'ejvja', 'lc', 'vakjsfb', 'LLHAS', 0),
(36, 43, 'dbjajks', 'dash', 'dbashd', 'Nema dodatnog objašnjenja.', 0);

-- --------------------------------------------------------

--
-- Table structure for table `op_odgovori`
--

CREATE TABLE `op_odgovori` (
  `ID` int(11) NOT NULL,
  `tekst` text NOT NULL,
  `pitanjeID` int(11) NOT NULL,
  `tocno` tinyint(1) NOT NULL DEFAULT 0,
  `korisnikID` int(11) NOT NULL,
  `aktivno` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `op_odgovori`
--

INSERT INTO `op_odgovori` (`ID`, `tekst`, `pitanjeID`, `tocno`, `korisnikID`, `aktivno`) VALUES
(1, 'Pariz', 21, 1, 1, 1),
(2, 'Lyon', 21, 0, 1, 1),
(3, 'Marseille', 21, 0, 1, 1),
(4, 'Nice', 21, 0, 1, 1),
(13, '7', 24, 1, 1, 1),
(14, '5', 24, 0, 1, 1),
(15, '6', 24, 0, 1, 1),
(16, '8', 24, 0, 1, 1),
(25, 'Sava', 27, 1, 1, 1),
(26, 'Dunav', 27, 0, 1, 1),
(27, 'Drava', 27, 0, 1, 1),
(28, 'Kupa', 27, 0, 1, 1),
(37, 'William Shakespeare', 30, 1, 1, 1),
(38, 'Charles Dickens', 30, 0, 1, 1),
(39, 'J.R.R. Tolkien', 30, 0, 1, 1),
(40, 'George Orwell', 30, 0, 1, 1),
(41, 'Pacifik', 31, 1, 1, 1),
(42, 'Atlantski', 31, 0, 1, 1),
(43, 'Indijski', 31, 0, 1, 1),
(44, 'Arktički', 31, 0, 1, 1),
(53, 'Isaac Newton', 34, 1, 1, 1),
(54, 'Albert Einstein', 34, 0, 1, 1),
(55, 'Nikola Tesla', 34, 0, 1, 1),
(56, 'Galileo Galilei', 34, 0, 1, 1),
(65, 'Ječam', 37, 1, 1, 1),
(66, 'Hmelj', 37, 0, 1, 1),
(67, 'Pšenica', 37, 0, 1, 1),
(68, 'Riža', 37, 0, 1, 1),
(77, 'Litij', 40, 1, 1, 1),
(78, 'Aluminij', 40, 0, 1, 1),
(79, 'Magnezij', 40, 0, 1, 1),
(80, 'Bakar', 40, 0, 1, 1),
(181, 'Za 1 godinu', 46, 0, 1, 1),
(182, 'Za 2 godine', 46, 0, 1, 1),
(183, 'Za 3 godine', 46, 0, 1, 1),
(184, 'Sutra', 46, 1, 1, 1),
(185, '100m', 47, 0, 1, 1),
(186, '12m', 47, 0, 1, 1),
(187, '33m', 47, 1, 1, 1),
(188, '22m', 47, 0, 1, 1),
(189, 'Sam i samcat', 48, 0, 1, 1),
(190, 'Sigurnost informacijskih sustava', 48, 1, 1, 1),
(191, 'Strelil bum se ak bum ovo delal jos par sekundi', 48, 0, 1, 1),
(192, 'Glupi je zadatak', 48, 0, 1, 1),
(193, 'Plava', 49, 0, 1, 1),
(194, 'Bijela', 49, 1, 1, 1),
(195, 'Crvena', 49, 0, 1, 1),
(196, 'Glupi si', 49, 0, 1, 1),
(197, 'dasda', 50, 0, 1, 1),
(198, 'dada', 50, 0, 1, 1),
(199, 'dada', 50, 0, 1, 1),
(200, 'dada', 50, 1, 1, 1),
(201, 'dasdas', 51, 0, 1, 1),
(202, 'dasdas', 51, 0, 1, 1),
(203, 'dasda', 51, 0, 1, 1),
(204, 'dsad', 51, 1, 1, 1),
(205, 'sjda', 52, 0, 1, 1),
(206, 'sadjkabda', 52, 0, 1, 1),
(207, 'dasjdbakj', 52, 1, 1, 1),
(208, 'dasjkdbas', 52, 0, 1, 1),
(209, 'askdbasd', 53, 0, 1, 1),
(210, 'askdbaskj', 53, 0, 1, 1),
(211, 'ashdbaks', 53, 1, 1, 1),
(212, 'assdbkasb', 53, 0, 1, 1),
(213, 'ufshf', 54, 0, 1, 1),
(214, 'fuoshf', 54, 0, 1, 1),
(215, 'fusaoh', 54, 1, 1, 1),
(216, 'fhuas', 54, 0, 1, 1),
(217, 'vabjks', 55, 0, 1, 1),
(218, 'bajks', 55, 0, 1, 1),
(219, 'vakjsfb', 55, 1, 1, 1),
(220, 'lc', 55, 0, 1, 1),
(221, 'dbashd', 56, 1, 1, 1),
(222, 'dash', 56, 0, 1, 1),
(223, 'dbashd', 57, 1, 1, 1),
(224, 'dash', 57, 0, 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ep_korisnik`
--
ALTER TABLE `ep_korisnik`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `razinaID` (`razinaID`),
  ADD KEY `razred_id` (`razred_id`);

--
-- Indexes for table `ep_korisnik_teme`
--
ALTER TABLE `ep_korisnik_teme`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `korisnik_id` (`korisnik_id`,`tema_id`),
  ADD KEY `tema_id` (`tema_id`);

--
-- Indexes for table `ep_pitanja_na_testu`
--
ALTER TABLE `ep_pitanja_na_testu`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `testID` (`testID`),
  ADD KEY `pitanjeID` (`pitanjeID`),
  ADD KEY `odgovorID` (`odgovorID`);

--
-- Indexes for table `ep_pitanje`
--
ALTER TABLE `ep_pitanje`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `korisnikID` (`korisnikID`),
  ADD KEY `fk_ep_teme` (`temaID`);

--
-- Indexes for table `ep_prava`
--
ALTER TABLE `ep_prava`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `korisnikID` (`korisnikID`);

--
-- Indexes for table `ep_pravo`
--
ALTER TABLE `ep_pravo`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `ep_razred`
--
ALTER TABLE `ep_razred`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ep_razredi`
--
ALTER TABLE `ep_razredi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `razred_id` (`razred_id`),
  ADD KEY `tema_id` (`tema_id`);

--
-- Indexes for table `ep_teme`
--
ALTER TABLE `ep_teme`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `naziv` (`naziv`);

--
-- Indexes for table `ep_test`
--
ALTER TABLE `ep_test`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `korisnikID` (`korisnikID`);

--
-- Indexes for table `ep_test_odgovori`
--
ALTER TABLE `ep_test_odgovori`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `op_odgovori`
--
ALTER TABLE `op_odgovori`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `pitanjeID` (`pitanjeID`),
  ADD KEY `korisnikID` (`korisnikID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ep_korisnik`
--
ALTER TABLE `ep_korisnik`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `ep_korisnik_teme`
--
ALTER TABLE `ep_korisnik_teme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `ep_pitanja_na_testu`
--
ALTER TABLE `ep_pitanja_na_testu`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `ep_pitanje`
--
ALTER TABLE `ep_pitanje`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `ep_prava`
--
ALTER TABLE `ep_prava`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ep_pravo`
--
ALTER TABLE `ep_pravo`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ep_razred`
--
ALTER TABLE `ep_razred`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `ep_razredi`
--
ALTER TABLE `ep_razredi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `ep_teme`
--
ALTER TABLE `ep_teme`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `ep_test`
--
ALTER TABLE `ep_test`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `ep_test_odgovori`
--
ALTER TABLE `ep_test_odgovori`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `op_odgovori`
--
ALTER TABLE `op_odgovori`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=225;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ep_korisnik`
--
ALTER TABLE `ep_korisnik`
  ADD CONSTRAINT `ep_korisnik_ibfk_1` FOREIGN KEY (`razinaID`) REFERENCES `ep_pravo` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `ep_korisnik_ibfk_2` FOREIGN KEY (`razred_id`) REFERENCES `ep_razred` (`id`);

--
-- Constraints for table `ep_korisnik_teme`
--
ALTER TABLE `ep_korisnik_teme`
  ADD CONSTRAINT `ep_korisnik_teme_ibfk_1` FOREIGN KEY (`korisnik_id`) REFERENCES `ep_korisnik` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `ep_korisnik_teme_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `ep_teme` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `ep_pitanja_na_testu`
--
ALTER TABLE `ep_pitanja_na_testu`
  ADD CONSTRAINT `ep_pitanja_na_testu_ibfk_1` FOREIGN KEY (`testID`) REFERENCES `ep_test` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `ep_pitanja_na_testu_ibfk_2` FOREIGN KEY (`pitanjeID`) REFERENCES `ep_pitanje` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `ep_pitanja_na_testu_ibfk_3` FOREIGN KEY (`odgovorID`) REFERENCES `op_odgovori` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `ep_pitanje`
--
ALTER TABLE `ep_pitanje`
  ADD CONSTRAINT `ep_pitanje_ibfk_1` FOREIGN KEY (`korisnikID`) REFERENCES `ep_korisnik` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ep_teme` FOREIGN KEY (`temaID`) REFERENCES `ep_teme` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `ep_prava`
--
ALTER TABLE `ep_prava`
  ADD CONSTRAINT `ep_prava_ibfk_1` FOREIGN KEY (`korisnikID`) REFERENCES `ep_korisnik` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `ep_razredi`
--
ALTER TABLE `ep_razredi`
  ADD CONSTRAINT `ep_razredi_ibfk_1` FOREIGN KEY (`razred_id`) REFERENCES `ep_razred` (`id`),
  ADD CONSTRAINT `ep_razredi_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `ep_teme` (`ID`);

--
-- Constraints for table `ep_test`
--
ALTER TABLE `ep_test`
  ADD CONSTRAINT `ep_test_ibfk_1` FOREIGN KEY (`korisnikID`) REFERENCES `ep_korisnik` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `ep_test_odgovori`
--
ALTER TABLE `ep_test_odgovori`
  ADD CONSTRAINT `ep_test_odgovori_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `ep_test` (`ID`);

--
-- Constraints for table `op_odgovori`
--
ALTER TABLE `op_odgovori`
  ADD CONSTRAINT `op_odgovori_ibfk_1` FOREIGN KEY (`pitanjeID`) REFERENCES `ep_pitanje` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `op_odgovori_ibfk_2` FOREIGN KEY (`korisnikID`) REFERENCES `ep_korisnik` (`ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
