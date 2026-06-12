-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 12, 2026 at 04:25 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `login_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `denda`
--

CREATE TABLE `denda` (
  `id` int NOT NULL,
  `jenis_denda` varchar(50) NOT NULL,
  `persentase_denda` decimal(5,2) NOT NULL,
  `nilai_minimal` decimal(12,2) NOT NULL,
  `nilai_maksimal` decimal(12,2) NOT NULL,
  `hari_keterlambatan` int NOT NULL,
  `deskripsi` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `denda`
--

INSERT INTO `denda` (`id`, `jenis_denda`, `persentase_denda`, `nilai_minimal`, `nilai_maksimal`, `hari_keterlambatan`, `deskripsi`) VALUES
(1, 'Keterlambatan 1-7 hari', '2.50', '25000.00', '50000.00', 7, 'Denda untuk keterlambatan 1-7 hari dari tanggal jatuh tempo'),
(2, 'Keterlambatan 8-14 hari', '5.00', '50000.00', '100000.00', 14, 'Denda untuk keterlambatan 8-14 hari dari tanggal jatuh tempo'),
(3, 'Keterlambatan 15-30 hari', '7.50', '75000.00', '150000.00', 30, 'Denda untuk keterlambatan 15-30 hari dari tanggal jatuh tempo'),
(4, 'Keterlambatan >30 hari', '10.00', '100000.00', '200000.00', 90, 'Denda untuk keterlambatan lebih dari 30 hari dari tanggal jatuh tempo'),
(5, 'Denda tetap ringan', '1.00', '10000.00', '50000.00', 3, 'Denda ringan untuk keterlambatan singkat'),
(6, 'Keterlambatan khusus', '3.00', '30000.00', '75000.00', 10, 'Denda khusus untuk kondisi tertentu'),
(7, 'Denda akhir bulan', '4.00', '40000.00', '80000.00', 21, 'Denda khusus jika lewat dari tanggal 21'),
(8, 'Keterlambatan parah', '12.50', '125000.00', '250000.00', 60, 'Denda untuk keterlambatan sangat parah'),
(9, 'Denda administrasi', '0.50', '5000.00', '20000.00', 1, 'Biaya administrasi keterlambatan'),
(10, 'Keterlambatan akhir tahun', '15.00', '150000.00', '300000.00', 45, 'Denda khusus untuk keterlambatan akhir tahun');

-- --------------------------------------------------------

--
-- Table structure for table `dokter`
--

CREATE TABLE `dokter` (
  `id` int NOT NULL,
  `kode_dokter` varchar(20) NOT NULL,
  `nama_dokter` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `spesialisasi_id` int DEFAULT NULL,
  `no_sip` varchar(50) DEFAULT NULL,
  `tgl_berlaku_sip` date DEFAULT NULL,
  `status` enum('aktif','tidak aktif') DEFAULT 'aktif',
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `dokter`
--

INSERT INTO `dokter` (`id`, `kode_dokter`, `nama_dokter`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `no_telepon`, `email`, `spesialisasi_id`, `no_sip`, `tgl_berlaku_sip`, `status`, `foto`, `created_at`, `updated_at`) VALUES
(1, 'DR001', 'Dr. Rachman Maulana', 'L', 'Jakarta', '1980-05-15', 'Jl. Sudirman No. 123, Jakarta Pusat', '081234567890', 'rachman@rs.com', 5, 'SIP/001/2024', '2026-12-31', 'aktif', NULL, '2025-12-18 12:36:00', '2025-12-19 09:27:10'),
(2, 'DR002', 'Dr. Louis Hasashi Halim', 'L', 'Bayur', '1985-08-20', 'Bayur', '081234567891', 'Louis@rs.com', 2, 'SIP/002/2024', '2026-12-31', 'aktif', NULL, '2025-12-18 12:36:00', '2025-12-18 14:39:03'),
(3, 'DR003', 'Dr. Bunaya Ardik S', 'L', 'Jawa Tengah,Kebumen', '1975-03-10', 'Pasar Kemis', '081234567892', 'bunaya@rs.com', 8, 'SIP/003/2028', '2026-12-31', 'aktif', NULL, '2025-12-18 12:36:00', '2025-12-22 06:13:48'),
(5, 'DR005', 'Dr. Fachrul Hannan W', 'L', 'Pati', '1978-07-30', 'Kroncong', '081234567894', 'Fachrul@rs.com', 10, 'SIP/005/2022', '2024-10-31', 'aktif', NULL, '2025-12-18 12:36:00', '2025-12-20 11:53:05'),
(6, 'DR006', 'Dr. Marsya Audia', 'P', 'Kabupaten Tangerang', '2006-09-18', 'Rajeg', '089567366598', 'marsya@rs.com', 3, '12365437733', '2028-05-18', 'aktif', NULL, '2025-12-18 14:30:01', '2025-12-18 14:30:49'),
(7, 'DR007', 'Dr.Elen Novita Sari', 'P', 'Jatiuwung', '2006-05-18', 'Tangerang', '089567366598', 'Elen@rs.com', 2, '12241', '2026-10-23', 'aktif', NULL, '2025-12-18 14:50:02', '2025-12-23 07:48:38'),
(9, 'DR009', 'Dr. Ferdi Harsono', 'L', 'Batu Ceper', '2006-06-05', 'Batu Ceper', '0807576483748', 'ferdi@rs.com', 10, '12365437734', '2026-12-22', 'aktif', NULL, '2025-12-23 07:45:53', '2025-12-31 09:10:12');

-- --------------------------------------------------------

--
-- Table structure for table `faskes`
--

CREATE TABLE `faskes` (
  `id` int NOT NULL,
  `kode_faskes` varchar(20) NOT NULL,
  `nama_faskes` varchar(100) NOT NULL,
  `jenis_faskes` varchar(50) NOT NULL,
  `alamat` text NOT NULL,
  `kota` varchar(50) NOT NULL,
  `provinsi` varchar(50) NOT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `no_telepon` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `direktur` varchar(100) DEFAULT NULL,
  `status` enum('aktif','tidak aktif') DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `faskes`
--

INSERT INTO `faskes` (`id`, `kode_faskes`, `nama_faskes`, `jenis_faskes`, `alamat`, `kota`, `provinsi`, `kode_pos`, `no_telepon`, `email`, `website`, `direktur`, `status`, `created_at`, `updated_at`) VALUES
(1, 'FSK001', 'RSUP Persahabatan', 'Rumah Sakit', 'Jl. Persahabatan Raya No. 1', 'Jakarta Timur', 'DKI Jakarta', '13230', '0214891708', 'info@persahabatan.go.id', 'https://www.persahabatan.go.id', 'Dr. Ahmad Yani', 'aktif', '2025-12-19 12:55:43', '2025-12-19 16:45:19'),
(2, 'FSK002', 'RSUD Kota Bandung', 'Rumah Sakit', 'Jl. Rumah Sakit No. 22', 'Bandung', 'Jawa Barat', '40292', '0224212171', 'rsud@bandung.go.id', '', 'Dr. Siti Nurhaliza', 'aktif', '2025-12-19 12:55:43', '2025-12-19 16:46:11'),
(3, 'FSK003', 'Puskesmas Kecamatan Ciracas', 'Puskesmas', 'Jl. Raya Bogor KM 20', 'Jakarta Timur', 'DKI Jakarta', '13720', '02187701234', 'puskesmas@ciracas.go.id', '', 'Dr. Budi Santoso', 'aktif', '2025-12-19 12:55:43', '2025-12-19 16:45:47'),
(4, 'FSK004', 'RS Siloam', 'Rumah Sakit', 'Jl. Jendral Sudirman Kav. 21', 'Jakarta Pusat', 'DKI Jakarta', '10220', '0212513131', 'info@siloamhospitals.com', 'https://www.siloamhospitals.com', 'Dr. Michael Chang', 'aktif', '2025-12-19 12:55:43', '2025-12-19 16:46:40'),
(5, 'FSK005', 'Klinik Sehat Bahagia', 'Klinik', 'Jl. Merdeka No. 45', 'Bandung', 'Jawa Barat', '40115', '0224234567', 'klinik@sehatbahagia.com', '', 'Dr. Linda Wijaya', 'aktif', '2025-12-19 12:55:43', '2025-12-19 16:46:55'),
(6, 'FSK006', 'RSUD Kota Tangerang', 'Rumah Sakit', 'Perumahan Moderland, Jl. Pulau Putri Raya, RT.05/RW.03, Klp. Indah, Kec. Tangerang, Kota Tangerang, Banten', 'Tangerang', 'Banten', '15543', '089537645642', 'rsudkotatangerang@rs.com', 'https://rsud.tangerangkota.go.id/', 'Dr. Putra Pramuda', 'aktif', '2025-12-20 02:56:49', '2025-12-20 02:56:49'),
(7, 'FSK007', 'RSUP Dr Sintanala', 'Rumah Sakit', 'Jl. DR. Sitanala No.99, RT.002/RW.003, Karang Sari, Kec. Neglasari, Kota Tangerang, Banten 15121', 'Tangerang', 'Banten', '15436', '089675847658', 'rsupsintanala@gmail.com', 'https://rsup-drsitanala.go.id', 'Dr. Adi Setyawann', 'aktif', '2025-12-20 12:21:16', '2025-12-31 07:52:01');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_dokter`
--

