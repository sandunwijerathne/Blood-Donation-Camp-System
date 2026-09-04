<?php
/**
 * Common Utility Functions
 */

require_once __DIR__ . '/db.php';

/**
 * Sanitize input string.
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Send a JSON response and exit.
 */
function sendJsonResponse(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get a setting value from the settings table.
 */
function getSetting(string $key, string $default = '', bool $forget = false): string
{
    static $cache = [];

    // saveSetting() calls this to drop a stale entry, so a value read
    // back later in the same request is the one just written.
    if ($forget) {
        unset($cache[$key]);
        return '';
    }

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = ($value !== false) ? $value : $default;
    } catch (PDOException $e) {
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/**
 * Save a setting value.
 */
function saveSetting(string $key, string $value): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);

        // Drop the cached copy, otherwise anything reading this key later
        // in the same request gets the value from before the save.
        getSetting($key, '', true);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get total donor count.
 */
function getTotalDonors(): int
{
    $db = getDB();
    return (int) $db->query("SELECT COUNT(*) FROM donors")->fetchColumn();
}

/**
 * Get donor count by blood group.
 */
function getDonorCountByBloodGroup(string $bloodGroup): int
{
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM donors WHERE blood_group = ? AND status = 'Active'");
    $stmt->execute([$bloodGroup]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get all blood group counts.
 */
function getAllBloodGroupCounts(): array
{
    $db = getDB();
    $stmt = $db->query("SELECT blood_group, COUNT(*) as count FROM donors WHERE status = 'Active' GROUP BY blood_group");
    $result = [];
    $groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    foreach ($groups as $g) {
        $result[$g] = 0;
    }
    while ($row = $stmt->fetch()) {
        $result[$row['blood_group']] = (int) $row['count'];
    }
    return $result;
}

/**
 * Get eligible donors count (last donation >= ELIGIBLE_MONTHS ago or never donated).
 */
function getEligibleDonorsCount(): int
{
    $db = getDB();
    $months = defined('ELIGIBLE_MONTHS') ? ELIGIBLE_MONTHS : 4;
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM donors 
         WHERE status = 'Active' 
         AND (last_donation_date IS NULL OR last_donation_date <= DATE_SUB(NOW(), INTERVAL ? MONTH))"
    );
    $stmt->execute([$months]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get messages sent today count.
 */
function getMessagesSentToday(): int
{
    $db = getDB();
    return (int) $db->query("SELECT COUNT(*) FROM message_logs WHERE DATE(sent_at) = CURDATE()")->fetchColumn();
}

/**
 * Get next upcoming blood camp.
 */
function getNextUpcomingCamp(): ?array
{
    $db = getDB();
    $stmt = $db->query("SELECT * FROM blood_camps WHERE camp_date >= CURDATE() AND status = 'Upcoming' ORDER BY camp_date ASC LIMIT 1");
    $camp = $stmt->fetch();
    return $camp ?: null;
}

/**
 * Format date for display.
 */
function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    return date($format, strtotime($date));
}

/**
 * Format time for display.
 */
function formatTime(?string $time, string $format = 'h:i A'): string
{
    if (empty($time)) {
        return '—';
    }
    return date($format, strtotime($time));
}

/**
 * Normalise a T.P. number to the canonical local storage format.
 *
 * The T.P. number is the unique identifier for a person, so every
 * entry point (donor form, Excel import, camp register) must store
 * it the same way or duplicates slip through. Handles the shapes
 * that appear in the paper register: "077 821 1176", "071-6340385",
 * "+94752698599", "94752698599".
 *
 * Returns '' when the input cannot be read as a usable number.
 */
function normalizeMobile(string $phone): string
{
    // Keep digits only - drop spaces, dashes, brackets, plus signs.
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') {
        return '';
    }

    // +94xxxxxxxxx / 94xxxxxxxxx -> 0xxxxxxxxx
    if (str_starts_with($digits, '94') && strlen($digits) === 11) {
        $digits = '0' . substr($digits, 2);
    }

    // Bare 9-digit mobile (7xxxxxxxx) -> prepend the leading zero.
    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        $digits = '0' . $digits;
    }

    // A Sri Lankan number is 10 digits starting with 0.
    if (strlen($digits) !== 10 || !str_starts_with($digits, '0')) {
        return '';
    }

    return $digits;
}

/**
 * Look up a donor by T.P. number (the unique identifier).
 */
function findDonorByMobile(string $mobile): ?array
{
    $mobile = normalizeMobile($mobile);

    if ($mobile === '') {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare(
        "SELECT id, donor_name, mobile, whatsapp, email, address, blood_group,
                gender, date_of_birth, last_donation_date, status
         FROM donors WHERE mobile = ? LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $donor = $stmt->fetch();

    return $donor ?: null;
}

/**
 * Format phone number for WhatsApp API (add country code if missing).
 */
function formatPhoneForAPI(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    $countryCode = getSetting('country_code', '+94');
    
    // Remove leading zero and add country code
    if (str_starts_with($phone, '0')) {
        $phone = $countryCode . substr($phone, 1);
    }
    
    // Ensure + prefix
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }
    
    return $phone;
}

/**
 * Replace template placeholders with actual values.
 */
function replacePlaceholders(string $template, array $data): string
{
    foreach ($data as $key => $value) {
        $template = str_replace('{' . strtoupper($key) . '}', $value, $template);
    }
    return $template;
}

/**
 * Log a sent message.
 */
/**
 * Record one sent message.
 *
 * A row belongs to a donor or to a staff member, never both. donor_id has
 * a foreign key into donors, so a staff id cannot be squeezed into it -
 * pass null there and give the id as $staffId instead. Both being null is
 * valid too: that is a message to a recipient who has since been deleted.
 */
function logMessage(?int $donorId, string $type, string $mobile, string $message, string $status, string $apiResponse = '', ?int $staffId = null, ?string $campaignId = null): int
{
    $db = getDB();
    $stmt = $db->prepare(
        "INSERT INTO message_logs (donor_id, staff_id, campaign_id, message_type, mobile, message, status, api_response, sent_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$donorId ?: null, $staffId ?: null, $campaignId ?: null, $type, $mobile, $message, $status, $apiResponse]);
    return (int) $db->lastInsertId();
}

/**
 * Has this recipient already been sent to successfully in this campaign?
 *
 * Sending happens in chunks, and a chunk can fail or be retried. Without
 * this check a retry re-sends to everyone already contacted - which on a
 * 488-donor list means 488 duplicate messages and a duplicated bill.
 */
function alreadySentInCampaign(?string $campaignId, ?int $donorId, ?int $staffId): bool
{
    if ($campaignId === null || $campaignId === '') {
        return false;
    }

    $db = getDB();

    if ($staffId !== null) {
        $stmt = $db->prepare(
            "SELECT 1 FROM message_logs
             WHERE campaign_id = ? AND staff_id = ? AND status = 'Sent' LIMIT 1"
        );
        $stmt->execute([$campaignId, $staffId]);
    } else {
        $stmt = $db->prepare(
            "SELECT 1 FROM message_logs
             WHERE campaign_id = ? AND donor_id = ? AND status = 'Sent' LIMIT 1"
        );
        $stmt->execute([$campaignId, $donorId]);
    }

    return (bool) $stmt->fetchColumn();
}

/**
 * Validate a campaign id supplied by the browser.
 *
 * It only ever groups rows together, but it still reaches SQL, so it is
 * constrained to the shape the client is meant to generate.
 */
function normaliseCampaignId(?string $raw): ?string
{
    $raw = trim((string) $raw);

    return preg_match('/^[a-f0-9]{32}$/', $raw) ? $raw : null;
}

/**
 * How many recipients one request may process.
 *
 * Each recipient costs one outbound HTTP call, so the chunk size has to
 * leave room under max_execution_time for the slowest plausible call.
 */
function sendChunkSize(): int
{
    $requested = (int) ($_POST['chunk'] ?? 0);
    $default   = 40;

    return $requested > 0 ? min($requested, 100) : $default;
}

/**
 * Get blood group color for UI.
 */
function getBloodGroupColor(string $group): string
{
    $colors = [
        'A+'  => '#e74c3c',
        'A-'  => '#c0392b',
        'B+'  => '#3498db',
        'B-'  => '#2980b9',
        'AB+' => '#9b59b6',
        'AB-' => '#8e44ad',
        'O+'  => '#2ecc71',
        'O-'  => '#27ae60'
    ];
    return $colors[$group] ?? '#95a5a6';
}

/**
 * Get blood group gradient for UI cards.
 */
function getBloodGroupGradient(string $group): string
{
    $gradients = [
        'A+'  => 'linear-gradient(135deg, #e74c3c, #ff6b6b)',
        'A-'  => 'linear-gradient(135deg, #c0392b, #e74c3c)',
        'B+'  => 'linear-gradient(135deg, #3498db, #74b9ff)',
        'B-'  => 'linear-gradient(135deg, #2980b9, #3498db)',
        'AB+' => 'linear-gradient(135deg, #9b59b6, #c39bd3)',
        'AB-' => 'linear-gradient(135deg, #8e44ad, #9b59b6)',
        'O+'  => 'linear-gradient(135deg, #2ecc71, #55efc4)',
        'O-'  => 'linear-gradient(135deg, #27ae60, #2ecc71)'
    ];
    return $gradients[$group] ?? 'linear-gradient(135deg, #95a5a6, #bdc3c7)';
}

// ═══════════════════════════════════════════════════════════
// CAMP BUDGET, CONTRIBUTIONS AND EXPENSES
// ═══════════════════════════════════════════════════════════

/**
 * Format a money value for display.
 *
 * The currency symbol is a setting rather than a constant so the same
 * code serves an organisation working in rupees and one that is not.
 */
function formatMoney(float|int|string|null $amount, bool $withSymbol = true): string
{
    if ($amount === null || $amount === '') {
        return $withSymbol ? getSetting('currency_symbol', 'Rs.') . ' 0.00' : '0.00';
    }

    $formatted = number_format((float) $amount, 2);

    return $withSymbol
        ? getSetting('currency_symbol', 'Rs.') . ' ' . $formatted
        : $formatted;
}

/**
 * The kinds of thing a wellwisher hands over at a camp.
 *
 * 'Cash' is the odd one out: for that category the contribution's
 * `amount` is an exact receipt, for every other category it is an
 * optional estimate of what the goods were worth. Keeping the list
 * here means the form, the validation and the export never drift.
 */
function campContributionCategories(): array
{
    return ['Food', 'Drinks', 'Water', 'Snacks', 'Medical', 'Equipment', 'Cash', 'Other'];
}

/**
 * Icon for each contribution category, used on the cards and tables.
 */
function contributionCategoryIcon(string $category): string
{
    $icons = [
        'Food'      => 'fa-utensils',
        'Drinks'    => 'fa-mug-hot',
        'Water'     => 'fa-bottle-water',
        'Snacks'    => 'fa-cookie-bite',
        'Medical'   => 'fa-kit-medical',
        'Equipment' => 'fa-chair',
        'Cash'      => 'fa-money-bill-wave',
        'Other'     => 'fa-box'
    ];
    return $icons[$category] ?? 'fa-box';
}

/**
 * The headings a camp's spending is broken down under.
 */
function campExpenseCategories(): array
{
    return ['Food', 'Drinks', 'Water', 'Transport', 'Printing', 'Venue', 'Medical', 'Decoration', 'Volunteer', 'Other'];
}

/**
 * Payment methods an expense can be settled by.
 */
function campPaymentMethods(): array
{
    return ['Cash', 'Bank Transfer', 'Card', 'Online', 'Other'];
}

/**
 * Every money figure for one camp, in one query pass.
 *
 * Donated goods are deliberately NOT added to the cash balance. A
 * hundred water bottles handed over on the morning is real value, but
 * it is not money the treasurer can spend, so it is reported on its
 * own line as `inkind_value` and the balance stays honest.
 */
function getCampFinanceSummary(int $campId): array
{
    $summary = [
        'budget'           => 0.0,
        'cash_received'    => 0.0,
        'cash_pledged'     => 0.0,
        'inkind_value'     => 0.0,
        'inkind_items'     => 0,
        'contributors'     => 0,
        'expenses_paid'    => 0.0,
        'expenses_planned' => 0.0,
        'balance'          => 0.0,
        'remaining'        => 0.0,
        'total_cost'       => 0.0
    ];

    if ($campId <= 0) {
        return $summary;
    }

    $db = getDB();

    $stmt = $db->prepare("SELECT budget_amount FROM blood_camps WHERE id = ? LIMIT 1");
    $stmt->execute([$campId]);
    $summary['budget'] = (float) ($stmt->fetchColumn() ?: 0);

    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN category = 'Cash' AND status = 'Received' THEN amount END), 0)  AS cash_received,
            COALESCE(SUM(CASE WHEN category = 'Cash' AND status = 'Pledged'  THEN amount END), 0)  AS cash_pledged,
            COALESCE(SUM(CASE WHEN category <> 'Cash' AND status = 'Received' THEN amount END), 0) AS inkind_value,
            COALESCE(SUM(CASE WHEN category <> 'Cash' THEN 1 ELSE 0 END), 0)                       AS inkind_items,
            COUNT(DISTINCT contributor_name)                                                       AS contributors
         FROM camp_contributions WHERE camp_id = ?"
    );
    $stmt->execute([$campId]);
    $contrib = $stmt->fetch() ?: [];

    $summary['cash_received'] = (float) ($contrib['cash_received'] ?? 0);
    $summary['cash_pledged']  = (float) ($contrib['cash_pledged'] ?? 0);
    $summary['inkind_value']  = (float) ($contrib['inkind_value'] ?? 0);
    $summary['inkind_items']  = (int)   ($contrib['inkind_items'] ?? 0);
    $summary['contributors']  = (int)   ($contrib['contributors'] ?? 0);

    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'Paid'    THEN amount END), 0) AS paid,
            COALESCE(SUM(CASE WHEN status = 'Planned' THEN amount END), 0) AS planned
         FROM camp_expenses WHERE camp_id = ?"
    );
    $stmt->execute([$campId]);
    $expense = $stmt->fetch() ?: [];

    $summary['expenses_paid']    = (float) ($expense['paid'] ?? 0);
    $summary['expenses_planned'] = (float) ($expense['planned'] ?? 0);

    $summary['total_cost'] = $summary['expenses_paid'] + $summary['expenses_planned'];

    // Money actually available: what was budgeted plus cash donations,
    // less what has already been paid out.
    $summary['balance'] = $summary['budget'] + $summary['cash_received'] - $summary['expenses_paid'];

    // How much of the budget is still unspent once commitments are counted.
    $summary['remaining'] = $summary['budget'] - $summary['total_cost'];

    return $summary;
}

