<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HRIS Application Settings
    |--------------------------------------------------------------------------
    */

    // Durasi penyimpanan foto selfie absensi dalam bulan.
    // Foto yang lebih lama dari periode ini akan dihapus otomatis.
    'selfie_retention_period_months' => 2,

    // Batas jumlah record absensi yang diproses per batch dalam command penghapusan selfie.
    // Berguna untuk mencegah timeout pada data yang sangat besar.
    'selfie_deletion_batch_size' => 200,


    // ... konfigurasi lain ...
    'admin_can_attend' => true, // atau false

];
