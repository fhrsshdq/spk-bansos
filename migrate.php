<?php
$data = [];
if (($handle = fopen("data_warga.csv", "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($row) >= 7) {
            $data[] = [
                'nokk' => trim($row[0]),
                'nama' => trim($row[1]),
                'scores' => [
                    'C1' => (int)$row[2],
                    'C2' => (int)$row[3],
                    'C3' => (int)$row[4],
                    'C4' => (int)$row[5],
                    'C5' => (int)$row[6]
                ]
            ];
        }
    }
    fclose($handle);
}
file_put_contents('data_warga.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Migrated";
