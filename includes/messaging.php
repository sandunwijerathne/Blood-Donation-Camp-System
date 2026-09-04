<?php
/**
 * Messaging gateways.
 *
 * WHY THIS FILE EXISTS
 *   The Twilio send routine existed twice, byte for byte: once in
 *   ajax/send-sms.php for real sends and once in ajax/settings-save.php
 *   for the Settings test. The WhatsApp POST was duplicated the same
 *   way. Two copies of a provider integration drift - the test starts
 *   passing while real sends fail, or a fix lands in one and not the
 *   other. That is exactly the bug class this system can least afford,
 *   because a broken send is only discovered after a camp.
 *
 *   Every outbound message now goes through one function per provider.
 *   The callers differ only in how they present the result.
 *
 * RESULT SHAPE
 *   Senders and the Settings test need different things, so the core
 *   returns everything and the callers map it:
 *
 *     ok       bool    delivered as far as the provider is concerned
 *     status   string  'Sent' | 'Pending' | 'Failed' - written to
 *                      message_logs.status
 *     detail   string  human-facing reason, for a toast
 *     raw      string  the provider's own response body, logged so a
 *                      failed camp blast is diagnosable weeks later
 *     http     int     HTTP status, 0 if the request never left
 *     gateway  string  which provider handled it
 *
 * 'Pending' means "not attempted" - unconfigured credentials or a
 * gateway with no implementation - as distinct from 'Failed', which
 * means the provider was asked and said no.
 */

require_once __DIR__ . '/functions.php';

/**
 * POST a form-encoded request and return body, error and status.
 *
 * The three integrations differ only in URL, headers and payload, so
 * the transport lives here once. CURLOPT_TIMEOUT is the reason bulk
 * sending has to be chunked: 20 seconds per recipient does not fit in
 * one web request.
 */
