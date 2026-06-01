<?php
/**
 * Xendit diagnostic — TEMPORARY. Delete this file when you're done.
 *
 * Visit:  http://localhost/ecommerce/xendit_test.php
 * It checks every part of the Xendit chain and shows the REAL error
 * (your normal pages hide it because display_errors is off).
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

function line($label, $ok, $detail = '') {
    echo ($ok ? '✅ ' : '❌ ') . str_pad($label, 34) . ($detail !== '' ? ' → ' . $detail : '') . "\n";
}

echo "=========================================\n";
echo " ON THE LINE — Xendit Diagnostic\n";
echo "=========================================\n\n";

/* 1. PHP cURL extension */
$hasCurl = function_exists('curl_init');
line('PHP cURL extension', $hasCurl,
    $hasCurl ? 'enabled' : 'MISSING — enable extension=curl in php.ini and restart Apache');

/* 2. Secret key present & looks valid */
$key = defined('XENDIT_SECRET_KEY') ? XENDIT_SECRET_KEY : '';
$keyLooksReal = $key !== '' && strpos($key, 'REPLACE_') !== 0 && strpos($key, 'xnd_') === 0;
line('XENDIT_SECRET_KEY set', $keyLooksReal,
    $key === '' ? 'EMPTY'
    : (strpos($key, 'REPLACE_') === 0 ? 'still the placeholder — set your real test key'
    : (strpos($key, 'xnd_') !== 0 ? 'does not start with "xnd_" — wrong value?'
    : 'starts with xnd_ … (' . substr($key, 0, 16) . '…) length=' . strlen($key))));

/* 2b. Make sure it's a SECRET key, not a public key */
if ($keyLooksReal && strpos($key, 'xnd_public') === 0) {
    line('Key type', false, 'This is a PUBLIC key. You need the SECRET key (xnd_development_... or xnd_production_...)');
}

/* 3. Callback token (for webhook, not invoice creation) */
$tok = defined('XENDIT_CALLBACK_TOKEN') ? XENDIT_CALLBACK_TOKEN : '';
$tokSet = $tok !== '' && strpos($tok, 'REPLACE_') !== 0;
line('XENDIT_CALLBACK_TOKEN set', $tokSet,
    $tokSet ? 'set (needed only for payment confirmation webhook)' : 'not set — invoices still work, but PAID status won\'t auto-update');

/* 4. Database reachable */
try {
    require_once __DIR__ . '/db.php';
    db()->query('SELECT 1');
    line('Database connection', true, DB_NAME . ' @ ' . DB_HOST);
} catch (Throwable $e) {
    line('Database connection', false, $e->getMessage());
}

/* 5. Live ping to Xendit — create a tiny test invoice */
echo "\n--- Live test invoice (₱10,000) ---\n";
if (!$hasCurl) {
    echo "Skipped: cURL not available.\n";
} elseif (!$keyLooksReal) {
    echo "Skipped: set a valid secret key first.\n";
} else {
    $payload = [
        'external_id' => 'DIAG-' . bin2hex(random_bytes(4)),
        'amount'      => 10000,
        'description' => 'Diagnostic test invoice',
        'currency'    => 'PHP',
    ];
    $ch = curl_init(XENDIT_API_BASE . '/v2/invoices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(XENDIT_SECRET_KEY . ':'),
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code === 0) {
        line('HTTP call to Xendit', false, 'cURL error: ' . $cerr);
        echo "\nLikely causes:\n";
        echo "  • No internet from the server, OR\n";
        echo "  • SSL cert problem on XAMPP (cacert.pem). Fix:\n";
        echo "      1. Download https://curl.se/ca/cacert.pem\n";
        echo "      2. Save to C:\\xampp\\php\\extras\\ssl\\cacert.pem\n";
        echo "      3. In php.ini set:  curl.cainfo = \"C:\\xampp\\php\\extras\\ssl\\cacert.pem\"\n";
        echo "      4. Restart Apache.\n";
    } else {
        $data = json_decode($resp, true);
        if ($code >= 200 && $code < 300 && !empty($data['invoice_url'])) {
            line('HTTP call to Xendit', true, "HTTP $code");
            echo "\n🎉 SUCCESS — Xendit is working!\n";
            echo "Test invoice URL:\n  " . $data['invoice_url'] . "\n";
            echo "\nYour key and connection are fine. If checkout still fails,\n";
            echo "the problem is elsewhere (empty cart, etc.) — check the Apache error log.\n";
        } else {
            line('HTTP call to Xendit', false, "HTTP $code");
            echo "\nXendit rejected the request. Raw response:\n";
            echo "  " . $resp . "\n\n";
            if ($code === 401) {
                echo "HTTP 401 = bad/invalid API key. Double-check you copied the SECRET key\n";
                echo "exactly (no spaces), and that it matches Test vs Live mode.\n";
            } elseif ($code === 400) {
                echo "HTTP 400 = request rejected (e.g. amount below minimum, ~₱15-ish,\n";
                echo "or account not activated for that payment method).\n";
            }
        }
    }
}

echo "\n=========================================\n";
echo "When finished, DELETE this file (xendit_test.php).\n";
