<?php
$KRITERIA_FILE = 'kriteria.json';
$DATA_FILE = 'data_warga.json';

// New criteria list
$newKriteria = [
    ['kode' => 'C1', 'nama' => 'Penghasilan Bulanan', 'bobot' => 40, 'tipe' => 'COST'],
    ['kode' => 'C2', 'nama' => 'Jumlah Tanggungan', 'bobot' => 40, 'tipe' => 'BENEFIT'],
    ['kode' => 'C3', 'nama' => 'Status Pekerjaan', 'bobot' => 20, 'tipe' => 'BENEFIT']
];
file_put_contents($KRITERIA_FILE, json_encode($newKriteria, JSON_PRETTY_PRINT));

// Migrate data warga
if (file_exists($DATA_FILE)) {
    $data = json_decode(file_get_contents($DATA_FILE), true);
    if ($data) {
        foreach ($data as &$warga) {
            $newScores = [];
            $newRaw = [];
            
            // Map C1 and C2
            if (isset($warga['scores']['C1'])) $newScores['C1'] = $warga['scores']['C1'];
            else $newScores['C1'] = 1;
            
            if (isset($warga['scores']['C2'])) $newScores['C2'] = $warga['scores']['C2'];
            else $newScores['C2'] = 1;
            
            // Map old C5 to new C3
            if (isset($warga['scores']['C5'])) $newScores['C3'] = $warga['scores']['C5'];
            else $newScores['C3'] = 1;
            
            
            if (isset($warga['raw_data']['C1'])) $newRaw['C1'] = $warga['raw_data']['C1'];
            if (isset($warga['raw_data']['C2'])) $newRaw['C2'] = $warga['raw_data']['C2'];
            if (isset($warga['raw_data']['C5'])) $newRaw['C3'] = $warga['raw_data']['C5'];
            
            $warga['scores'] = $newScores;
            $warga['raw_data'] = $newRaw;
        }
        file_put_contents($DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
}
echo "Migration done";
?>