/**
 * Read DataTables' paging parameters safely.
 *
 * DataTables sends length = -1 for "show all". Interpolated straight
 * into "LIMIT -1" that is a SQL syntax error; a large positive value is
 * worse, loading every row into memory. Two of the seven list endpoints
 * clamped this and five did not, which is what having the rule in seven
 * places produces - so it lives here now.
 *
 * "All" is mapped to $maxLength rather than the default, so the control
 * still does something useful instead of silently showing 25 rows.
 *
 * Returns [$start, $length], both safe to interpolate.
 */
function dataTablePaging(int $maxLength = 100, int $default = 25): array
{
    $start  = max(0, (int) ($_POST['start'] ?? 0));
    $length = (int) ($_POST['length'] ?? $default);
    $length = $length <= 0 ? $maxLength : min($length, $maxLength);

    return [$start, $length];
}

// ── Login throttling ─────────────────────────────────────────
// Tuned for a system with a handful of legitimate operators. A real
// person mistypes a password two or three times; five failures in
// fifteen minutes is not a person remembering.
const LOGIN_MAX_PER_EMAIL   = 5;
const LOGIN_MAX_PER_IP      = 20;
const LOGIN_WINDOW_SECONDS  = 900;   // 15 minutes
const LOGIN_ATTEMPT_RETAIN  = 86400; // prune records after a day

