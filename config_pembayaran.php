<?php
// Configuration untuk sistem pembayaran iuran

// Pengaturan umum
define('PAYMENT_SETTINGS', [
    'due_date_day' => 10, // Tanggal jatuh tempo setiap bulan
    'grace_period' => 7, // Masa tenggang setelah jatuh tempo (hari)
    'auto_generate' => true, // Auto generate iuran bulanan
    'notification' => true, // Aktifkan notifikasi
]);

// Tarif denda
define('LATE_PENALTIES', [
    [
        'min_days' => 1,
        'max_days' => 7,
        'percentage' => 0.5, // 0.5%
        'min_amount' => 5000,
        'max_amount' => 10000
    ],
    [
        'min_days' => 8,
        'max_days' => 14,
        'percentage' => 1, // 1%
        'min_amount' => 10000,
        'max_amount' => 20000
    ],
    [
        'min_days' => 15,
        'max_days' => 30,
        'percentage' => 2, // 2%
        'min_amount' => 20000,
        'max_amount' => 50000
    ],
    [
        'min_days' => 31,
        'max_days' => null, // unlimited
        'percentage' => 5, // 5%
        'min_amount' => 50000,
        'max_amount' => null
    ]
]);

// Biaya admin per metode
define('ADMIN_FEES', [
    'transfer' => 2500,
    'atm' => 2500,
    'mobile_banking' => 0,
    'cash' => 0,
    'debit_card' => 2500,
    'credit_card' => 5000
]);

// Rekening bank untuk transfer
define('BANK_ACCOUNTS', [
    'BCA' => [
        'bank' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'BPJS KESEHATAN',
        'branch' => 'KCU Sudirman Jakarta'
    ],
    'BNI' => [
        'bank' => 'BNI',
        'account_number' => '0987654321',
        'account_name' => 'BPJS KESEHATAN',
        'branch' => 'KCU Thamrin Jakarta'
    ],
    'Mandiri' => [
        'bank' => 'Mandiri',
        'account_number' => '1122334455',
        'account_name' => 'BPJS KESEHATAN',
        'branch' => 'KCU Gatot Subroto'
    ],
    'BRI' => [
        'bank' => 'BRI',
        'account_number' => '5544332211',
        'account_name' => 'BPJS KESEHATAN',
        'branch' => 'KCU Kuningan Jakarta'
    ]
]);

// Status pembayaran
define('PAYMENT_STATUS', [
    'pending' => 'Pending',
    'processing' => 'Diproses',
    'success' => 'Berhasil',
    'failed' => 'Gagal',
    'expired' => 'Kadaluarsa'
]);

// Status iuran
define('PREMIUM_STATUS', [
    'unpaid' => 'Belum Bayar',
    'paid' => 'Lunas',
    'overdue' => 'Lewat Jatuh Tempo',
    'suspended' => 'Ditangguhkan'
]);

// Fungsi helper untuk pembayaran
function calculateLateFee($amount, $days_late) {
    foreach (LATE_PENALTIES as $penalty) {
        if ($days_late >= $penalty['min_days'] && 
            ($penalty['max_days'] === null || $days_late <= $penalty['max_days'])) {
            
            $fee = $amount * ($penalty['percentage'] / 100);
            
            if ($penalty['min_amount'] !== null && $fee < $penalty['min_amount']) {
                $fee = $penalty['min_amount'];
            }
            if ($penalty['max_amount'] !== null && $fee > $penalty['max_amount']) {
                $fee = $penalty['max_amount'];
            }
            
            return $fee;
        }
    }
    return 0;
}

function generateReferenceNumber($prefix = 'REF') {
    return $prefix . date('YmdHis') . rand(100, 999);
}

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getPaymentMethodName($code) {
    $methods = [
        'TF-BCA' => 'Transfer BCA',
        'TF-BNI' => 'Transfer BNI',
        'TF-MDR' => 'Transfer Mandiri',
        'TF-BRI' => 'Transfer BRI',
        'ATM' => 'ATM',
        'M-BCA' => 'Mobile Banking BCA',
        'M-MDR' => 'Mobile Banking Mandiri',
        'OVO' => 'OVO',
        'GO-PAY' => 'GoPay',
        'CASH' => 'Tunai',
        'DEBIT' => 'Kartu Debit',
        'KREDIT' => 'Kartu Kredit'
    ];
    
    return $methods[$code] ?? $code;
}

// Include ke config utama jika belum
if (!function_exists('getPaymentConfig')) {
    function getPaymentConfig($key) {
        return PAYMENT_SETTINGS[$key] ?? null;
    }
}