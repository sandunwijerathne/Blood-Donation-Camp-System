<?php
/**
 * Blood Donor Management System - test suite.
 *
 *   php tests/run.php
 *
 * Exit code 0 if everything passes, 1 otherwise, so it can gate a
 * deploy script.
 *
 * WHY NO PHPUNIT
 *   vendor/ is tracked in this repository - a dependency bump already
 *   touches 500+ files - so adding PHPUnit would commit thousands more
 *   for a suite this size. The project uses no framework anywhere else
 *   either. A runner small enough to read in one sitting fits better.
 *
 * WHAT IS COVERED
 *   The pure logic behind the bugs that actually happened to this
 *   system, not coverage for its own sake. Every group below maps to a
 *   real defect: donors stored twice under two number formats, "LIMIT
 *   -1" crashing five endpoints, Sinhala arriving as "?????", a filter
 *   that could not reach 344 donors, and a retry re-messaging everyone.
 *
 * WHAT IS NOT
 *   Nothing here touches the database or the network. That keeps the
 *   suite runnable anywhere and unable to damage data, at the cost of
 *   not covering the endpoints themselves. Those are still verified by
 *   hand - see docs/recovery.md and scripts/verify-restore.sh.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Tests are CLI only.\n");
}

$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/messaging.php';

// ── Tiny assertion harness ───────────────────────────────────
$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;
$GLOBALS['failures'] = [];

function group(string $name): void
{
    printf("%s%s%s", PHP_EOL, $name, PHP_EOL);
}

function is_same(string $label, $actual, $expected): void
{
    if ($actual === $expected) {
        $GLOBALS['passed']++;
        printf("  \u{2713} %s%s", $label, PHP_EOL);
        return;
    }

    $GLOBALS['failed']++;
    $msg = sprintf("%s%s      expected: %s%s      actual:   %s",
        $label, PHP_EOL, var_export($expected, true), PHP_EOL, var_export($actual, true));
    $GLOBALS['failures'][] = $msg;
    printf("  \u{2717} %s%s", $msg, PHP_EOL);
}

function is_true(string $label, $actual): void
{
    is_same($label, (bool) $actual, true);
}

// ─────────────────────────────────────────────────────────────
group('Mobile normalisation  (donors were stored twice under two formats)');

is_same('spaces stripped',            normalizeMobile('070 000 0000'), '0700000000');
is_same('+94 converted to leading 0', normalizeMobile('+94700000000'), '0700000000');
is_same('94 prefix converted',        normalizeMobile('94700000000'),  '0700000000');
is_same('9 digits gain the 0',        normalizeMobile('700000000'),    '0700000000');
is_same('dashes stripped',            normalizeMobile('070-000-0000'), '0700000000');
is_same('already canonical',          normalizeMobile('0700000000'),   '0700000000');
is_same('all four spellings agree',
    count(array_unique(array_map('normalizeMobile',
        ['070 000 0000', '+94700000000', '94700000000', '0700000000']))), 1);
is_same('too short rejected',         normalizeMobile('12345'),        '');
is_same('empty rejected',             normalizeMobile(''),             '');
is_same('letters rejected',           normalizeMobile('not a number'), '');
is_same('landline still parses',      normalizeMobile('0917435522'),   '0917435522');
is_true('landlines are detectable',   !str_starts_with(normalizeMobile('0917435522'), '07'));

// ─────────────────────────────────────────────────────────────
group('DataTables paging  ("LIMIT -1" was a SQL syntax error on 5 endpoints)');

$paging = function (array $post): array {
    $_POST = $post;
    return dataTablePaging();
};

is_same('"All" (-1) becomes the cap',  $paging(['length' => -1]),            [0, 100]);
is_same('normal page survives',        $paging(['start' => 25, 'length' => 25]), [25, 25]);
is_same('oversized request clamped',   $paging(['length' => 999999]),        [0, 100]);
is_same('negative offset floored',     $paging(['start' => -50]),            [0, 25]);
is_same('zero length becomes the cap', $paging(['length' => 0]),             [0, 100]);
is_same('missing params default',      $paging([]),                          [0, 25]);
is_same('injection attempt cast away', $paging(['start' => '1;DROP TABLE donors', 'length' => '10 OR 1=1']), [1, 10]);
$_POST = [];

// ─────────────────────────────────────────────────────────────
group('Blood group filter  (344 donors were unreachable through the UI)');

$clause = function (string $v): array {
    $params = [];
    $sql = bloodGroupFilterClause($v, $params);
    return [$sql, $params];
};

is_same('empty means no filter',    $clause(''),        ['', []]);
is_same('a real group binds a param', $clause('O+'),    ['blood_group = ?', ['O+']]);
is_same('sentinel matches blanks',  $clause(BLOOD_GROUP_NONE),
    ["(blood_group IS NULL OR blood_group = '')", []]);
is_same('invalid group ignored',    $clause('X+'),      ['', []]);
is_same('injection ignored',        $clause("' OR 1=1 --"), ['', []]);
is_same('wildcard ignored',         $clause('%'),       ['', []]);
is_same('sentinel binds nothing',   $clause(BLOOD_GROUP_NONE)[1], []);

// ─────────────────────────────────────────────────────────────
group('Campaign batching  (a retry used to re-message everyone)');

is_same('valid id accepted',   normaliseCampaignId('a1b2c3d4e5f60718293a4b5c6d7e8f90'), 'a1b2c3d4e5f60718293a4b5c6d7e8f90');
is_same('uppercase rejected',  normaliseCampaignId('A1B2C3D4E5F60718293A4B5C6D7E8F90'), null);
is_same('short rejected',      normaliseCampaignId('abc'),        null);
is_same('injection rejected',  normaliseCampaignId("' OR 1=1 --"), null);
is_same('null rejected',       normaliseCampaignId(null),         null);

$chunk = function ($v) { $_POST = $v === null ? [] : ['chunk' => $v]; return sendChunkSize(); };
is_same('default chunk',       $chunk(null), 40);
is_same('honours a request',   $chunk(10),   10);
is_same('caps a large chunk',  $chunk(500),  100);
is_same('negative falls back', $chunk(-5),   40);
$_POST = [];

// ─────────────────────────────────────────────────────────────
group('Template rendering  (placeholders must survive translation)');

$body = 'ආයුබෝවන් {NAME}, දිනය: {DATE}, ස්ථානය: {LOCATION}';
$out  = replacePlaceholders($body, ['name' => 'නිමල්', 'date' => '2026-09-20', 'location' => 'ගාල්ල']);

is_true('Sinhala body renders',          str_contains($out, 'නිමල්'));
is_same('no tokens left unresolved',     preg_match('/\{[A-Z_]+\}/', $out), 0);
is_true('Sinhala preserved end to end',  mb_check_encoding($out, 'UTF-8') && !str_contains($out, '?'));
is_same('unknown tokens are left alone',
    replacePlaceholders('Hello {NAME} {NOPE}', ['name' => 'X']), 'Hello X {NOPE}');
is_same('empty data changes nothing',    replacePlaceholders('plain text', []), 'plain text');

// ─────────────────────────────────────────────────────────────
group('SMS encoding and cost  (Sinhala arrived as "?????" and costs 3x)');

$needsUnicode = fn(string $m): bool => !mb_check_encoding($m, 'ASCII');

is_true('English stays GSM-7',        !$needsUnicode('Blood camp on Saturday'));
is_true('Sinhala forces unicode',      $needsUnicode('ආයුබෝවන්'));
is_true('a Sinhala NAME in an English template still flips it',
    $needsUnicode(replacePlaceholders('Hello {NAME}, please donate', ['name' => 'නිමල් පෙරේරා'])));
is_same('length counted in characters, not bytes', mb_strlen('රුධිර'), 5);
is_true('byte length differs, which is the trap',  strlen('රුධිර') > mb_strlen('රුධිර'));
is_true('the 320 cap is defined',      defined('NOTIFY_SMS_MAX_CHARS') && NOTIFY_SMS_MAX_CHARS === 320);
is_true('an over-length Sinhala message exceeds the cap',
    mb_strlen(str_repeat('රුධිර', 100)) > NOTIFY_SMS_MAX_CHARS);

// ─────────────────────────────────────────────────────────────
group('Output escaping  (stored XSS ran in the admin browser)');

is_same('angle brackets escaped', sanitize('<img src=x onerror=alert(1)>'),
    '&lt;img src=x onerror=alert(1)&gt;');
is_same('quotes escaped',         sanitize('a "b" \'c\''), 'a &quot;b&quot; &#039;c&#039;');
is_true('Sinhala survives escaping',
    str_contains(sanitize('නිමල් පෙරේරා'), 'නිමල්'));
is_same('ampersand escaped once',  sanitize('a & b'), 'a &amp; b');

// ─────────────────────────────────────────────────────────────
group('Lockout wording  (read by someone who just mistyped a password)');

is_same('seconds stay seconds', humaniseSeconds(45),  '45 seconds');
is_same('singular second',      humaniseSeconds(1),   '1 second');
is_same('rounds up to minutes', humaniseSeconds(90),  '2 minutes');
is_same('singular minute',      humaniseSeconds(60),  '1 minute');
is_same('a full window',        humaniseSeconds(900), '15 minutes');

// ─────────────────────────────────────────────────────────────
group('Gateway setup hints  (terse provider errors made actionable)');

$notifyFail = ['gateway' => 'notify', 'http' => 400, 'detail' => 'The sender id field is invalid.'];
is_true('sender problems name the fix',
    str_contains(smsSetupHint($notifyFail), 'NotifyDEMO'));

$keyFail = ['gateway' => 'notify', 'http' => 400, 'detail' => 'The api key field must be 20 characters.'];
is_true('key problems point at the settings page',
    str_contains(smsSetupHint($keyFail), 'Notify.lk settings page'));

is_same('non-notify gateways pass through unchanged',
    smsSetupHint(['gateway' => 'twilio', 'http' => 401, 'detail' => 'nope']), 'nope');

is_true('WhatsApp token errors mention the 24 hour limit',
    str_contains(whatsAppSetupHint(['http' => 401, 'detail' => 'access token expired']), '24 hours'));

// ─────────────────────────────────────────────────────────────
printf("%s%s%s", PHP_EOL, str_repeat('-', 60), PHP_EOL);
printf("%d passed, %d failed%s", $GLOBALS['passed'], $GLOBALS['failed'], PHP_EOL);

if ($GLOBALS['failed'] > 0) {
    printf("%sFAILURES:%s", PHP_EOL, PHP_EOL);
    foreach ($GLOBALS['failures'] as $f) printf("  %s%s", $f, PHP_EOL);
    exit(1);
}

exit(0);