/**
 * The client's IP address.
 *
 * Proxy headers are deliberately NOT trusted: X-Forwarded-For is
 * attacker-controlled unless a known proxy sets it, and trusting it
 * would let anyone reset their own rate limit by forging a header.
 * Behind a real reverse proxy, resolve the trusted address there.
 */
function clientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Record one login attempt, successful or not.
 */
function recordLoginAttempt(string $email, bool $successful): void
{
    $db = getDB();

    $stmt = $db->prepare(
        "INSERT INTO login_attempts (email, ip_address, successful) VALUES (?, ?, ?)"
    );
    $stmt->execute([mb_substr($email, 0, 255), clientIp(), $successful ? 1 : 0]);

    // A successful login clears that account's failures, so someone who
    // finally remembers their password is not still locked out.
    if ($successful) {
        $stmt = $db->prepare(
            "DELETE FROM login_attempts WHERE email = ? AND successful = 0"
        );
        $stmt->execute([$email]);
    }

    // Opportunistic pruning - roughly one request in twenty - so the
    // table cannot grow without bound and no cron job is required.
    if (random_int(1, 20) === 1) {
        $db->prepare("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ? SECOND)")
           ->execute([LOGIN_ATTEMPT_RETAIN]);
    }
}

/**
 * How many seconds this login attempt must wait, or 0 if it may proceed.
 *
 * Counting is done over a sliding window from the OLDEST failure still
 * in scope, so an attacker cannot reset the clock by pausing.
 */
function loginLockoutSeconds(string $email): int
{
    $db = getDB();
    $wait = 0;

    foreach ([['email', $email, LOGIN_MAX_PER_EMAIL],
              ['ip_address', clientIp(), LOGIN_MAX_PER_IP]] as [$column, $value, $limit]) {

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS failures, MIN(attempted_at) AS first_failure
             FROM login_attempts
             WHERE $column = ? AND successful = 0
               AND attempted_at > (NOW() - INTERVAL ? SECOND)"
        );
        // $column is one of two literals above, never user input.
        $stmt->execute([$value, LOGIN_WINDOW_SECONDS]);
        $row = $stmt->fetch();

        if ((int) ($row['failures'] ?? 0) >= $limit) {
            $elapsed = time() - strtotime((string) $row['first_failure']);
            $wait = max($wait, LOGIN_WINDOW_SECONDS - $elapsed);
        }
    }

    return max(0, $wait);
}

/**
 * "4 minutes" rather than "223 seconds" - a lockout message is read by
 * someone who has just mistyped their password, not by a machine.
 */
function humaniseSeconds(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' second' . ($seconds === 1 ? '' : 's');
    }

    $minutes = (int) ceil($seconds / 60);

    return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
}
