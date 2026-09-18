<?php
/**
 * Small helpers used across the whole application.
 * Session and role checks live in auth.php.
 */

/** Builds a URL relative to the project root, so links work from any folder. */
function base_url(string $path = ''): string {
    $root = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // Pages live either at the root or one folder deep (admin/, teacher/, student/).
    foreach (['/admin', '/teacher', '/student'] as $sub) {
        if (substr($root, -strlen($sub)) === $sub) {
            $root = substr($root, 0, -strlen($sub));
            break;
        }
    }
    return ($root === '' ? '' : $root) . '/' . ltrim($path, '/');
}

/** Escapes text before printing it into HTML. */
function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * The core calculation of the project:
 *     Attendance % = (classes present / total classes) x 100
 */
function percentage(int $present, int $total): float {
    return $total <= 0 ? 0.0 : round(($present / $total) * 100, 2);
}

/** CSS class for a percentage: good, warn or low. */
function pct_class(float $pct): string {
    if ($pct >= MIN_ATTENDANCE)      return 'pct-good';
    if ($pct >= MIN_ATTENDANCE - 10) return 'pct-warn';
    return 'pct-low';
}

/** Stores a one-time message shown after a redirect. */
function set_flash(string $type, string $text): void {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function get_flash(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/** Creates and checks a token so forms cannot be submitted from another site. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('This form expired. Go back, reload the page and try again.');
    }
}

/** Reads an integer from the query string or POST body. */
function int_input(string $key, ?int $default = null): ?int {
    $raw = $_REQUEST[$key] ?? null;
    if ($raw === null || $raw === '') return $default;
    return (int) $raw;
}

/** Validates a YYYY-MM-DD date, falling back to today. */
function date_input(string $key, ?string $default = null): string {
    $raw = $_REQUEST[$key] ?? $default ?? date('Y-m-d');
    $d = DateTime::createFromFormat('Y-m-d', (string) $raw);
    return ($d && $d->format('Y-m-d') === $raw) ? $raw : date('Y-m-d');
}

/** Turns rows into a downloadable CSV file and stops the script. */
function send_csv(string $filename, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}
