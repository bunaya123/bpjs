<?php
// File untuk insert data contoh kunjungan
// Ambil user_id
$user_id = $_SESSION['user_id'] ?? 1;

// Data contoh kunjungan
$kunjungan_data = [
    // Peserta 1 - M Reza Faurizki
    [
        'peserta_id' => 1,
        'faskes_id' => 1,
        'dokter_id' => 1,
        'tanggal_kunjungan' => '2025-12-01',
        'jam_kunjungan' => '08:30:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Penyakit Dalam',
        'diagnosis' => 'Hipertensi Grade 1',
        'keluhan' => 'Sakit kepala, pusing, tekanan darah tinggi',
        'status' => 'selesai',
        'biaya_administrasi' => 25000.00
    ],
    // Peserta 2 - Revaliza Maheppy
    [
        'peserta_id' => 2,
        'faskes_id' => 2,
        'dokter_id' => 2,
        'tanggal_kunjungan' => '2025-12-03',
        'jam_kunjungan' => '09:00:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Kebidanan',
        'diagnosis' => 'Kehamilan Trimester 2',
        'keluhan' => 'Kontrol kehamilan rutin',
        'status' => 'selesai',
        'biaya_administrasi' => 30000.00
    ],
    // Peserta 5 - Nazwa Fauziah
    [
        'peserta_id' => 5,
        'faskes_id' => 1,
        'dokter_id' => 1,
        'tanggal_kunjungan' => '2025-12-08',
        'jam_kunjungan' => '11:15:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Umum',
        'diagnosis' => 'Demam dan Batuk',
        'keluhan' => 'Demam tinggi dan batuk berdahak',
        'status' => 'selesai',
        'biaya_administrasi' => 15000.00
    ],
    // Peserta 7 - Kartika Melodya
    [
        'peserta_id' => 7,
        'faskes_id' => 6,
        'dokter_id' => 9,
        'tanggal_kunjungan' => '2025-12-11',
        'jam_kunjungan' => '09:45:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Umum',
        'diagnosis' => 'Flu dan Sakit Tenggorokan',
        'keluhan' => 'Badai flu dan sakit saat menelan',
        'status' => 'selesai',
        'biaya_administrasi' => 20000.00
    ],
    // Peserta 11 - Louis Hasashi Halim
    [
        'peserta_id' => 11,
        'faskes_id' => 3,
        'dokter_id' => 3,
        'tanggal_kunjungan' => '2025-12-15',
        'jam_kunjungan' => '13:15:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Gigi',
        'diagnosis' => 'Karies Gigi',
        'keluhan' => 'Sakit gigi berlubang',
        'status' => 'selesai',
        'biaya_administrasi' => 35000.00
    ],
    // Kunjungan hari ini
    [
        'peserta_id' => 1,
        'faskes_id' => 1,
        'dokter_id' => 1,
        'tanggal_kunjungan' => date('Y-m-d'),
        'jam_kunjungan' => '08:00:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Penyakit Dalam',
        'diagnosis' => 'Kontrol Rutin',
        'keluhan' => 'Kontrol tekanan darah',
        'status' => 'terdaftar',
        'biaya_administrasi' => 20000.00
    ],
    [
        'peserta_id' => 2,
        'faskes_id' => 2,
        'dokter_id' => 2,
        'tanggal_kunjungan' => date('Y-m-d'),
        'jam_kunjungan' => '10:30:00',
        'jenis_pelayanan' => 'rawat_jalan',
        'poli' => 'Kebidanan',
        'diagnosis' => 'USG Kehamilan',
        'keluhan' => 'Pemeriksaan kehamilan minggu ke-20',
        'status' => 'diproses',
        'biaya_administrasi' => 45000.00
    ]
];

// Insert data
foreach ($kunjungan_data as $data) {
    $sql = "INSERT INTO kunjungan 
            (peserta_id, faskes_id, dokter_id, tanggal_kunjungan, 
            jam_kunjungan, jenis_pelayanan, poli, diagnosis, keluhan, 
            status, biaya_administrasi, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiisssssssdi", 
        $data['peserta_id'], $data['faskes_id'], $data['dokter_id'],
        $data['tanggal_kunjungan'], $data['jam_kunjungan'], $data['jenis_pelayanan'],
        $data['poli'], $data['diagnosis'], $data['keluhan'], $data['status'],
        $data['biaya_administrasi'], $user_id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
?>