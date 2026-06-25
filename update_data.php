<?php
$file = 'data_warga.json';
$data = json_decode(file_get_contents($file), true);

foreach($data as &$row) {
    if (empty($row['raw_data'])) {
        $row['raw_data'] = ['C1'=>'', 'C2'=>'', 'C3'=>''];
        
        $s1 = isset($row['scores']['C1']) ? $row['scores']['C1'] : 0;
        if ($s1==5) $row['raw_data']['C1'] = '< Rp 500.000';
        elseif ($s1==4) $row['raw_data']['C1'] = 'Rp 500.000 - Rp 1.000.000';
        elseif ($s1==3) $row['raw_data']['C1'] = 'Rp 1.000.000 - Rp 2.000.000';
        elseif ($s1==2) $row['raw_data']['C1'] = 'Rp 2.000.000 - Rp 3.000.000';
        elseif ($s1==1) $row['raw_data']['C1'] = '> Rp 3.000.000';

        $s2 = isset($row['scores']['C2']) ? $row['scores']['C2'] : 0;
        if ($s2==5) $row['raw_data']['C2'] = '> 5 Orang';
        elseif ($s2==4) $row['raw_data']['C2'] = '4 Orang';
        elseif ($s2==3) $row['raw_data']['C2'] = '3 Orang';
        elseif ($s2==2) $row['raw_data']['C2'] = '2 Orang';
        elseif ($s2==1) $row['raw_data']['C2'] = '1 Orang';

        $s3 = isset($row['scores']['C3']) ? $row['scores']['C3'] : 0;
        if ($s3==5) $row['raw_data']['C3'] = 'Tidak Bekerja';
        elseif ($s3==4) $row['raw_data']['C3'] = 'Buruh Harian';
        elseif ($s3==3) $row['raw_data']['C3'] = 'Wiraswasta';
        elseif ($s3==2) $row['raw_data']['C3'] = 'Karyawan Swasta';
        elseif ($s3==1) $row['raw_data']['C3'] = 'PNS / TNI / Polri';
    }
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
echo "Done";
