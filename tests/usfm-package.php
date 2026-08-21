<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use FeedMySheep\Bible\UsfmPackage;

$fixtureDirectory = __DIR__ . '/fixtures/usfm-package';
$archive = tempnam(sys_get_temp_dir(), 'usfm-package-');
if ($archive === false) throw new RuntimeException('Could not create the test archive.');

$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not open the test archive.');
foreach (['00-FRT.usfm', '01-GEN.usfm', '02-EXO.usfm'] as $filename) {
    $zip->addFile($fixtureDirectory . '/' . $filename, $filename);
}
$zip->close();

$manifest = [
    'source_identifier' => 'fixture',
    'book_codes' => ['GEN', 'EXO'],
    'package' => ['sha256' => hash_file('sha256', $archive)],
];

try {
    $report = (new UsfmPackage($archive, $manifest))->inspect();
    assert(array_keys($report['books']) === ['EXO', 'GEN']);
    assert(array_column($report['entries'], 'name') === ['00-FRT.usfm', '01-GEN.usfm', '02-EXO.usfm']);
    assert($report['summary']['entries'] === 3);
    assert($report['summary']['books'] === 2);
    assert($report['books']['GEN']['chapters'][1][1]['text'] === 'In the beginning.');
    assert($report['books']['EXO']['chapters'][1][1]['text'] === 'These are the names.');

    $mappedManifest = $manifest;
    $mappedManifest['book_codes'] = ['GEN', 'DAN'];
    $mappedManifest['book_code_map'] = ['DAG' => 'DAN'];
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Could not reopen the test archive.');
    $zip->deleteName('02-EXO.usfm');
    $zip->addFromString('02-DAG.usfm', "\\id DAG Daniel with additions\n\\c 1\n\\v 1 A mapped verse.\n");
    $zip->close();
    $mappedManifest['package']['sha256'] = hash_file('sha256', $archive);
    $mappedReport = (new UsfmPackage($archive, $mappedManifest))->inspect();
    assert(array_keys($mappedReport['books']) === ['DAN', 'GEN']);
    assert($mappedReport['books']['DAN']['provider_code'] === 'DAG');

    $mappedManifest['empty_verse_policy'] = 'omit';
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Could not reopen the test archive.');
    $zip->deleteName('02-DAG.usfm');
    $zip->addFromString('02-DAG.usfm', "\\id DAG Daniel with additions\n\\c 1\n\\v 1 \\f + \\ft An omitted verse.\\f*\n\\v 2 A retained verse.\n");
    $zip->close();
    $mappedManifest['package']['sha256'] = hash_file('sha256', $archive);
    $mappedReport = (new UsfmPackage($archive, $mappedManifest))->inspect();
    assert(array_keys($mappedReport['books']['DAN']['chapters'][1]) === [2]);

    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Could not reopen the test archive.');
    $zip->deleteName('02-DAG.usfm');
    $zip->addFile($fixtureDirectory . '/02-EXO.usfm', '02-EXO.usfm');
    $zip->close();
    $manifest['package']['sha256'] = hash_file('sha256', $archive);

    $incompleteManifest = $manifest;
    $incompleteManifest['book_codes'][] = 'LEV';
    try {
        (new UsfmPackage($archive, $incompleteManifest))->inspect();
        throw new RuntimeException('An incomplete manifest canon was accepted.');
    } catch (RuntimeException $exception) {
        assert(str_contains($exception->getMessage(), 'Missing: LEV'));
    }

    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Could not reopen the test archive.');
    $zip->addFromString('03-TOB.usfm', "\\id TOB Tobit\n\\c 1\n\\v 1 An explicitly excluded book.\n");
    $zip->close();
    $manifest['package']['sha256'] = hash_file('sha256', $archive);
    try {
        (new UsfmPackage($archive, $manifest))->inspect();
        throw new RuntimeException('A chapter-bearing non-canonical identifier was accepted.');
    } catch (RuntimeException $exception) {
        assert(str_contains($exception->getMessage(), 'Unexpected chapter-bearing USFM identifier TOB'));
    }

    $manifest['excluded_book_codes'] = ['TOB'];
    $report = (new UsfmPackage($archive, $manifest))->inspect();
    assert(array_keys($report['books']) === ['EXO', 'GEN']);
    assert(array_column($report['entries'], 'name') === ['00-FRT.usfm', '01-GEN.usfm', '02-EXO.usfm', '03-TOB.usfm']);
} finally {
    unlink($archive);
}

echo "USFM package tests passed\n";
