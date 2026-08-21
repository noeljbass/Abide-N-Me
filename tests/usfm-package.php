<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use FeedMySheep\Bible\UsfmPackage;

$fixtureDirectory = __DIR__ . '/fixtures/usfm-package';
$archive = tempnam(sys_get_temp_dir(), 'usfm-package-');
if ($archive === false) throw new RuntimeException('Could not create the test archive.');

$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not open the test archive.');
foreach (['00-FRT.usfm', '01-GEN.usfm'] as $filename) {
    $zip->addFile($fixtureDirectory . '/' . $filename, $filename);
}
$zip->close();

$manifest = [
    'source_identifier' => 'fixture',
    'book_codes' => ['GEN'],
    'package' => ['sha256' => hash_file('sha256', $archive)],
];

try {
    $report = (new UsfmPackage($archive, $manifest))->inspect();
    assert(array_keys($report['books']) === ['GEN']);
    assert(array_column($report['entries'], 'name') === ['00-FRT.usfm', '01-GEN.usfm']);
    assert($report['summary']['entries'] === 2);
    assert($report['summary']['books'] === 1);

    $incompleteManifest = $manifest;
    $incompleteManifest['book_codes'][] = 'EXO';
    try {
        (new UsfmPackage($archive, $incompleteManifest))->inspect();
        throw new RuntimeException('An incomplete manifest canon was accepted.');
    } catch (RuntimeException $exception) {
        assert(str_contains($exception->getMessage(), 'Missing: EXO'));
    }

    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Could not reopen the test archive.');
    $zip->addFromString('00-FRT.usfm', "\\id FRT Front matter\n\\c 1\n\\v 1 Unexpected chapter.\n");
    $zip->close();
    $manifest['package']['sha256'] = hash_file('sha256', $archive);
    try {
        (new UsfmPackage($archive, $manifest))->inspect();
        throw new RuntimeException('A chapter-bearing non-canonical identifier was accepted.');
    } catch (RuntimeException $exception) {
        assert(str_contains($exception->getMessage(), 'unexpected: FRT'));
    }
} finally {
    unlink($archive);
}

echo "USFM package tests passed\n";
