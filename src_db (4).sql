-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2025 at 11:59 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `src_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_year`
--

CREATE TABLE `academic_year` (
  `ay_id` int(11) NOT NULL,
  `ay_name` varchar(50) NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admission`
--

CREATE TABLE `admission` (
  `admission_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `year_level_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Late','Absent') NOT NULL,
  `admission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Dean','Faculty','Ssc','MIS Admin') NOT NULL DEFAULT 'Faculty',
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `lab_id` int(11) NOT NULL,
  `lab_name` varchar(100) NOT NULL,
  `location` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `schedule_id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `level` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `semester`
--

CREATE TABLE `semester` (
  `semester_id` int(11) NOT NULL,
  `ay_id` int(11) NOT NULL,
  `semester_now` enum('1','2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(10) NOT NULL,
  `rfid_number` varchar(50) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT '',
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(50) DEFAULT '',
  `gender` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `rfid_number`, `profile_picture`, `first_name`, `middle_name`, `last_name`, `suffix`, `gender`) VALUES
(' 24-000342', ' 24-0003425', '', ' RUFFA', 'ROMERO', 'CALILUNG', '', 'Female'),
('19-0000124', '19-0000124', '', ' ALDRIN', 'PEREZ', 'FAVOR', '', 'Male'),
('20-0000651', '20-0000651', '', ' OLIVER', 'LANSANGAN', 'DELFIN', '', 'Male'),
('21-0000840', '21-0000840', '', ' DIETHER JOSHUA', 'SAGUN', 'CALAGUAS', '', 'Male'),
('21-0000897', '21-0000897', '', ' ERIS', 'ESPIRITU', 'PONIO', '', 'Male'),
('21-0000905', '21-0000905', '', ' CRISTOPHER JAMES', 'BARNES', 'ANGELES', '', 'Male'),
('21-0001062', '21-0001062', '', ' JOHN MICHAEL', 'FLORES', 'DIZON', '', 'Male'),
('21-0001280', '21-0001280', '', ' VINCE NICOLAS', 'ENRIQUEZ', 'SANGALANG', '', 'Male'),
('22-0001230', '22-0001230', '', ' PRINCE', 'JAN', 'VITUG', '', 'Male'),
('22-0001234', '22-0001234', '', ' ROSA', 'CAMMILE', 'MANGAYA', '', 'Female'),
('22-0001235', '22-0001235', '', ' JAYANNE', '', 'MONTEMAYOR', '', 'Female'),
('22-0001236', '22-0001236', '', ' DEANA', '', 'NULUD', '', 'Female'),
('22-0001237', '22-0001237', '', ' TENCHI', '', 'SENYO', '', 'Male'),
('22-0001238', '22-0001238', '', ' ALAN', '', 'TOLENTINO', '', 'Male'),
('22-0001239', '22-0001239', '', ' ANGELA', '', 'VALDEZ', '', 'Female'),
('22-0001456', '22-0001456', '', ' LORENZO EMMANUEL', 'MINGUINTO', 'URBANO', '', 'Male'),
('22-0001559', '22-0001559', '', ' NINO ANJELO', '', 'DIZON', '', 'Male'),
('22-0002120', '22-0002120', '', ' PATRICK JOHN', 'LAPIRA', 'ALIPIO', '', 'Male'),
('22-0002123', '22-0002123', '', ' JEROME ANGELO', 'LEJARDE', 'LANSANG', '', 'Male'),
('22-0002127', '22-0002127', '', ' NICOLE', '', 'ENRIQUEZ', '', 'Female'),
('22-0002128', '22-0002128', '', ' ANGELA', 'ENRIQUEZ', 'AVILA', '', 'Female'),
('22-0002129', '22-0002129', '', ' JOHN LESTER', 'GARCIA', 'BACANI', '', 'Male'),
('22-0002131', '22-0002131', '', ' JOHN CARL', 'DELA PENA', 'DIZON', '', 'Male'),
('22-0002141', '22-0002141', '', ' PRINCESS', 'OCAMPO', 'CALMA', '', 'Female'),
('22-0002142', '22-0002142', '', ' KYLE', 'MANALAC', 'FERNANDEZ', '', 'Female'),
('22-0002145', '22-0002145', '', ' AIRA', 'MANALAC', 'FERNANDEZ', '', 'Female'),
('22-0002146', '22-0002146', '', ' RIMARCH', 'ROQUE', 'DIZON', '', 'Male'),
('22-0002147', '22-0002147', '', ' LHOURD ANDREI', 'LEANO', 'GANZON', '', 'Male'),
('22-0002148', '22-0002148', '', ' MARK GLEN', 'PINEDA', 'GUEVARRA', '', 'Male'),
('22-0002149', '22-0002149', '', ' JEROME', 'PAMAGAN', 'GARCIA', '', 'Male'),
('22-0002152', '22-0002152', '', ' MICAELLA', 'PINEDA', 'MILLOS', '', 'Female'),
('22-0002153', '22-0002153', '', ' ELAINE', 'SALALILA', 'MONTEMAYOR', '', 'Female'),
('22-0002154', '22-0002154', '', ' CLARENCE', 'BUAN', 'DULA', '', 'Male'),
('22-0002155', '22-0002155', '', ' ROY', 'DELA CRUZ', 'JUNTILLA', '', 'Male'),
('22-0002156', '22-0002156', '', ' ASHLIE JOHN', 'VALENCIA', 'GATCHALIAN', '', 'Male'),
('22-0002157', '22-0002157', '', ' RAINIER', 'JOVELLAR', 'LAXAMANA', '', 'Male'),
('22-0002158', '22-0002158', '', ' ROMAN', 'SANTOS', 'MERCADO', '', 'Male'),
('22-0002167', '22-0002167', '', ' GENER JR.', 'VALENCIA', 'MANLAPAZ', '', 'Male'),
('22-0002170', '22-0002170', '', ' LAWRENCE ANDREI', '', 'GUIAO', '', 'Male'),
('22-0002171', '22-0002171', '', ' LLANYELL', 'REYES', 'MANALANG', '', 'Male'),
('22-0002191', '22-0002191', '', ' JOHN EMIL', 'MANALAC', 'TUPAS', '', 'Male'),
('22-0002199', '22-0002199', '', ' JANIRO', 'MENDOZA', 'SERRANO', '', 'Male'),
('22-0002200', '22-0002200', '', ' MARK ANTHONY', 'SISON', 'VILLAFUERTE', '', 'Male'),
('22-0002201', '22-0002201', '', ' FRENCER GIL', 'MANANSALA', 'ROMERO', '', 'Male'),
('22-0002202', '22-0002202', '', ' LIMUEL', 'VARQUEZ', 'MIRANDA', '', 'Male'),
('22-0002204', '22-0002204', '', ' JONNARIE', 'MERCADO', 'ROLL', '', 'Female'),
('22-0002209', '22-0002209', '', ' RONIEL MARCO', 'PUNZALAN', 'BAYAUA', '', 'Male'),
('22-0002224', '22-0002224', '', ' JANESSA', 'HICBAN', 'SANTOS', '', 'Female'),
('22-0002225', '22-0002225', '', ' MARK EDRIAN', 'DE DIOS', 'ROQUE', '', 'Male'),
('22-0002226', '22-0002226', '', ' RALPH', 'AGUILAR', 'SIMBUL', '', 'Male'),
('22-0002264', '22-0002264', '', ' MICHELLE', 'DAGOY', 'GUANLAO', '', 'Female'),
('22-0002294', '22-0002294', '', ' JEROME', 'DETERA', 'OCAMPO', '', 'Male'),
('22-0002365', '22-0002365', '', ' JOHN ARLEY', 'MANALANSAN', 'DABU', '', 'Male'),
('22-0002372', '22-0002372', '', ' TRICIA ANN', 'MANABAT', 'NEPOMUCENO', '', 'Female'),
('22-0002376', '22-0002376', '', ' CRISTINE', '', 'MAAMBONG', '', 'Male'),
('22-0002382', '22-0002382', '', ' VINCENT', '', 'TIATCO', '', 'Male'),
('22-0002387', '22-0002387', '', ' GUEN CARLO', '', 'GOMEZ', '', 'Male'),
('22-0002388', '22-0002388', '', ' JOSEPH LORENZ', 'DIMACALI', 'SISON', '', 'Male'),
('22-0002389', '22-0002389', '', ' JESSA', 'VERZOSA', 'GUANLAO', '', 'Female'),
('22-0002390', '22-0002390', '', ' NEIL TRISTAN', 'PAYUMO', 'MANGILIMAN', '', 'Male'),
('22-0002391', '22-0002391', '', ' KHIAN CARL', 'BORJA', 'HERODICO', '', 'Male'),
('22-0002393', '22-0002393', '', ' RAMLEY JON', 'RAMOS', 'MAGPAYO', '', 'Male'),
('22-0002394', '22-0002394', '', ' LEONEL', 'PACHICO', 'POPATCO', '', 'Male'),
('22-0002398', '22-0002398', '', ' KING WESHLEY', 'GALANG', 'MUTUC', '', 'Male'),
('22-0002400', '22-0002400', '', ' STEVEN', 'LOBERO', 'GONZALES', '', 'Male'),
('22-0002401', '22-0002401', '', ' RICHARD', 'BUNQUE', 'GUEVARRA', '', 'Male'),
('22-0002403', '22-0002403', '', ' CHRISTOPHER', 'MADEJA', 'PANOY', '', 'Male'),
('22-0002407', '22-0002407', '', ' JHAY-R', 'LLENAS', 'MERCADO', '', 'Male'),
('22-0002409', '22-0002409', '', ' VAL NERIE', 'ONG', 'ESPELETA', '', 'Male'),
('22-0002413', '22-0002413', '', ' JOHN LOUISE', 'CUNANAN', 'SEMSEM', '', 'Male'),
('22-0002414', '22-0002414', '', ' RAPH JUSTINE', 'BAUTISTA', 'BUTIAL', '', 'Male'),
('22-0002415', '22-0002415', '', ' ANGEL ROSE ANNE', 'FABROA', 'MALLARI', '', 'Female'),
('22-0002416', '22-0002416', '', ' KELSEY KEMP', 'SAZON', 'BONOAN', '', 'Male'),
('22-0002419', '22-0002419', '', ' PRINCESS SHAINE', 'BUCUD', 'SANTIAGO', '', 'Female'),
('22-0002420', '22-0002420', '', ' YVES ANDREI', 'MANALO', 'SANTOS', '', 'Male'),
('22-0002421', '22-0002421', '', ' CHRISTINE ANNE', 'MALLARI', 'FLORENDO', '', 'Female'),
('22-0002425', '22-0002425', '', ' RICHMOND', 'MARTIN', 'SAFICO', '', 'Male'),
('22-0002431', '22-0002431', '', ' JANRIX HARVEY', 'CRUZ', 'RIVERA', '', 'Male'),
('22-0002434', '22-0002434', '', ' AERIAL JERAMY', 'APARICI', 'LAYUG', '', 'Male'),
('22-0002436', '22-0002436', '', ' RUSSEL KENNETH', 'CASTLLO', 'LIM', '', 'Male'),
('22-0002438', '22-0002438', '', ' ANGELITO', '', 'CRUZ', '', 'Male'),
('22-0002439', '22-0002439', '', ' JOANNA', 'DUNGCA', 'JULIAN', '', 'Female'),
('22-0002442', '22-0002442', '', ' PRINCE ALVIER', 'GALANG', 'NUNEZ', '', 'Male'),
('22-0002453', '22-0002453', '', ' DEXTER', 'SALALILA', 'VILLEGAS', '', 'Male'),
('22-0002455', '22-0002455', '', ' JHAYZHELLE', 'DUNGCA', 'ALVARADO', '', 'Male'),
('22-0002458', '22-0002458', '', ' VERONICA', 'ALBISA', 'MERCADO', '', 'Female'),
('22-0002460', '22-0002460', '', ' JOHN MICHAEL', 'JIMENEZ', 'ELILIO', '', 'Male'),
('22-0002467', '22-0002467', '', ' ROSE ANN', 'DELA CRUZ', 'DELA ROSA', '', 'Female'),
('22-0002507', '22-0002507', '', ' ABRAHAM CHRISTIAN', 'SIMBAHAN', 'GAPPI', '', 'Male'),
('22-0002509', '22-0002509', '', ' JHON LOUIE', 'BOGNOT', 'DIZON', '', 'Male'),
('22-0002525', '22-0002525', '', ' JOHN REVELYN', 'DURAN', 'GONZALES', '', 'Male'),
('22-0002534', '22-0002534', '', ' RHAINE JUSTIN', '', 'MANALAC', '', 'Male'),
('22-0002686', '22-0002686', '', ' JOHN BENEDICT', 'DE GUZMAN', 'DEL ROSARIO', '', 'Male'),
('22-0002726', '22-0002726', '', ' QUEEN MEILANIE', 'BILLENA', 'BENRIL', '', 'Female'),
('22-0002822', '22-0002822', '', ' RHEALLE', 'DELA CRUZ', 'ALKUINO', '', 'Female'),
('22-0003082', '22-0003082', '', ' JAYSON', '', 'BACSAN', '', 'Male'),
('23-0002973', '23-0002973', '', ' JOHN MICHAEL', 'GALANG', 'DAVID', '', 'Male'),
('23-0003005', '23-0003005', '', ' MERWIN', 'PASCUAL', 'HIPOLITO', '', 'Male'),
('23-0003011', '23-0003011', '', ' IGIDIAN VINCE', 'GUINTU', 'CASTRO', '', 'Male'),
('23-0003012', '23-0003012', '', ' REYMART', 'LANSANG', 'PINEDA', '', 'Male'),
('23-0003021', '23-0003021', '', ' C-JAY', 'HICBAN', 'SANTOS', '', 'Male'),
('23-0003022', '23-0003022', '', ' RENZ YUAN', 'GUEVARRA', 'CAYANAN', '', 'Male'),
('23-0003023', '23-0003023', '', ' LEAN', 'CRUZ', 'LAXAMANA', '', 'Female'),
('23-0003026', '23-0003026', '', ' JULIUS CEDRICK', 'GUIAO', 'VIRAY', '', 'Male'),
('23-0003028', '23-0003028', '', ' MARK ATHAN', 'GUANZON', 'MANALANG', '', 'Male'),
('23-0003031', '23-0003031', '', ' JHON MICHAEL', 'OCAMPO', 'BATAC', '', 'Male'),
('23-0003034', '23-0003034', '', ' JOSEPH MIGUEL', '', 'URBANO', '', 'Male'),
('23-0003053', '23-0003053', '', ' ROY FRANCIS', 'SALALILA', 'ENRIQUEZ', '', 'Male'),
('23-0003054', '23-0003054', '', ' KATE LYN', 'PINEDA', 'BUAN', '', 'Female'),
('23-0003058', '23-0003058', '', ' KEN HARVEY', 'REQUIRON', 'SORIANO', '', 'Male'),
('23-0003060', '23-0003060', '', ' JOHN KEISLY', 'DY', 'BACANI', 'LabB-PC20', 'Male'),
('23-0003062', '23-0003062', '', ' JOHN CLARENCE', 'MUTUC', 'DAVID', '', 'Male'),
('23-0003063', '23-0003063', '', ' TIMOTHY EARL', 'CORONA', 'BUAN', '', 'Male'),
('23-0003082', '23-0003082', '', ' JAYSON', 'INDIONGCO', 'BACSAN', '', 'Male'),
('23-0003087', '23-0003087', '', ' MHARK CHEDRICK', '', 'FERNANDO', '', 'Male'),
('23-0003098', '23-0003098', '', ' NICK IVAN', 'BUAN', 'MARIANO', '', 'Male'),
('23-0003103', '23-0003103', '', ' JULIANA CLAIR', 'PINEDA', 'IGNACIO', '', 'Female'),
('23-0003108', '23-0003108', '', ' RENELLE ROBIE', 'DULCE', 'LOPEZ', '', 'Male'),
('23-0003167', '23-0003167', '', ' RYAN', 'MULI', 'GUINTO', '', 'Male'),
('24 -000326', '24 -0003269', '', ' GIRLLY', 'MANALAC', 'FERNANDEZ', '', 'Female'),
('24-0003044', '24-0003044', '', ' ELKAN', 'ALONZO', 'SARMIENTO', '', 'Male'),
('24-0003174', '24-0003174', '', ' REX', 'LAPURE', 'GATCHALIAN', '', 'Male'),
('24-0003256', '24-0003256', '', ' JUSTINE', 'LICAME', 'ANGELES', '', 'Male'),
('24-0003261', '24-0003261', '', ' JOHN PAUL', 'DUNGCA', 'ARCILLA', '', 'Male'),
('24-0003262', '24-0003262', '', ' KAREN', 'DAVID', 'MONTES', '', 'Female'),
('24-0003267', '24-0003267', '', ' KIM WESLEY', 'ANTONIO', 'PERALTA', '', 'Male'),
('24-0003280', '24-0003280', '', ' EDRON', 'BATAC', 'GARCIA', '', 'Male'),
('24-0003285', '24-0003285', '', ' JUSTINE', 'PITUC', 'SINGAN', '', 'Male'),
('24-0003290', '24-0003290', '', ' RONNIE JR.', 'BARBIN', 'HALOG', '', 'Male'),
('24-0003292', '24-0003292', '', ' SHIANN KELLY', 'GARCIA', 'PAYUMO', '', 'Male'),
('24-0003303', '24-0003303', '', ' ANTONETTE', 'DELFIN', 'BERNARDO', '', 'Female'),
('24-0003306', '24-0003306', '', ' JOHN BENEDICT', 'GOMEZ', 'PERRERAS', '', 'Male'),
('24-0003307', '24-0003307', '', ' JERALD', 'FORTIN', 'GALANG', '', 'Male'),
('24-0003308', '24-0003308', '', ' WARREN KING', 'DIMAANO', 'CANLAS', '', 'Male'),
('24-0003309', '24-0003309', '', ' IYA NEL', 'SERRANO', 'MANGARING', '', 'Female'),
('24-0003310', '24-0003310', '', ' SHIN', 'GARCIA', 'BARTOCILLO', '', 'Male'),
('24-0003314', '24-0003314', '', ' JHON FRANCIS', 'GUANZON', 'ALAVE', '', 'Male'),
('24-0003315', '24-0003315', '', ' ALEXANDER JEHRIEL', 'ARRIOLA', 'NULUD', '', 'Male'),
('24-0003318', '24-0003318', '', ' NICOLE', 'BUAN', 'SAMBILE', '', 'Female'),
('24-0003321', '24-0003321', '', ' MARVIN JOEY', 'OCAMPO', 'APAREJADO', '', 'Male'),
('24-0003325', '24-0003325', '', ' MARLYN', '', 'MERCADO', '', 'Female'),
('24-0003331', '24-0003331', '', ' SOPIA MAE', 'CARLOS', 'GUINTO', '', 'Female'),
('24-0003339', '24-0003339', '', ' KEVIN', 'MARIANO', 'CASTRO', '', 'Male'),
('24-0003343', '24-0003343', '', ' ARJAY', 'PERENIA', 'DEL CASTILLO', '', 'Male'),
('24-0003349', '24-0003349', '', ' JAZELLE ANNE', 'GARCES', 'BATAS', '', 'Female'),
('24-0003362', '24-0003362', '', ' ERIC', 'SUYOM', 'CADOCOY', '', 'Male'),
('24-0003375', '24-0003375', '', ' KATHEINE JOY', 'CORTEZ', 'FERNANDO', '', 'Female'),
('24-0003393', '24-0003393', '', ' ERICAH MAE', 'INFANTE', 'VALENCIA', '', 'Female'),
('24-0003410', '24-0003410', '', ' JESSICA', 'CABACUNGAN', 'SALALILA', '', 'Female'),
('24-0003414', '24-0003414', '', ' VHON LEAMBEER', 'DELOS REYES', 'GONZALES', '', 'Male'),
('24-0003425', '24-0003425', '', ' RUFFA', '', 'CALILUNG', '', 'Female'),
('24-0003426', '24-0003426', '', ' JOHN PAUL', 'MARMETO', 'SANTOS', '', 'Male'),
('24-0003433', '24-0003433', '', ' LYKA NICOLE', 'TORRES', 'LAYUG', '', 'Female'),
('24-0003434', '24-0003434', '', ' TRISTAN', 'LUSUNG', 'DUQUE', '', 'Male'),
('24-0003435', '24-0003435', '', ' ALEXA KEITH', 'CALAGOS', 'BOSTERO', '', 'Female'),
('25-0003688', '25-0003688', '', ' TRISHA', 'CABILES', 'BARRUGA', '', 'Female'),
('25-0003690', '25-0003690', '', ' KERWIN', 'PADILLA', 'BUAN', '', 'Male'),
('25-0003691', '25-0003691', '', ' JOSHUA', 'RAMIREZ', 'CAMITAN', '', 'Male'),
('25-0003692', '25-0003692', '', ' JOHN CHLOE', 'TUMINTIN', 'CASUPANAN', '', 'Male'),
('25-0003693', '25-0003693', '', ' DAVE GABRIEL', 'BALTAZAR', 'CRUZ', '', 'Male'),
('25-0003694', '25-0003694', '', ' KAYCEE LYN', 'NARVAREZ', 'DIMAL', '', 'Female'),
('25-0003695', '25-0003695', '', ' NORMAN', 'SAMPANG', 'FRESNOZA JR.', '', 'Male'),
('25-0003698', '25-0003698', '', ' MAUI', 'MALLARI', 'MARCELO', '', 'Female'),
('25-0003704', '25-0003704', '', ' ELLAIZA', 'BACANI', 'NEPOMUCENO', '', 'Female'),
('25-0003706', '25-0003706', '', ' JEROME', 'TORANO', 'PINEDA', '', 'Male'),
('25-0003707', '25-0003707', '', ' JOSHUA', 'LUCINO', 'PINEDA', '', 'Male'),
('25-0003708', '2874015315', '', ' JOLAINE', 'JIMENEZ', 'ANDAMON', '', 'Female'),
('25-0003709', '25-0003709', '', ' EMY JANE', 'LUBIANO', 'ROYO', '', 'Female'),
('25-0003711', '25-0003711', '', ' CID', 'MALIGLIG', 'SOTTO', '', 'Male'),
('25-0003736', '25-0003736', '', ' CINDY', 'ENRIQUEZ', 'ROQUE', '', 'Female'),
('25-0003751', '25-0003751', '', ' GERALD', 'DELA CRUZ', 'PANTIG', '', 'Male'),
('25-0003756', '25-0003756', '', ' TRISTAN', 'CENAL', 'BUAN', '', 'Male'),
('25-0003763', '25-0003763', '', ' JOHN RUSTI', 'BUTIAL', 'NIO', '', 'Male'),
('25-0003765', '25-0003765', '', ' IVAN', 'DELA CRUZ', 'MARIANO', '', 'Male'),
('25-0003768', '25-0003768', '', ' KIRK RINGO', 'BEJASA', 'SERIOS', '', 'Male'),
('25-0003771', '25-0003771', '', ' JAN MARK', 'PAMINTUAN', 'TUAZON', '', 'Male'),
('25-0003774', '25-0003774', '', ' KYLE ZEDDRICK', 'MACALINO', 'SUBOC', '', 'Male'),
('25-0003781', '25-0003781', '', ' SHANNEN', 'MONTEALTO', 'MONSALOD', '', 'Female'),
('25-0003782', '25-0003782', '', ' ANGEL', 'LOBERO', 'GONZALES', '', 'Female'),
('26-4378547', '26-43785476', '', ' JHOANA MARIE', 'MANLULU', 'SALVADOR', '', 'Female'),
('2643794436', '2643794436', '', ' Mark', 'Glen', 'Pineda', '', 'Male'),
('student_id', '	rfid_number', 'profile_picture', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender');

-- --------------------------------------------------------

--
-- Table structure for table `subject`
--

CREATE TABLE `subject` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` int(11) NOT NULL,
  `lecture` int(11) NOT NULL,
  `laboratory` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `year_level`
--

CREATE TABLE `year_level` (
  `year_id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `level` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_year`
--
ALTER TABLE `academic_year`
  ADD PRIMARY KEY (`ay_id`);

--
-- Indexes for table `admission`
--
ALTER TABLE `admission`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `academic_year_id` (`academic_year_id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `year_level_id` (`year_level_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `admission_id` (`admission_id`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `facility`
--
ALTER TABLE `facility`
  ADD PRIMARY KEY (`lab_id`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`section_id`);

--
-- Indexes for table `semester`
--
ALTER TABLE `semester`
  ADD PRIMARY KEY (`semester_id`),
  ADD KEY `ay_id` (`ay_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `rfid_number` (`rfid_number`);

--
-- Indexes for table `subject`
--
ALTER TABLE `subject`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `year_level`
--
ALTER TABLE `year_level`
  ADD PRIMARY KEY (`year_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_year`
--
ALTER TABLE `academic_year`
  MODIFY `ay_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admission`
--
ALTER TABLE `admission`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility`
--
ALTER TABLE `facility`
  MODIFY `lab_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `semester`
--
ALTER TABLE `semester`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subject`
--
ALTER TABLE `subject`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `year_level`
--
ALTER TABLE `year_level`
  MODIFY `year_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admission`
--
ALTER TABLE `admission`
  ADD CONSTRAINT `admission_academic_year_fk` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_year` (`ay_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_course_fk` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_schedule_fk` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_section_fk` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_semester_fk` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`semester_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_subject_fk` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_year_level_fk` FOREIGN KEY (`year_level_id`) REFERENCES `year_level` (`year_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_admission_fk` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_schedule_fk` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `facility` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `semester`
--
ALTER TABLE `semester`
  ADD CONSTRAINT `semester_ibfk_1` FOREIGN KEY (`ay_id`) REFERENCES `academic_year` (`ay_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- --------------------------------------------------------
-- Normalization additions: extend students and add support tables

-- Add commonly used columns to students (keeps existing columns)
ALTER TABLE `students`
  ADD COLUMN `email` varchar(150) DEFAULT NULL AFTER `suffix`,
  ADD COLUMN `department` varchar(50) DEFAULT NULL AFTER `email`,
  ADD COLUMN `course_strand` varchar(50) DEFAULT NULL AFTER `department`,
  ADD COLUMN `password` varchar(255) DEFAULT NULL AFTER `course_strand`,
  ADD COLUMN `profile_pic` varchar(255) DEFAULT NULL AFTER `profile_picture`,
  ADD COLUMN `is_verified` tinyint(1) NOT NULL DEFAULT 0 AFTER `profile_pic`,
  ADD COLUMN `research_file` varchar(255) DEFAULT NULL AFTER `is_verified`,
  ADD COLUMN `reset_token` varchar(255) DEFAULT NULL AFTER `research_file`,
  ADD COLUMN `reset_token_expiry` datetime DEFAULT NULL AFTER `reset_token`,
  ADD COLUMN `last_password_change` datetime DEFAULT NULL AFTER `reset_token_expiry`,
  ADD COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `last_password_change`;

-- Strands lookup table
CREATE TABLE IF NOT EXISTS `strands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `strand` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_strand` (`strand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Books: unified research submissions repository
CREATE TABLE IF NOT EXISTS `books` (
  `book_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `abstract` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `authors` text DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `course_strand` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `views` int(11) NOT NULL DEFAULT 0,
  `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `student_id` varchar(10) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `year` varchar(25) DEFAULT NULL,
  `title_norm` varchar(255) GENERATED ALWAYS AS (LOWER(TRIM(`title`))) PERSISTENT,
  PRIMARY KEY (`book_id`),
  KEY `idx_books_status` (`status`),
  KEY `idx_books_year` (`year`),
  KEY `idx_books_student` (`student_id`),
  KEY `idx_books_title_norm` (`title_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Compatibility view: map books to legacy research_submission structure
DROP VIEW IF EXISTS `research_submission`;
CREATE VIEW `research_submission` AS
SELECT
  b.`book_id` AS `id`,
  b.`title` AS `title`,
  b.`year` AS `year`,
  b.`abstract` AS `abstract`,
  b.`keywords` AS `keywords`,
  b.`authors` AS `author`,
  NULL AS `members`,
  b.`department` AS `department`,
  b.`course_strand` AS `course_strand`,
  b.`image` AS `image`,
  b.`document` AS `document`,
  b.`views` AS `views`,
  b.`submission_date` AS `submission_date`,
  b.`student_id` AS `student_id`,
  b.`status` AS `status`
FROM `books` b;

-- Bookmarks for saved research
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(10) NOT NULL,
  `book_id` int(11) NOT NULL,
  `bookmarked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bookmark` (`student_id`, `book_id`),
  KEY `idx_bm_student` (`student_id`),
  KEY `idx_bm_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Activity logs (generic audit trail)
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_type` varchar(20) NOT NULL,
  `actor_id` varchar(64) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Foreign keys for books and bookmarks
ALTER TABLE `books`
  ADD CONSTRAINT `fk_books_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_books_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `employees`(`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `bookmarks`
  ADD CONSTRAINT `fk_bookmarks_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`book_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookmarks_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Helpful virtual alias for legacy code using paper_id
ALTER TABLE `bookmarks`
  ADD COLUMN `paper_id` INT AS (`book_id`) VIRTUAL;

-- --------------------------------------------------------
-- Employees normalization (safe migration; keeps employee_id as INT)
-- Adds columns used by the app and for cross-system compatibility
ALTER TABLE `employees`
  ADD COLUMN IF NOT EXISTS `role` enum('Researh_Adviser') NULL,
  ADD COLUMN IF NOT EXISTS `department` varchar(50) NULL,
  ADD COLUMN IF NOT EXISTS `permissions` text NULL,
  ADD COLUMN IF NOT EXISTS `phone` varchar(30) NULL,
  ADD COLUMN IF NOT EXISTS `last_login_at` datetime NULL,
  ADD COLUMN IF NOT EXISTS `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Helpful indexes for filtering
ALTER TABLE `employees`
  ADD INDEX `idx_is_archived` (`is_archived`),
  ADD INDEX `idx_department` (`department`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
