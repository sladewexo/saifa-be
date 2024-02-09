<?php

function appendToCsv($filename, $data, $delimiter = ',')
{
    //$fileExists = file_exists($filename) && filesize($filename) > 0;
    if (($handle = fopen($filename, 'a')) !== FALSE) {
//        if ($fileExists) {
//            fwrite($handle, "\n");
//        }
        fputcsv($handle, $data, $delimiter);
        fclose($handle);
    } else {
        // Handle error when file can't be opened
        echo "Cannot open the file ($filename)";
    }
}

function updateMigration(int $version, string $name)
{
    $csvFile = './migration.csv';
    $newRow = [$version, $name, date('Y-m-d H:i:s')]; // New row to be added
    appendToCsv($csvFile, $newRow);
}

function checkRunMigration(int $versionNumber): bool
{
    try {
        $csvFile = './migration.csv';
        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            exit('File not found or is not readable');
        }
        $lastRow = null;

        if (($handle = fopen($csvFile, 'r')) !== false) {
            // Loop through each row in the CSV file
            while (($row = fgetcsv($handle)) !== false) {
                // Assign the current row to lastRow
                $lastRow = $row;
            }
            // Close the file handle
            fclose($handle);
        }
        if (!is_null($lastRow)) {
            $lastVersionMigration = (int)$lastRow[0];
            if ($lastVersionMigration >= $versionNumber) return true;
        }
        return false;
    } catch (\Exception $e) {
        echo 'check checkRunMigration exception: ', $e->getMessage(), "\n";
        return false;
    }
}