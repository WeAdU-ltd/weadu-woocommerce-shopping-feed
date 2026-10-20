<?php

require __DIR__ . '/FeedAudit.php';

$fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures.json'), true);
$failed = 0;

function fail($name, $detail)
{
    global $failed;
    $failed++;
    fwrite(STDERR, $name . ': ' . $detail . PHP_EOL);
}

function codes($findings)
{
    $out = array();
    foreach ($findings as $row) {
        $out[] = $row['code'];
    }
    sort($out);
    return $out;
}

foreach ($fixtures['utm'] as $row) {
    $built = WeaduFeedAudit::buildUtmUrl($row['input']);
    if ($built['ok'] !== $row['ok']) {
        fail($row['name'], 'ok ' . var_export($built['ok'], true));
        continue;
    }
    if ($row['ok']) {
        if ($built['url'] !== $row['url']) {
            fail($row['name'], $built['url']);
        }
    } else {
        $expected = $row['codes'];
        sort($expected);
        $actual = codes($built['errors']);
        if ($actual !== $expected) {
            fail($row['name'], implode(',', $actual));
        }
    }
}

foreach ($fixtures['items'] as $row) {
    $report = WeaduFeedAudit::auditItems(array($row['item']));
    $expected = $row['codes'];
    sort($expected);
    $actual = codes($report['items'][0]['findings']);
    if ($actual !== $expected) {
        fail($row['name'], implode(',', $actual));
    }
}

foreach ($fixtures['titles'] as $row) {
    $suggested = WeaduFeedAudit::suggestTitle($row['item']);
    if ($suggested !== $row['suggested']) {
        fail($row['name'], $suggested);
    }
}

$long = str_repeat('A', 160);
if (strlen(WeaduFeedAudit::suggestTitle(array('title' => $long, 'brand' => ''))) !== 150) {
    fail('trim 150', 'length');
}

$batch = WeaduFeedAudit::auditItems(array($fixtures['items'][0]['item'], $fixtures['items'][0]['item']));
if (codes($batch['items'][1]['findings']) !== array('duplicate_id')) {
    fail('duplicate', implode(',', codes($batch['items'][1]['findings'])));
}

$csv = WeaduFeedAudit::toCsv(WeaduFeedAudit::auditItems(array($fixtures['items'][3]['item'])));
if (strpos($csv, 'gtin_invalid') === false) {
    fail('csv', 'missing gtin_invalid');
}

if (WeaduFeedAudit::label('missing_gtin', 'fr') !== 'GTIN et MPN vides.') {
    fail('label fr', WeaduFeedAudit::label('missing_gtin', 'fr'));
}
if (WeaduFeedAudit::label('missing_gtin', 'en') !== 'gtin and mpn are both empty.') {
    fail('label en', WeaduFeedAudit::label('missing_gtin', 'en'));
}

if (!WeaduFeedAudit::gtinIsValid('4006381333931') || WeaduFeedAudit::gtinIsValid('4006381333932')) {
    fail('gtin', 'check digit');
}

if ($failed > 0) {
    fwrite(STDERR, $failed . " php failure(s)\n");
    exit(1);
}

fwrite(STDOUT, "php feed audit ok\n");