function httpPostRequest(string $url, array $options): array
{
    if (!function_exists('curl_init')) {
        return ['body' => '', 'error' => 'PHP cURL extension is not enabled.', 'http' => 0];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $body  = curl_exec($ch);
    $error = curl_error($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'body'  => $body === false ? '' : (string) $body,
        'error' => $body === false ? ($error ?: 'No response.') : '',
        'http'  => $http,
    ];
}

/**
 * Send one SMS through Notify.lk.
 *
 * Notify's whole API is a single form-encoded POST, so there is no
 * client library here on purpose: the official notifylk/notify-php
 * returns [null, ...] from sendSMSWithHttpInfo(), discarding the
 * response body - and that body is the only place Notify reports
 * whether a message was actually accepted.
 *
 * Settings map onto Notify's parameters as:
 *   sms_api_key    -> user_id    (numeric API user id)
 *   sms_api_secret -> api_key
 *   sms_sender_id  -> sender_id  (an approved name, never a number)
 */
function sendNotifySms(string $phone, string $message, string $userId, string $apiKey, string $senderId): array
{
    // Notify wants 9471XXXXXXX - country code, no plus, no leading zero.
    $to = ltrim($phone, '+');

    // Counted in characters, not bytes: a Sinhala message is multi-byte
    // and strlen() would reject valid messages at a third of the limit.
    $length = mb_strlen($message);
    if ($length > NOTIFY_SMS_MAX_CHARS) {
        return [
            'ok'    => false,
            'http'  => 0,
            'body'  => '',
            'error' => "Message is $length characters; Notify.lk accepts at most " . NOTIFY_SMS_MAX_CHARS . '.',
        ];
    }

    $fields = [
        'user_id'   => $userId,
        'api_key'   => $apiKey,
        'sender_id' => $senderId,
        'to'        => $to,
        'message'   => $message,
    ];

    // Notify encodes as GSM-7 by default, and GSM-7 has no Sinhala
    // characters - the message arrives as a row of "?". type=unicode
    // switches the send to UCS-2.
    //
    // Only set it when the text needs it: UCS-2 carries 70 characters
    // per segment instead of 160, so forcing it on an English message
    // would roughly double what that message costs to send.
    if (!mb_check_encoding($message, 'ASCII')) {
        $fields['type'] = 'unicode';
    }

    $res = httpPostRequest('https://app.notify.lk/api/v1/send', [
        CURLOPT_POSTFIELDS => http_build_query($fields),
    ]);

    if ($res['error'] !== '') {
        return ['ok' => false, 'http' => $res['http'], 'body' => '', 'error' => $res['error']];
    }

    // Notify answers 200 with {"status":"error"} for a rejected send, so
    // the decoded body decides the outcome - the HTTP code on its own
    // would report those failures as successes.
    $body = json_decode($res['body'], true);
    $ok   = $res['http'] < 400 && is_array($body) && ($body['status'] ?? '') === 'success';

    $error = '';
    if (!$ok) {
        if (!is_array($body)) {
            $error = 'Unreadable response from Notify.lk.';
        } else {
            // Field-level problems come back as {"errors":["..."]} while
            // other failures use a single message, so read both shapes.
            $errors = $body['errors'] ?? null;
            $error = (is_array($errors) && $errors)
                ? implode(' ', array_map('strval', $errors))
                : (string) ($body['message'] ?? $body['error'] ?? 'Notify.lk rejected the message.');
        }
    }

    return ['ok' => $ok, 'http' => $res['http'], 'body' => $res['body'], 'error' => $error];
}

/**
 * Send one SMS through whichever gateway is configured.
 *
 * The single entry point for every SMS this application sends, real or
 * test.
 */
function smsSend(string $phone, string $message): array
{
    $gateway   = strtolower(getSetting('sms_gateway', 'twilio'));
    $apiKey    = getSetting('sms_api_key');
    $apiSecret = getSetting('sms_api_secret');
    $senderId  = getSetting('sms_sender_id');

    $base = ['gateway' => $gateway, 'http' => 0, 'raw' => ''];

    if ($apiKey === '' || $apiSecret === '' || $senderId === '') {
        return $base + ['ok' => false, 'status' => 'Pending',
            'detail' => 'SMS gateway credentials are not configured.'];
    }

    if (!function_exists('curl_init')) {
        return $base + ['ok' => false, 'status' => 'Failed',
            'detail' => 'PHP cURL extension is not enabled.'];
    }

    if ($gateway === 'notify') {
        $r = sendNotifySms($phone, $message, $apiKey, $apiSecret, $senderId);

        return [
            'gateway' => $gateway,
            'ok'      => $r['ok'],
            'status'  => $r['ok'] ? 'Sent' : 'Failed',
            'detail'  => $r['ok'] ? 'Message accepted by Notify.lk.' : $r['error'],
            'raw'     => $r['body'],
            'http'    => $r['http'],
        ];
    }

    if ($gateway === 'twilio') {
        $res = httpPostRequest(
            "https://api.twilio.com/2010-04-01/Accounts/$apiKey/Messages.json",
            [
                CURLOPT_USERPWD    => $apiKey . ':' . $apiSecret,
                CURLOPT_POSTFIELDS => http_build_query([
                    'From' => $senderId,
                    'To'   => $phone,
                    'Body' => $message,
                ]),
            ]
        );

        $ok = $res['error'] === '' && $res['http'] < 400;

        return [
            'gateway' => $gateway,
            'ok'      => $ok,
            'status'  => $ok ? 'Sent' : 'Failed',
            'detail'  => $ok ? 'Message accepted by Twilio.' : ($res['error'] ?: $res['body']),
            'raw'     => $res['body'],
            'http'    => $res['http'],
        ];
    }

    // Dialog and Mobitel are selectable so credentials can be stored
    // during provider setup, but no endpoint is implemented for them.
    return $base + ['ok' => false, 'status' => 'Pending',
        'detail' => ucfirst($gateway) . ' gateway credentials saved; provider-specific endpoint not configured yet.'];
}

/**
 * Turn a failed SMS into advice.
 *
 * Provider wording is terse, so name the fix for the mistakes that
 * actually happen during first-time setup. Used by the Settings test,
 * where somebody is standing there trying to make it work.
 */
function smsSetupHint(array $result): string
{
    $detail = $result['detail'];

    if ($result['gateway'] !== 'notify') {
        return $detail;
    }

    if (stripos($detail, 'sender') !== false) {
        return $detail . ' - the Sender ID must be a name Notify has approved for your account. Use NotifyDEMO until yours is approved.';
    }

    if (stripos($detail, 'balance') !== false || stripos($detail, 'credit') !== false) {
        return $detail . ' - the Notify.lk account is out of credit.';
    }

    if ($result['http'] === 401
        || stripos($detail, 'unauthor') !== false
        || stripos($detail, 'invalid') !== false
        || stripos($detail, 'api key') !== false
        || stripos($detail, 'user id') !== false) {
        return $detail . ' - check the User ID and API Key against your Notify.lk settings page.';
    }

    return $detail;
}

/**
 * POST one payload to the WhatsApp Cloud API.
 *
 * Callers build the payload - a template or free text - and this sends
 * it. Same 'Pending' vs 'Failed' distinction as SMS.
 */
function whatsAppSend(array $payload): array
{
    $token         = getSetting('whatsapp_api_token');
    $phoneNumberId = getSetting('whatsapp_phone_number_id');
    $version       = getSetting('whatsapp_api_version', 'v23.0');

    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'status' => 'Pending', 'http' => 0, 'raw' => '',
            'detail' => 'WhatsApp API credentials are not configured.'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 'Failed', 'http' => 0, 'raw' => '',
            'detail' => 'PHP cURL extension is not enabled.'];
    }

    $res = httpPostRequest(
        "https://graph.facebook.com/$version/$phoneNumberId/messages",
        [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]
    );

    $ok = $res['error'] === '' && $res['http'] < 400;

    return [
        'ok'     => $ok,
        'status' => $ok ? 'Sent' : 'Failed',
        'detail' => $ok ? 'Message accepted by Meta.' : ($res['error'] ?: $res['body']),
        'raw'    => $res['body'],
        'http'   => $res['http'],
    ];
}

/**
 * Turn a failed WhatsApp send into advice, same idea as smsSetupHint().
 */
function whatsAppSetupHint(array $result): string
{
    $detail = $result['detail'];

    if (stripos($detail, 'not exist') !== false && stripos($detail, 'template') !== false) {
        return $detail . ' - the template was not found. Check the Phone Number ID belongs to this WhatsApp Business Account.';
    }

    if ($result['http'] === 401 || stripos($detail, 'access token') !== false) {
        return $detail . ' - the access token is invalid or has expired. Temporary tokens last 24 hours.';
    }

    if (stripos($detail, 'recipient') !== false) {
        return $detail . ' - add this number to the allowed recipient list on the WhatsApp > API Setup page first.';
    }

    return $detail;
}
