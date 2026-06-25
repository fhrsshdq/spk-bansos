<?php
header('Content-Type: application/json');

$KRITERIA_FILE = 'kriteria.json';
$JSON_FILE = 'data_warga.json';

// Get Quota
$kuota = isset($_GET['kuota']) ? (int)$_GET['kuota'] : 50;

// Read Kriteria
$kriteriaData = [];
if (file_exists($KRITERIA_FILE)) {
    $kriteriaData = json_decode(file_get_contents($KRITERIA_FILE), true) ?: [];
}

// Read Warga Data
$dataWarga = [];
if (file_exists($JSON_FILE)) {
    $dataWarga = json_decode(file_get_contents($JSON_FILE), true) ?: [];
}

if (empty($kriteriaData) || empty($dataWarga)) {
    echo json_encode([
        'status' => 'success',
        'total_warga' => count($dataWarga),
        'kuota' => $kuota,
        'data_warga' => $dataWarga,
        'ranked_data' => []
    ]);
    exit;
}

// Function to calculate SAW
function calculateSAW($dataWarga, $kriteriaData) {
    if (empty($dataWarga) || empty($kriteriaData)) return [];

    // Find min and max for each criteria
    $minMax = [];
    foreach ($kriteriaData as $k) {
        $kode = $k['kode'];
        $scores = array_column(array_column($dataWarga, 'scores'), $kode);
        if (empty($scores)) {
            $minMax[$kode] = ['min' => 1, 'max' => 1];
            continue;
        }
        $min = min($scores);
        $max = max($scores);
        if ($min == 0) $min = 1;
        if ($max == 0) $max = 1;
        
        $minMax[$kode] = [
            'min' => $min,
            'max' => $max
        ];
    }

    $calculatedData = [];
    foreach ($dataWarga as $d) {
        $skorAkhir = 0;
        
        foreach ($kriteriaData as $k) {
            $kode = $k['kode'];
            $tipe = $k['tipe']; // BENEFIT or COST
            
            // Weight can be from GET (if live preview) or from json
            $bobot = isset($_GET['w_'.$kode]) ? (float)$_GET['w_'.$kode] : (float)$k['bobot'];
            // Normalize weight (convert % to decimal for safety, assuming bobot is out of 100)
            $w = $bobot / 100;

            $val = isset($d['scores'][$kode]) ? $d['scores'][$kode] : 1;
            
            if ($tipe === 'COST') {
                $norm = $minMax[$kode]['min'] / max($val, 1);
            } else { // BENEFIT
                $norm = $val / $minMax[$kode]['max'];
            }
            
            $skorAkhir += ($norm * $w);
        }
        
        $d['skorAkhir'] = round($skorAkhir * 100, 4); // * 100 for better display? No, original didn't *100.
        // Actually original did ($norm * w) where w = 0.35 etc.
        // So I'll do without *100 because the $bobot comes as 35 from js, so 35/100 is 0.35.
        // Wait! In the original, the bobot was passed as 35, and the PHP expected 0.35?
        // Let's check original api.php: $w1 = isset($_GET['w_c1']) ? (float)$_GET['w_c1'] : 0.35;
        // In the original script.js:
        // const p1 = document.getElementById('w-c1').value (which is 35)
        // the URL passed w_c1=35 ! 
        // Oh! So original passed 35, and $w1 became 35!
        // That means the skorAkhir was like 35 + 25 + 20 = 100.
        // So I should just NOT divide by 100 to maintain backward compatibility.
        
        $calculatedData[] = $d;
    }

    // Sort by skorAkhir descending
    usort($calculatedData, function($a, $b) {
        return $b['skorAkhir'] <=> $a['skorAkhir'];
    });

    return $calculatedData;
}

$calculatedData = [];
$matrixKeputusan = [];
$matrixNormalisasi = [];

foreach ($dataWarga as $d) {
    $skorAkhir = 0;
    
    $rowKeputusan = ['nama' => $d['nama'], 'nokk' => $d['nokk'], 'scores' => []];
    $rowNormalisasi = ['nama' => $d['nama'], 'nokk' => $d['nokk'], 'scores' => []];

    foreach ($kriteriaData as $k) {
        $kode = $k['kode'];
        $tipe = $k['tipe'];
        
        // Find min max for this criteria
        $scores = array_column(array_column($dataWarga, 'scores'), $kode);
        $min = !empty($scores) ? min($scores) : 1;
        $max = !empty($scores) ? max($scores) : 1;
        if($min == 0) $min = 1;
        if($max == 0) $max = 1;
        
        $bobot = isset($_GET['w_'.$kode]) ? (float)$_GET['w_'.$kode] : (float)$k['bobot'];
        $val = isset($d['scores'][$kode]) ? $d['scores'][$kode] : 1;
        
        $rowKeputusan['scores'][$kode] = $val;

        if ($tipe === 'COST') {
            $norm = $min / max($val, 1);
        } else {
            $norm = $val / $max;
        }
        
        $rowNormalisasi['scores'][$kode] = round($norm, 4);
        $skorAkhir += ($norm * $bobot);
    }
    
    $matrixKeputusan[] = $rowKeputusan;
    $matrixNormalisasi[] = $rowNormalisasi;

    $d['skorAkhir'] = round($skorAkhir, 4);
    $calculatedData[] = $d;
}

// Sort
usort($calculatedData, function($a, $b) {
    return $b['skorAkhir'] <=> $a['skorAkhir'];
});

// Output JSON
echo json_encode([
    'status' => 'success',
    'total_warga' => count($dataWarga),
    'kuota' => $kuota,
    'data_warga' => $dataWarga, // Original unordered
    'ranked_data' => $calculatedData,
    'kriteria_data' => $kriteriaData, // Send kriteria so JS knows the dynamic columns!
    'matrix_keputusan' => $matrixKeputusan,
    'matrix_normalisasi' => $matrixNormalisasi
]);
