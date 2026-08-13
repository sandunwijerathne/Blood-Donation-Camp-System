<?php
/**
 * Merge the per-page transcription fragments in parts/ into a single
 * import-ready CSV for the Donors > Import screen.
 *
 *   php merge-parts.php
 *
 * Writes donors-import.csv with a UTF-8 BOM (so Excel and
 * PhpSpreadsheet both read the Sinhala correctly), skips duplicate
 * T.P. numbers, and reports anything it dropped.
 */

$dir     = __DIR__;
$partsIn = $dir . '/parts';
$outFile = $dir . '/donors-import.csv';

$header = ['Name','Mobile','WhatsApp','Email','Address','Blood Group','Gender','Date Of Birth','Last Donation Date'];
$validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

$files = glob($partsIn . '/p*.csv');
sort($files, SORT_NATURAL);

if (!$files) {
    fwrite(STDERR, "No fragments found in $partsIn\n");
    exit(1);
}

$missingFile = $dir . '/donors-missing-bloodgroup.csv';

$rows    = [];   // complete - ready to import
$missing = [];   // everything except a blood group
$seen    = [];
$dupes   = 0;
$bad     = 0;

foreach ($files as $file) {
    $fh = fopen($file, 'r');
    if (!$fh) continue;

    while (($row = fgetcsv($fh)) !== false) {
        // Skip blank lines
        if ($row === [null] || count($row) < 6) continue;

        $name   = trim($row[0] ?? '');
        $mobile = preg_replace('/\D+/', '', trim($row[1] ?? ''));
        $group  = strtoupper(trim($row[5] ?? ''));

        // A name and a usable T.P. are the minimum - the T.P. is the
        // unique key, so a row without one cannot be imported at all.
        if ($name === '' || strlen($mobile) !== 10) {
            $bad++;
            fwrite(STDERR, "  dropped (" . basename($file) . "): $name / $mobile\n");
            continue;
        }

        // The same person donates at several camps, so the same T.P.
        // shows up in more than one book. Keep one record per person,
        // merging in whichever version has the most detail and the most
        // recent donation date.
        if (isset($seen[$mobile])) {
            $dupes++;
            $seen[$mobile] = mergeDonor($seen[$mobile], $row, $validGroups);
            continue;
        }

        $seen[$mobile] = $row;
    }

    fclose($fh);
}

/**
 * Combine two sightings of the same person into the fuller record.
 */
function mergeDonor(array $a, array $b, array $validGroups): array
{
    $out = $a;

    // Prefer a real blood group over a blank one.
    $aGroup = strtoupper(trim($a[5] ?? ''));
    $bGroup = strtoupper(trim($b[5] ?? ''));
    if (!in_array($aGroup, $validGroups, true) && in_array($bGroup, $validGroups, true)) {
        $out[5] = $b[5];
    }

    // Prefer the longer (more complete) name and address.
    foreach ([0, 4] as $i) {
        if (mb_strlen(trim($b[$i] ?? '')) > mb_strlen(trim($a[$i] ?? ''))) {
            $out[$i] = $b[$i];
        }
    }

    // Keep the most recent donation date.
    $aDate = trim($a[8] ?? '');
    $bDate = trim($b[8] ?? '');
    if ($bDate !== '' && ($aDate === '' || strtotime($bDate) > strtotime($aDate))) {
        $out[8] = $bDate;
    }

    return $out;
}

// Split the merged people into importable vs needs-a-blood-group.
foreach ($seen as $row) {
    $group = strtoupper(trim($row[5] ?? ''));
    if (in_array($group, $validGroups, true)) {
        $rows[] = $row;
    } else {
        $missing[] = $row;
    }
}

function writeCsv(string $path, array $header, array $rows): void
{
    $out = fopen($path, 'w');
    fwrite($out, "\xEF\xBB\xBF");      // UTF-8 BOM for Excel / PhpSpreadsheet
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
}

writeCsv($outFile, $header, $rows);
writeCsv($missingFile, $header, $missing);

echo "merged " . count($files) . " page fragments\n";
echo "ready to import : " . count($rows) . " donors -> " . basename($outFile) . "\n";
echo "no blood group  : " . count($missing) . " donors -> " . basename($missingFile) . "\n";
echo "skipped         : $dupes duplicate T.P., $bad unusable rows\n";