CREATE TABLE `jadwal_dokter` (
  `id` int NOT NULL,
  `dokter_id` int NOT NULL,
  `poli_id` int NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jadwal_dokter`
--

INSERT INTO `jadwal_dokter` (`id`, `dokter_id`, `poli_id`, `hari`, `jam_mulai`, `jam_selesai`) VALUES
(1, 1, 1, 'Senin', '08:00:00', '12:00:00'),
(2, 1, 1, 'Rabu', '08:00:00', '12:00:00'),
(3, 2, 2, 'Selasa', '09:00:00', '15:00:00'),
(4, 2, 2, 'Kamis', '09:00:00', '15:00:00'),
(5, 3, 3, 'Senin', '10:00:00', '16:00:00'),
(6, 3, 3, 'Jumat', '10:00:00', '16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `kelas`
--

CREATE TABLE `kelas` (
  `id` int NOT NULL,
  `kode_kelas` varchar(10) NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `deskripsi` text NOT NULL,
  `iuran_per_bulan` decimal(10,2) NOT NULL,
  `fasilitas` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kelas`
--

INSERT INTO `kelas` (`id`, `kode_kelas`, `nama_kelas`, `deskripsi`, `iuran_per_bulan`, `fasilitas`, `created_at`, `updated_at`) VALUES
(1, 'KD1', 'Kelas 1', 'Kelas dengan fasilitas terbaik untuk perawatan kesehatan', '150000.00', 'Rawat inap kamar VIP, Konsultasi dokter spesialis tanpa batas, Obat-obatan komprehensif, Pemeriksaan laboratorium lengkap, Fisioterapi, Akomodasi untuk 1 pendamping', '2025-12-23 08:43:37', '2025-12-24 18:23:40'),
(2, 'KLS2', 'Kelas 2', 'Kelas menengah dengan fasilitas standar yang baik', '100000.00', 'Rawat inap kamar kelas 2, Konsultasi dokter spesialis terbatas, Obat-obatan standar, Pemeriksaan laboratorium dasar, Rawat jalan terbatas', '2025-12-23 08:43:37', '2025-12-24 18:24:03'),
(3, 'KLS3', 'Kelas 3', 'Kelas dasar dengan fasilitas minimal untuk perlindungan kesehatan', '40000.00', 'Rawat inap kamar kelas 3, Konsultasi dokter umum, Obat generik, Pemeriksaan dasar, Rawat jalan terbatas', '2025-12-23 08:43:37', '2025-12-28 04:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `klaim`
--

CREATE TABLE `klaim` (
  `id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `no_klaim` varchar(50) NOT NULL,
  `diagnosa` text NOT NULL,
  `nominal_klaim` decimal(15,2) NOT NULL,
  `status_klaim` enum('pending','approved','rejected') NOT NULL,
  `tanggal_klaim` date NOT NULL,
  `catatan` text,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `klaim`
--

INSERT INTO `klaim` (`id`, `peserta_id`, `no_klaim`, `diagnosa`, `nominal_klaim`, `status_klaim`, `tanggal_klaim`, `catatan`, `updated_at`) VALUES
(1, 20, 'KL-20251228001', 'Demam Berdarah Dengue', '2500000.00', 'approved', '2025-12-15', '', '2025-12-29 07:09:09'),
(2, 21, 'KL-20251228002', 'Tifoid Fever', '1800000.00', 'approved', '2025-12-18', '', '2025-12-29 05:38:04'),
(3, 22, 'KL-20251228003', 'Appendicitis Akut', '5000000.00', 'approved', '2025-12-20', NULL, NULL),
(4, 23, 'KL-20251228004', 'Hipertensi Grade 2', '1200000.00', 'approved', '2025-12-22', NULL, NULL),
(5, 24, 'KL-20251228005', 'Diabetes Melitus Tipe 2', '3500000.00', 'approved', '2025-12-24', '', '2025-12-29 06:43:43'),
(6, 28, 'KL-20251228006', 'Flu', '4000000.00', 'approved', '2025-12-29', '', '2026-01-05 14:29:22'),
(7, 26, 'KL-20251228007', 'Darah Tinggi', '1000000.00', 'approved', '2025-12-29', '', NULL),
(8, 50, 'KL-20251228008', 'Maag', '2000000.00', 'approved', '2025-12-29', '', NULL),
(9, 52, 'KL-20251229-3375', 'Malaria', '400000.00', 'approved', '2025-12-29', '', NULL),
(10, 55, 'KL-20251228009', 'Ambien', '500000.00', 'approved', '2026-01-06', '', NULL),
(11, 57, 'KL-20251228009', 'Flu', '500000.00', 'approved', '2026-01-06', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `kunjungan`
--

CREATE TABLE `kunjungan` (
  `id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `faskes_id` int NOT NULL,
  `dokter_id` int DEFAULT NULL,
  `tanggal_kunjungan` date NOT NULL,
  `jam_kunjungan` time NOT NULL,
  `jenis_pelayanan` enum('rawat_jalan','rawat_inap','ugd','rutin') NOT NULL,
  `poli` varchar(100) DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `keluhan` text,
  `biaya_administrasi` decimal(15,2) NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('terdaftar','diproses','selesai','batal') DEFAULT 'terdaftar',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kunjungan`
--

INSERT INTO `kunjungan` (`id`, `peserta_id`, `faskes_id`, `dokter_id`, `tanggal_kunjungan`, `jam_kunjungan`, `jenis_pelayanan`, `poli`, `diagnosis`, `keluhan`, `biaya_administrasi`, `user_id`, `status`, `created_at`, `updated_at`) VALUES
(31, 50, 4, 3, '2025-12-28', '12:58:00', 'rutin', 'Hati', 'Maag', NULL, '100000.00', 5, 'terdaftar', '2025-12-28 06:00:08', '2025-12-29 12:13:57'),
(32, 26, 2, 3, '2025-12-28', '13:03:00', 'ugd', 'penyakit dalam', 'Darah Tinggi', 'Darah Tinggi', '70000.00', 5, 'diproses', '2025-12-28 06:06:06', '2025-12-29 06:32:29'),
(33, 21, 6, 2, '2025-12-29', '05:24:00', 'rawat_inap', 'Rawat Inap Umum', 'Tifoid Fever', 'diabetes', '100000.00', 5, 'selesai', '2025-12-28 22:27:35', '2025-12-29 06:30:04'),
(34, 28, 3, 7, '2025-12-29', '05:28:00', 'ugd', 'IGD (Instalasi Gawat Darurat)', 'Flu', 'flu', '150000.00', 5, 'selesai', '2025-12-28 22:30:25', '2025-12-29 06:30:27'),
(35, 20, 7, 1, '2025-12-29', '13:36:00', 'rawat_jalan', 'Rawat Inap Umum', 'Demam Berdarah', NULL, '2000000.00', 5, 'selesai', '2025-12-29 06:37:50', '2025-12-29 06:37:50'),
(36, 23, 2, 2, '2025-12-29', '13:40:00', 'rutin', 'IGD (Instalasi Gawat Darurat)', 'Hirpetensi Grade 2', NULL, '1200000.00', 5, 'selesai', '2025-12-29 06:42:35', '2025-12-29 06:43:23'),
(37, 24, 1, 1, '2025-12-29', '13:43:00', 'rutin', 'IGD (Instalasi Gawat Darurat)', 'Diabetes Melitus tipe 2', NULL, '3500000.00', 5, 'selesai', '2025-12-29 06:44:52', '2025-12-29 06:45:55'),
(38, 22, 7, 1, '2025-12-29', '13:46:00', 'ugd', 'IGD (Instalasi Gawat Darurat)', 'Appendicitis Akut', NULL, '5000000.00', 5, 'selesai', '2025-12-29 06:47:01', '2025-12-29 06:47:40'),
(39, 52, 7, 1, '2025-12-29', '18:53:00', 'rutin', 'penyakit dalam', 'Anemia', NULL, '300000.00', 5, 'selesai', '2025-12-29 11:55:07', '2025-12-29 11:55:07'),
(40, 55, 1, 2, '2026-01-06', '08:01:00', 'rutin', 'penyakit dalam', 'Pemeriksaan Fisik', 'Mules', '100000.00', 5, 'diproses', '2026-01-06 01:03:16', '2026-01-06 01:03:16');

-- --------------------------------------------------------

--
-- Table structure for table `log_activity`
--

CREATE TABLE `log_activity` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `activity` varchar(100) NOT NULL,
  `ip_addres` varchar(45) NOT NULL,
  `user_agent` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `metode_pembayaran`
--

CREATE TABLE `metode_pembayaran` (
  `id` int NOT NULL,
  `kode_metode` varchar(20) NOT NULL,
  `nama_metode` varchar(50) NOT NULL,
  `jenis` enum('Bank Transfer','E-Wallet','Tunai','Kartu') NOT NULL,
  `nama_bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `atas_nama` varchar(100) DEFAULT NULL,
  `biaya_admin` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `metode_pembayaran`
--

INSERT INTO `metode_pembayaran` (`id`, `kode_metode`, `nama_metode`, `jenis`, `nama_bank`, `no_rekening`, `atas_nama`, `biaya_admin`) VALUES
(46, 'TRF_BCA', 'Transfer Bank BCA', 'Bank Transfer', 'Bank Central Asia', '1234567890', 'Organisasi Kita', '2500.00'),
(47, 'TRF_MDR', 'Transfer Bank Mandiri', 'Bank Transfer', 'Bank Mandiri', '0987654321', 'Organisasi Kita', '3000.00'),
(48, 'TRF_BRI', 'Transfer Bank BRI', 'Bank Transfer', 'Bank Rakyat Indonesia', '1122334455', 'Organisasi Kita', '2000.00'),
(49, 'TRF_BNI', 'Transfer Bank BNI', 'Bank Transfer', 'Bank Negara Indonesia', '5566778899', 'Organisasi Kita', '2500.00'),
(50, 'EW_OVO', 'OVO', 'E-Wallet', NULL, NULL, 'Organisasi Kita', '1500.00'),
(51, 'EW_GOJEK', 'GoPay', 'E-Wallet', NULL, NULL, 'Organisasi Kita', '2000.00'),
(52, 'EW_DANA', 'DANA', 'E-Wallet', NULL, NULL, 'Organisasi Kita', '1000.00'),
(53, 'CASH', 'Tunai Langsung', 'Tunai', NULL, NULL, NULL, '0.00'),
(54, 'CARD_DEB', 'Kartu Debit', 'Kartu', NULL, NULL, NULL, '5000.00'),
(55, 'CARD_CRD', 'Kartu Kredit', 'Kartu', NULL, NULL, NULL, '7500.00'),
(56, 'TRF_CIMB', 'Transfer Bank CIMB', 'Bank Transfer', 'CIMB Niaga', '6677889900', 'Organisasi Kita', '3500.00'),
(57, 'EW_SHOPEE', 'ShopeePay', 'E-Wallet', NULL, NULL, 'Organisasi Kita', '1200.00'),
(58, 'TRF_PANIN', 'Transfer Bank Panin', 'Bank Transfer', 'Bank Panin', '7788990011', 'Organisasi Kita', '4000.00'),
(59, 'CASH_COUNTER', 'Tunai di Kasir', 'Tunai', NULL, NULL, NULL, '0.00'),
(60, 'TRF_BSI', 'Transfer Bank BSI', 'Bank Transfer', 'Bank Syariah Indonesia', '8899001122', 'Organisasi Kita', '2000.00');

-- --------------------------------------------------------

--
-- Table structure for table `obat`
--

CREATE TABLE `obat` (
  `id` int NOT NULL,
  `kode_obat` varchar(20) NOT NULL,
  `nama_obat` varchar(100) NOT NULL,
  `jenis` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `stok` int NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `tanggal_expired` date DEFAULT NULL,
  `status` enum('Aktif','Non-Aktif') DEFAULT 'Aktif',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `obat`
--

INSERT INTO `obat` (`id`, `kode_obat`, `nama_obat`, `jenis`, `satuan`, `stok`, `harga`, `tanggal_expired`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'OB001', 'Paracetamol 500mg', 'Generik', 'Tablet', 100, '5000.00', '2030-07-21', 'Aktif', '', '2025-12-18 15:59:08', '2025-12-21 15:21:41'),
(2, 'OB002', 'Amoxicillin 500mg', 'Generik', 'Kapsul', 50, '8000.00', '2029-06-21', 'Aktif', '', '2025-12-18 15:59:08', '2025-12-21 15:21:53'),
(3, 'OB003', 'Vitamin C 1000mg', 'Vitamin', 'Tablet', 200, '15000.00', '2028-10-21', 'Aktif', '', '2025-12-18 15:59:08', '2025-12-21 15:22:12'),
(4, 'OB004', 'Salbutamol 2mg', 'Generik', 'Tablet', 30, '12000.00', '2028-12-21', 'Aktif', '', '2025-12-18 15:59:08', '2025-12-21 15:22:23'),
(5, 'OB005', 'Omeprazole 20mg', 'Paten', 'Kapsul', 8, '25000.00', '2025-12-24', 'Non-Aktif', '', '2025-12-18 15:59:08', '2025-12-24 06:59:45'),
(6, '0B006', 'OBH Combi 5 ML', 'Herbal', 'Tablet', 60, '25000.00', '2028-10-21', 'Aktif', '', '2025-12-19 09:35:50', '2025-12-21 15:21:16'),
(7, '0B007', 'Tolak Angin', 'Herbal', 'Drop', 60, '15000.00', '2027-06-20', 'Aktif', '', '2025-12-20 12:30:58', '2025-12-20 12:30:58'),
(8, '0B008', 'Antimu', 'Vitamin', 'Drop', 20, '5000.00', '2028-10-21', 'Aktif', '', '2025-12-20 12:36:22', '2025-12-24 06:59:22');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int NOT NULL,
  `iuran_id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `no_pembayaran` int NOT NULL,
  `tanggal_bayar` int NOT NULL,
  `jumlah_bayar` int NOT NULL,
  `metode_pembayaran` enum('Transfer Bank','ATM','Mobile Banking','Cash','Debit Card','Kredit') NOT NULL,
  `nama_bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `reference_number` varchar(50) NOT NULL,
  `bukti_bayar` varchar(225) NOT NULL,
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `keterangan` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `iuran_id`, `peserta_id`, `no_pembayaran`, `tanggal_bayar`, `jumlah_bayar`, `metode_pembayaran`, `nama_bank`, `no_rekening`, `reference_number`, `bukti_bayar`, `verified_by`, `verified_at`, `keterangan`) VALUES
(1, 1, 101, 1001, 20241224, 500000, 'Transfer Bank', 'Bank BCA', '1234567890', 'REF001234', 'bukti_bayar_001.jpg', 1, '2025-12-24 18:30:00', 'Pembayaran iuran bulan Januari 2025'),
(2, 2, 102, 1002, 20241223, 750000, 'ATM', 'Bank Mandiri', '0987654321', 'REF002345', 'bukti_bayar_002.jpg', 1, '2025-12-23 14:20:00', 'Pembayaran iuran bulan Desember 2024'),
(3, 3, 103, 1003, 20241222, 300000, 'Mobile Banking', 'Bank BRI', '1122334455', 'REF003456', 'bukti_bayar_003.png', 2, '2025-12-22 10:15:00', 'Pembayaran iuran pertama'),
(4, 4, 104, 1004, 20241221, 600000, 'Cash', NULL, NULL, 'REF004567', 'bukti_bayar_004.jpg', NULL, NULL, 'Pembayaran tunai di kantor'),
(5, 5, 105, 1005, 20241220, 450000, 'Debit Card', 'Bank BNI', '5566778899', 'REF005678', 'bukti_bayar_005.jpg', 1, '2025-12-20 16:45:00', 'Pembayaran via merchant'),
(6, 6, 106, 1006, 20241219, 800000, 'Kredit', 'Bank CIMB', '6677889900', 'REF006789', 'bukti_bayar_006.png', 3, '2025-12-19 11:10:00', 'Pembayaran cicilan ke-3');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran_bpjs`
--

CREATE TABLE `pembayaran_bpjs` (
  `id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `tanggal_pembayaran` date NOT NULL,
  `jumlah_dibayarkan` decimal(12,2) NOT NULL,
  `metode_pembayaran` varchar(50) NOT NULL,
  `denda` decimal(12,2) DEFAULT '0.00',
  `status_pembayaran` enum('success','pending','failed') DEFAULT 'pending',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pembayaran_bpjs`
--

INSERT INTO `pembayaran_bpjs` (`id`, `peserta_id`, `tanggal_pembayaran`, `jumlah_dibayarkan`, `metode_pembayaran`, `denda`, `status_pembayaran`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 20, '2025-12-26', '50000.00', 'transfer', '0.00', 'success', '', '2025-12-26 09:28:59', '2025-12-26 09:28:59'),
(15, 23, '2025-12-27', '50000.00', 'tunai', '0.00', 'success', '', '2025-12-27 07:47:05', '2025-12-27 07:47:05'),
(16, 50, '2025-12-28', '100000.00', 'tunai', '0.00', 'success', '', '2025-12-28 04:09:30', '2025-12-28 04:09:30'),
(20, 52, '2026-01-05', '100000.00', 'transfer', '0.00', 'success', '', '2026-01-05 12:40:15', '2026-01-05 12:40:15'),
(21, 28, '2026-01-05', '100000.00', 'transfer', '0.00', 'success', '', '2026-01-05 12:41:42', '2026-01-05 12:41:42'),
(22, 26, '2026-01-06', '100000.00', 'tunai', '0.00', 'success', '', '2026-01-06 00:36:40', '2026-01-06 00:36:40'),
(23, 26, '2026-01-06', '100000.00', 'transfer', '0.00', 'success', '', '2026-01-06 00:37:14', '2026-01-06 00:37:14');

-- --------------------------------------------------------

--
-- Table structure for table `pendaftaran_peserta`
--

CREATE TABLE `pendaftaran_peserta` (
  `id` int NOT NULL,
  `no_pendaftaran` varchar(20) NOT NULL,
  `peserta_id` int NOT NULL,
  `tanggal_daftar` date NOT NULL,
  `tanggal_berlaku` date NOT NULL,
  `kelas_id` int NOT NULL,
  `faskes_id` int NOT NULL,
  `metode_pembayaran` enum('transfer','kredit','debit','tunai') NOT NULL,
  `nama_bank` varchar(50) NOT NULL,
  `no_rekening` varchar(50) NOT NULL,
  `no_kartu_kredit` varchar(50) NOT NULL,
  `nama_kartu` varchar(100) NOT NULL,
  `iuran_bulanan` decimal(10,2) NOT NULL,
  `biaya_admin` decimal(10,2) NOT NULL,
  `ppn` decimal(10,2) NOT NULL,
  `total_pembayaran` decimal(10,2) NOT NULL,
  `status_pembayaran` enum('pending','paid','failed') NOT NULL,
  `bukti_pembayaran` varchar(225) NOT NULL,
  `user_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pendaftaran_peserta`
--

INSERT INTO `pendaftaran_peserta` (`id`, `no_pendaftaran`, `peserta_id`, `tanggal_daftar`, `tanggal_berlaku`, `kelas_id`, `faskes_id`, `metode_pembayaran`, `nama_bank`, `no_rekening`, `no_kartu_kredit`, `nama_kartu`, `iuran_bulanan`, `biaya_admin`, `ppn`, `total_pembayaran`, `status_pembayaran`, `bukti_pembayaran`, `user_id`) VALUES
(1, 'REG001', 101, '2025-12-01', '2026-11-30', 1, 3, 'transfer', 'BCA', '1234567890', '', '', '500000.00', '10000.00', '55000.00', '565000.00', 'paid', 'bukti1.jpg', 1),
(2, 'REG002', 102, '2025-12-05', '2026-12-04', 2, 5, 'kredit', '', '', '4111111111111111', 'John Doe', '750000.00', '15000.00', '82500.00', '847500.00', 'pending', 'bukti2.jpg', 2),
(3, 'REG003', 103, '2025-12-10', '2026-12-09', 1, 2, 'tunai', '', '', '', '', '500000.00', '10000.00', '55000.00', '565000.00', 'paid', 'bukti3.jpg', 3),
(4, 'REG004', 104, '2025-12-15', '2026-12-14', 3, 4, 'debit', 'Mandiri', '0987654321', '', '', '1000000.00', '20000.00', '110000.00', '1130000.00', 'failed', 'bukti4.jpg', 1),
(5, 'REG005', 105, '2025-12-20', '2026-12-19', 2, 1, 'transfer', 'BNI', '1122334455', '', '', '750000.00', '15000.00', '82500.00', '847500.00', 'paid', 'bukti5.jpg', 2);

-- --------------------------------------------------------

--
-- Table structure for table `peserta`
--

CREATE TABLE `peserta` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `no_kartu` varchar(20) NOT NULL,
  `nik` varchar(16) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date NOT NULL,
  `alamat` text,
  `no_telepon` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `faskes` varchar(100) DEFAULT NULL,
  `kelas_bpjs` varchar(20) DEFAULT NULL,
  `gaji_dilaporkan` decimal(15,2) DEFAULT '0.00',
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `segmen_peserta` varchar(10) DEFAULT 'PBI',
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `kelas_id` int DEFAULT NULL,
  `no_pendaftaran` varchar(20) DEFAULT NULL,
  `tanggal_daftar` date DEFAULT NULL,
  `tanggal_berlaku` date DEFAULT NULL,
  `tanggal_expired` date DEFAULT NULL,
  `metode_pembayaran` enum('transfer','kredit','debit','tunai') DEFAULT NULL,
  `nama_bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `no_kartu_kredit` varchar(50) DEFAULT NULL,
  `nama_kartu` varchar(100) DEFAULT NULL,
  `iuran_bulanan` decimal(10,2) DEFAULT NULL,
  `biaya_admin` decimal(10,2) DEFAULT NULL,
  `ppn` decimal(10,2) DEFAULT NULL,
  `total_pembayaran` decimal(10,2) DEFAULT NULL,
  `status_pembayaran` enum('pending','paid','failed','verified') DEFAULT 'pending',
  `bukti_pembayaran` varchar(225) DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `provinsi` varchar(50) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `peserta`
--

INSERT INTO `peserta` (`id`, `user_id`, `no_kartu`, `nik`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `no_telepon`, `email`, `faskes`, `kelas_bpjs`, `gaji_dilaporkan`, `status`, `segmen_peserta`, `foto`, `created_at`, `updated_at`, `kelas_id`, `no_pendaftaran`, `tanggal_daftar`, `tanggal_berlaku`, `tanggal_expired`, `metode_pembayaran`, `nama_bank`, `no_rekening`, `no_kartu_kredit`, `nama_kartu`, `iuran_bulanan`, `biaya_admin`, `ppn`, `total_pembayaran`, `status_pembayaran`, `bukti_pembayaran`, `pekerjaan`, `provinsi`, `kota`) VALUES
(20, NULL, '0005546541034', '0076538967856456', 'Daniel Ajriya Permana', 'L', 'kebumen', '2007-06-14', 'paskem', '0808956487365', 'daniel@gmail.com', 'Rs Kebon Jeruk', 'Kelas 2', '5000000.00', 'active', 'PPU', NULL, '2025-12-25 07:25:42', '2025-12-27 07:33:45', 2, 'REG202512253961', '2025-12-25', '2026-01-01', '2026-12-31', 'transfer', 'bca', '0807956877', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', 'bukti_pembayaran/BPJS_1766647542_6458.png', 'karyawan', 'banten', 'tangerang'),
(21, NULL, '0007609692218', '8767876543567654', 'Eka Dwi Rohmadhoni', 'P', 'Tangerang', '2006-06-07', 'jatiuwung', '09876545675', 'eka@gmail.com', 'Rs Kebon Jeruk', 'Kelas 2', '4000000.00', 'active', 'PPU', NULL, '2025-12-27 06:53:33', '2025-12-27 06:54:08', 2, 'REG202512277779', '2025-12-27', '2026-01-01', '2026-12-31', 'tunai', '', '', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'staf administrasi', 'banten', 'tangerang'),
(22, NULL, '0007923397162', '8765676543212434', 'Louis Hasashi Halim', 'L', 'Jakarta ', '2007-06-07', 'Bayur', '0808956487367', 'louis@gmail.com', 'RSUP Persahabatan', 'Kelas 1', '10000000.00', 'active', 'PPU', NULL, '2025-12-27 07:41:26', '2025-12-27 07:42:15', 3, 'REG202512278397', '2025-12-27', '2026-01-01', '2026-12-31', 'transfer', 'BCA', '987676567', '', '0', '35000.00', '5000.00', '3500.00', '43500.00', 'verified', '', 'staf administrasi', 'banten', 'tangerang'),
(23, NULL, '0006707841823', '6547638765467587', 'Fauzi Nurrohman', 'L', 'Pandeglang', '2006-07-06', 'Pasar Kemis', '089614658763', 'fauzi@gmail.com', 'puskesmas', 'Kelas 3', '3000000.00', 'active', 'PPU', NULL, '2025-12-27 07:45:22', '2025-12-27 07:46:09', 1, 'REG202512279668', '2025-12-27', '2025-01-01', '2025-12-31', 'tunai', '', '', '', '0', '80000.00', '5000.00', '8000.00', '93000.00', 'verified', '', 'Ustad', 'banten', 'tangerang'),
(24, NULL, '0002722741225', '0076538967856457', 'Rayhan Rafif Adi Pratama', 'L', 'Yogyakarta', '2005-06-07', 'Tangerang Selatan', '0808956756347', 'rayhan@gmail.com', 'Rumah Sakit ', 'Kelas 2', '8000000.00', 'active', 'PPU', NULL, '2025-12-27 07:53:16', '2025-12-27 07:53:56', 2, 'REG202512270909', '2025-12-27', '2026-01-01', '2026-12-31', 'transfer', 'BTN', '987676567', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'Audit Keuangan', 'banten', 'tangerang Selatan'),
(26, NULL, '0006272679970', '0076538967856458', 'Fachrul Hannan Wily Salim', 'L', 'Pati', '2025-12-15', 'Kroncong', '089614658763', 'fachrul@gmail.com', 'puskesmas', 'Kelas 3', '5000000.00', 'active', 'PPU', NULL, '2025-12-27 07:59:11', '2025-12-27 07:59:53', 1, 'REG202512279915', '2025-12-27', '2025-01-01', '2025-12-31', 'tunai', '', '', '', '0', '80000.00', '5000.00', '8000.00', '93000.00', 'verified', '', 'ngoding', 'banten', 'tangerang'),
(28, NULL, '0007471170045', '8667465376456783', 'Nazwa Fauziah', 'P', 'Yogyakarta', '2020-06-11', 'Rajeg', '0808956487368', 'fauzi@gmail.com', 'puskesmas', 'Kelas 2', '5000000.00', 'active', 'PPU', NULL, '2025-12-27 08:09:08', '2026-01-05 12:41:58', 2, 'REG202512273008', '2025-12-27', '2025-01-01', '2025-12-31', 'tunai', '', '', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'pengusaha', 'banten', 'tangerang'),
(50, NULL, '0009610586024', '5426537647653654', 'Nathania Reva Kusuma', 'P', 'Rajeg', '2006-07-13', 'Sukatani', '089567456354', 'nathania@gmail.com', 'Rumah Sakit ', 'Kelas 2', '7000000.00', 'active', 'PPU', NULL, '2025-12-28 03:36:36', '2025-12-28 23:43:29', 2, 'REG202512287526', '2025-12-28', '2026-01-01', '2026-12-31', 'tunai', '', '', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'pengusaha', 'banten', 'tangerang'),
(52, NULL, '0005348852062', '0076538967856487', 'Marsya Audia', 'P', 'Rajeg', '2006-06-14', 'Rajeg', '0808956756376', 'marsya@gmail.com', 'Rumah Sakit ', 'Kelas 2', '4000000.00', 'active', 'PPU', NULL, '2025-12-28 23:40:43', '2025-12-28 23:41:39', 2, 'REG202512297614', '2025-12-29', '2025-01-01', '2025-12-31', 'tunai', '', '', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'Guru', 'banten', 'tangerang'),
(55, NULL, '0009309842077', '0076538967856564', 'Annisa Zahra Salsabila', 'P', 'Jatiuwung', '2005-01-06', 'Cikupa', '089765456765', 'anisa@gmail.com', 'Puskemas Central', 'Kelas 2', '5000000.00', 'active', 'PPU', NULL, '2026-01-06 00:34:44', '2026-01-06 00:35:38', 2, 'REG202601061428', '2026-01-06', '2026-01-01', '2026-12-31', 'tunai', '', '', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'Karyawan Kantor', 'Banten', 'tangerang'),
(57, NULL, '0005681498601', '0076538967856657', 'NOva Teguh', 'L', 'Cikupa', '2023-03-08', 'Bugel', '0808956487543', 'nova@gmail.com', 'rs umum daerah', 'Kelas 2', '0.00', 'active', 'PBI', NULL, '2026-01-06 01:45:58', '2026-01-06 01:46:48', 2, 'REG202601064341', '2026-01-06', '2026-01-01', '2026-12-31', 'tunai', '', '', '', '0', '51000.00', '5000.00', '5100.00', '61100.00', 'verified', '', 'dosen', 'banten', 'Tangerang');

-- --------------------------------------------------------

--
-- Table structure for table `poli`
--

CREATE TABLE `poli` (
  `id` int NOT NULL,
  `kode_poli` varchar(20) NOT NULL,
  `nama_poli` varchar(100) NOT NULL,
  `lokasi` varchar(200) NOT NULL,
  `kapasitas` int NOT NULL,
  `fasilitas` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `poli`
--

INSERT INTO `poli` (`id`, `kode_poli`, `nama_poli`, `lokasi`, `kapasitas`, `fasilitas`) VALUES
(1, 'POL001', 'Poli Umum', 'Lantai 1, Gedung A', 50, 'Ruang tunggu, AC, 5 ruang konsultasi'),
(2, 'POL002', 'Poli Gigi', 'Lantai 1, Gedung B', 30, 'Kursi gigi, alat sterilisasi, rontgen'),
(3, 'POL003', 'Poli Anak', 'Lantai 2, Gedung A', 40, 'Ruang bermain, ruang menyusui, 4 ruang konsultasi'),
(4, 'POL004', 'Poli Kandungan', 'Lantai 2, Gedung B', 25, 'USG, ruang bersalin, ruang pemeriksaan'),
(5, 'POL005', 'Poli Bedah', 'Lantai 3, Gedung A', 20, 'Ruang operasi minor, ruang perawatan'),
(6, 'POL006', 'Poli Penyakit Dalam', 'Lantai 3, Gedung B', 35, 'EKG, laboratorium mini, 3 ruang konsultasi');

-- --------------------------------------------------------

--
-- Table structure for table `rekening_admin`
--

CREATE TABLE `rekening_admin` (
  `id` int NOT NULL,
  `metode_pembayaran` int NOT NULL,
  `nama_bank` varchar(50) NOT NULL,
  `no_rekening` varchar(50) NOT NULL,
  `atas_nama` varchar(100) NOT NULL,
  `cabang` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rekening_admin`
--

INSERT INTO `rekening_admin` (`id`, `metode_pembayaran`, `nama_bank`, `no_rekening`, `atas_nama`, `cabang`) VALUES
(1, 1, 'Bank Central Asia', '1234567890', 'Organisasi Kita Sejahtera', 'BCA Cabang Sudirman Jakarta'),
(2, 2, 'Bank Mandiri', '0987654321', 'Yayasan Organisasi Kita', 'Mandiri Cabang Thamrin'),
(3, 3, 'Bank Rakyat Indonesia', '1122334455', 'PT Organisasi Kita', 'BRI Cabang Gatot Subroto'),
(4, 4, 'Bank Negara Indonesia', '5566778899', 'Organisasi Kita Indonesia', 'BNI Cabang Kuningan'),
(5, 11, 'CIMB Niaga', '6677889900', 'Komunitas Organisasi Kita', 'CIMB Cabang Senayan'),
(6, 13, 'Bank Panin', '7788990011', 'Lembaga Organisasi Kita', 'Panin Cabang Mega Kuningan'),
(7, 15, 'Bank Syariah Indonesia', '8899001122', 'Organisasi Kita Syariah', 'BSI Cabang Pondok Indah'),
(8, 2, 'Bank Mandiri', '1122334455', 'Organisasi Kita Pusat', 'Mandiri Cabang Bundaran HI'),
(9, 1, 'Bank Central Asia', '5566778899', 'Organisasi Kita Cabang Utara', 'BCA Cabang Kelapa Gading'),
(10, 3, 'Bank Rakyat Indonesia', '6677889900', 'Organisasi Kita Cabang Selatan', 'BRI Cabang Cilandak'),
(11, 4, 'Bank Negara Indonesia', '7788990011', 'Organisasi Kita Cabang Barat', 'BNI Cabang Tomang'),
(12, 11, 'CIMB Niaga', '8899001122', 'Organisasi Kita Cabang Timur', 'CIMB Cabang Cempaka Putih'),
(13, 1, 'Bank Central Asia', '9900112233', 'Organisasi Kita Dana Pendidikan', 'BCA Cabang Tebet'),
(14, 2, 'Bank Mandiri', '0011223344', 'Organisasi Kita Dana Sosial', 'Mandiri Cabang Kebayoran');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_status_iuran`
--

CREATE TABLE `riwayat_status_iuran` (
  `id` int NOT NULL,
  `iuran_id` int NOT NULL,
  `status_lama` varchar(50) NOT NULL,
  `status_baru` varchar(50) NOT NULL,
  `perubahan_oleh` int DEFAULT NULL,
  `alasan_perubahan` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting_pembayaran`
--

CREATE TABLE `setting_pembayaran` (
  `id` int NOT NULL,
  `nama_setting` varchar(100) NOT NULL,
  `nilai_setting` varchar(225) NOT NULL,
  `keterangan` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spesialisasi_dokter`
--

CREATE TABLE `spesialisasi_dokter` (
  `id` int NOT NULL,
  `kode_spesialisasi` varchar(20) NOT NULL,
  `nama_spesialisasi` varchar(100) NOT NULL,
  `deskripsi` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spesialisasi_dokter`
--

INSERT INTO `spesialisasi_dokter` (`id`, `kode_spesialisasi`, `nama_spesialisasi`, `deskripsi`) VALUES
(1, 'SP001', 'Dokter Umum', 'Praktisi kesehatan umum'),
(2, 'SP002', 'Dokter Gigi', 'Spesialis kesehatan gigi dan mulut'),
(3, 'SP003', 'Dokter Anak', 'Spesialis kesehatan anak'),
(4, 'SP004', 'Dokter Kandungan', 'Spesialis kebidanan dan kandungan'),
(5, 'SP005', 'Dokter Bedah', 'Spesialis operasi dan bedah'),
(6, 'SP006', 'Dokter Penyakit Dalam', 'Spesialis penyakit dalam'),
(7, 'SP007', 'Dokter Kulit', 'Spesialis kulit dan kelamin'),
(8, 'SP008', 'Dokter Mata', 'Spesialis kesehatan mata'),
(9, 'SP009', 'Dokter THT', 'Spesialis telinga, hidung, dan tenggorokan'),
(10, 'SP010', 'Dokter Jantung', 'Spesialis jantung dan pembuluh darah');

-- --------------------------------------------------------

--
-- Table structure for table `tindakan`
--

CREATE TABLE `tindakan` (
  `id` int NOT NULL,
  `kode_tindakan` varchar(20) NOT NULL,
  `nama_tindakan` varchar(200) NOT NULL,
  `deskripsi` text NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `tarif_bpjs` decimal(15,2) NOT NULL,
  `tarif_non_bpjs` decimal(15,2) NOT NULL,
  `jenis_tindakan` enum('medis','bedah','diagnostik','terapi','rehabilitasi','Rawat Jalan','Rawat Inap','IGD','Laboratorium','Radiologi','Fisioterapi') NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `waktu_estimasi` int DEFAULT NULL,
  `persyaratan` text NOT NULL,
  `catatan` text,
  `status` enum('aktif','tidak aktif') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tindakan`
--

INSERT INTO `tindakan` (`id`, `kode_tindakan`, `nama_tindakan`, `deskripsi`, `kategori`, `tarif_bpjs`, `tarif_non_bpjs`, `jenis_tindakan`, `unit`, `waktu_estimasi`, `persyaratan`, `catatan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TDK001', 'Konsultasi Dokter Umum', 'Konsultasi dan pemeriksaan umum', 'Konsultasi', '35000.00', '75000.00', 'Rawat Jalan', 'Poliklinik Umum', 15, 'Kartu peserta, Surat rujukan (jika ada)', '', 'aktif', '2025-12-20 03:19:05', '2025-12-23 07:12:52'),
(2, 'TDK002', 'Konsultasi Dokter Spesialis', 'Konsultasi dengan dokter spesialis', 'Konsultasi', '75000.00', '200000.00', 'Rawat Jalan', 'Poliklinik Spesialis', 20, 'Kartu peserta, Surat rujukan dari dokter umum', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 05:25:37'),
(3, 'TDK003', 'Pemeriksaan Darah Lengkap', 'Pemeriksaan darah lengkap', 'Laboratorium', '125000.00', '250000.00', 'Laboratorium', 'Laboratorium', 120, 'Puasa 8-10 jam sebelum pemeriksaan', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 03:52:36'),
(4, 'TDK004', 'Rontgen Thorax', 'Foto rontgen dada', 'Radiologi', '150000.00', '300000.00', 'Radiologi', 'Radiologi', 60, 'Melepas perhiasan logam', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 05:25:19'),
(5, 'TDK005', 'USG Abdomen', 'Ultrasonografi perut lengkap', 'Radiologi', '250000.00', '500000.00', 'Radiologi', 'Radiologi', 45, 'Puasa 6 jam sebelum pemeriksaan', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 03:19:05'),
(6, 'TDK006', 'Jahit Luka', 'Penjahitan luka berat', 'Tindakan Minor', '100000.00', '300000.00', 'IGD', 'IGD', 30, 'Tidak ada alergi terhadap anestesi lokal', '', 'aktif', '2025-12-20 03:19:05', '2025-12-23 07:13:50'),
(7, 'TDK007', 'Infus', 'Pemasangan infus dan cairan', 'Tindakan Minor', '50000.00', '100000.00', 'IGD', 'IGD', 15, 'Tidak ada kontraindikasi', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 03:19:05'),
(8, 'TDK008', 'Fisioterapi', 'Terapi fisik untuk rehabilitasi', 'Fisioterapi', '10000.00', '100000.00', 'Fisioterapi', 'Fisioterapi', 45, 'Surat rujukan dari dokter', '', 'aktif', '2025-12-20 03:19:05', '2025-12-22 17:09:15'),
(9, 'TDK009', 'EKG', 'Elektrokardiogram', 'Diagnostik', '100000.00', '200000.00', 'Rawat Jalan', 'Kardiologi', 20, 'Tidak ada', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 03:19:05'),
(10, 'TDK010', 'Pemeriksaan Mata', 'Pemeriksaan lengkap mata', 'Konsultasi', '80000.00', '175000.00', 'Rawat Jalan', 'Mata', 25, 'Tidak menggunakan lensa kontak', NULL, 'aktif', '2025-12-20 03:19:05', '2025-12-20 03:19:05'),
(11, 'TDK011', 'Operasi', 'Operasi Usus Buntu', 'Operasi', '350000.00', '25000000.00', 'IGD', 'IGD', 120, 'bpjs', '', 'aktif', '2025-12-20 04:30:07', '2025-12-24 18:06:48'),
(12, 'TDK012', 'Periksa Mata', 'Mata Minus min 5', 'Fisioterapi', '50000.00', '400000.00', 'Fisioterapi', 'Mata', 60, 'make kacamata', '', 'tidak aktif', '2025-12-20 05:04:18', '2025-12-24 18:06:04'),
(13, 'TDK013', 'Maag', 'Nyeri Di ulu Hati', 'Fisioterapi', '100000.00', '100000.00', 'Laboratorium', 'Fisioterapi', 30, '', NULL, 'aktif', '2025-12-29 12:36:41', '2025-12-29 12:36:41'),
(14, 'TDK014', 'Operasi Usus', 'Ambien', 'Operasi', '4000000.00', '6000000.00', 'IGD', 'IGD', 120, '', '', 'aktif', '2026-01-06 01:14:57', '2026-01-06 01:15:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `profile_pic` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `phone`, `full_name`, `created_at`, `last_login`, `is_active`, `profile_pic`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'admin@example.com', NULL, 'Admin', '2025-12-15 07:13:37', '2025-12-19 18:13:57', 1, 'default.png'),
(2, 'user1', '482c811da5d5b4bc6d497ffa98491e38', 'user1@example.com', NULL, 'John Doe', '2025-12-15 07:13:37', '2025-12-26 19:35:31', 1, 'default.png'),
(3, 'testuser', 'e10adc3949ba59abbe56e057f20f883e', 'test@example.com', NULL, NULL, '2025-12-15 07:13:37', NULL, 1, 'default.png'),
(5, 'Bunaya', 'f4f380295e2ab79c65385ebb87406f3f', 'bunaya@gmail.com', '08951495467', 'Bunaya Ardik Saputra', '2025-12-15 07:25:07', '2026-01-06 01:56:22', 1, 'profile_5_1767663513.jpeg'),
(6, 'fakhrul', '648e66c533b564404617f5b26c1c6ddb', 'fakhrul@gmail.com', NULL, 'fakhrul', '2025-12-15 15:53:07', NULL, 1, 'default.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `denda`
--
ALTER TABLE `denda`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dokter`
--
ALTER TABLE `dokter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_dokter` (`kode_dokter`),
  ADD KEY `spesialisasi_id` (`spesialisasi_id`);

--
-- Indexes for table `faskes`
--
ALTER TABLE `faskes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jadwal_dokter`
--
ALTER TABLE `jadwal_dokter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `klaim`
--
ALTER TABLE `klaim`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kunjungan`
--
ALTER TABLE `kunjungan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_peserta` (`peserta_id`),
  ADD KEY `idx_tanggal` (`tanggal_kunjungan`),
  ADD KEY `idx_faskes` (`faskes_id`),
  ADD KEY `idx_dokter` (`dokter_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `log_activity`
--
ALTER TABLE `log_activity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `obat`
--
ALTER TABLE `obat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pembayaran_bpjs`
--
ALTER TABLE `pembayaran_bpjs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `peserta_id` (`peserta_id`);

--
-- Indexes for table `peserta`
--
ALTER TABLE `peserta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_kartu` (`no_kartu`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_peserta_kelas` (`kelas_id`);

--
-- Indexes for table `poli`
--
ALTER TABLE `poli`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rekening_admin`
--
ALTER TABLE `rekening_admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `riwayat_status_iuran`
--
ALTER TABLE `riwayat_status_iuran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting_pembayaran`
--
ALTER TABLE `setting_pembayaran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `spesialisasi_dokter`
--
ALTER TABLE `spesialisasi_dokter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tindakan`
--
ALTER TABLE `tindakan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `denda`
--
ALTER TABLE `denda`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dokter`
--
ALTER TABLE `dokter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `faskes`
--
ALTER TABLE `faskes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jadwal_dokter`
--
ALTER TABLE `jadwal_dokter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `klaim`
--
ALTER TABLE `klaim`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `kunjungan`
--
ALTER TABLE `kunjungan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `log_activity`
--
ALTER TABLE `log_activity`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `obat`
--
ALTER TABLE `obat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pembayaran_bpjs`
--
ALTER TABLE `pembayaran_bpjs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `peserta`
--
ALTER TABLE `peserta`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `poli`
--
ALTER TABLE `poli`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rekening_admin`
--
ALTER TABLE `rekening_admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `riwayat_status_iuran`
--
ALTER TABLE `riwayat_status_iuran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting_pembayaran`
--
ALTER TABLE `setting_pembayaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spesialisasi_dokter`
--
ALTER TABLE `spesialisasi_dokter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tindakan`
--
ALTER TABLE `tindakan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dokter`
--
ALTER TABLE `dokter`
  ADD CONSTRAINT `dokter_ibfk_1` FOREIGN KEY (`spesialisasi_id`) REFERENCES `spesialisasi_dokter` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pembayaran_bpjs`
--
ALTER TABLE `pembayaran_bpjs`
  ADD CONSTRAINT `pembayaran_bpjs_ibfk_1` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `peserta`
--
ALTER TABLE `peserta`
  ADD CONSTRAINT `fk_peserta_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `peserta_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
