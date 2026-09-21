<?php
require dirname(__DIR__) . '/.tools/wordpress/wp-load.php';
if (wp_get_environment_type() !== 'local') { throw new RuntimeException('Local test environment required.'); }
$passed = 0;
function expect(bool $condition, string $label): void {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $passed++; echo "PASS: $label\n";
}
function sample_signature(): string {
    $im = imagecreatetruecolor(1200, 400); imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
    $ink = imagecolorallocate($im, 20, 40, 30); imagesetthickness($im, 4);
    for ($i = 0; $i < 400; $i++) { imageline($im, 100 + $i, (int)(170 + sin($i / 17) * 45), 101 + $i, (int)(170 + sin(($i + 1) / 17) * 45), $ink); }
    ob_start(); imagepng($im); $bytes = ob_get_clean(); imagedestroy($im);
    return 'data:image/png;base64,' . base64_encode($bytes);
}
function sample_data(): array {
    return ['tracking' => '00340434738212345678', 'carrier' => 'DHL', 'sender' => EE_Validation::SENDER_ADDRESS, 'recipient' => "Anna Müller\nGartenstraße 24\n20095 Hamburg", 'contents' => 'Eine dunkelblaue Jacke, Größe M.', 'delivery_to' => '', 'delivery_date' => '', 'first_name' => 'Anna', 'last_name' => 'Müller', 'address' => "Gartenstraße 24\n20095 Hamburg", 'email' => 'anna@example.test', 'phone' => '', 'received_date' => '', 'place' => 'Hamburg', 'date' => current_time('Y-m-d'), 'person' => 'ich', 'receipt' => 'not_received', 'unknown' => true, 'cod' => 'no', 'cod_payment' => '', 'confirmed' => true, 'signature' => sample_signature()];
}
function challenge(int $age = 5): string {
    $payload = base64_encode(wp_json_encode(['key' => bin2hex(random_bytes(32)), 'time' => time() - $age]));
    return $payload . '.' . hash_hmac('sha256', $payload, wp_salt('auth'));
}
function send_data(array $data) {
    $request = new WP_REST_Request('POST', '/empfaengererklaerung/v1/declarations');
    $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($data));
    return EE_Plugin::submit($request);
}
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ee_rate_%'");
$_SERVER['REMOTE_ADDR'] = '127.0.0.9';
$data = sample_data();
expect(!is_wp_error(EE_Validation::validate($data)), 'complete declaration validates');
foreach (['003404347382', '003404347382ABC123', '00340434123456789012', '003404347382 123', '003404347382-123', ' 003404347382123', "003404347382123\n", '<b>003404347382123</b>', 3404347382123] as $tracking) {
    $bad = $data; $bad['tracking'] = $tracking;
    $result = EE_Validation::validate($bad);
    expect(is_wp_error($result) && isset($result->get_error_data()['fields']['tracking']), 'invalid tracking format rejected: ' . json_encode($tracking));
}
expect(EE_Validation::validate($data)['tracking'] === $data['tracking'], 'tracking preserves leading zeros');
$fixed = $data; unset($fixed['sender']); expect(EE_Validation::validate($fixed)['sender'] === EE_Validation::SENDER_ADDRESS, 'sender supplied automatically when missing');
$fixed['sender'] = 'Manipulated sender'; expect(EE_Validation::validate($fixed)['sender'] === EE_Validation::SENDER_ADDRESS, 'customer cannot override fixed sender');
foreach (['tracking', 'carrier', 'recipient', 'contents', 'first_name', 'last_name', 'address', 'email', 'place', 'date'] as $key) {
    $bad = $data; $bad[$key] = ''; expect(is_wp_error(EE_Validation::validate($bad)), "required $key rejected");
}
foreach (['email' => 'bad@@example.test', 'date' => '2026-02-30', 'delivery_date' => '2099-01-01', 'person' => 'other', 'signature' => 'data:image/png;base64,AAAA', 'confirmed' => false, 'tracking' => str_repeat('x', 101), 'address' => ['bad']] as $key => $value) {
    $bad = $data; $bad[$key] = $value; expect(is_wp_error(EE_Validation::validate($bad)), "invalid $key rejected");
}
$blank = imagecreatetruecolor(1200,400); imagefill($blank,0,0,imagecolorallocate($blank,255,255,255)); ob_start(); imagepng($blank); $blankBytes = ob_get_clean(); imagedestroy($blank);
$bad = $data; $bad['signature'] = 'data:image/png;base64,' . base64_encode($blankBytes); expect(is_wp_error(EE_Validation::validate($bad)), 'blank PNG signature rejected');
$received = $data; $received['receipt'] = 'received'; expect(is_wp_error(EE_Validation::validate($received)), 'received requires date');
$received['received_date'] = current_time('Y-m-d'); $received = EE_Validation::validate($received); expect(!$received['unknown'], 'unknown whereabouts removed when received');
foreach (['courier', 'branch', 'unpaid'] as $payment) { $cod = $data; $cod['cod'] = 'yes'; $cod['cod_payment'] = $payment; expect(!is_wp_error(EE_Validation::validate($cod)), 'COD ' . $payment); }
$bad = $data; $bad['cod'] = 'yes'; expect(is_wp_error(EE_Validation::validate($bad)), 'COD requires payment choice');
expect(!str_contains(EE_Validation::statement($data), 'Nachnahme'), 'non-COD statement has no COD text');
$we = $data; $we['person'] = 'wir'; expect(str_contains(EE_Validation::statement($we), 'erhalten haben.'), 'plural wording');
expect(str_contains(EE_Validation::statement($data), 'erhalten habe.'), 'singular wording');
$artifacts = dirname(__DIR__) . '/.tools/artifacts'; @mkdir($artifacts, 0777, true);
$brand = ['company' => 'Paketservice', 'accent' => '#205c50', 'logo' => ''];
$pdf = EE_PDF::generate($data, $brand, 'EE-DEMO-20260908'); file_put_contents($artifacts . '/normal.pdf', $pdf);
expect(str_starts_with($pdf, '%PDF-'), 'Dompdf creates actual PDF');
$logo = imagecreatetruecolor(240,60); imagefill($logo,0,0,imagecolorallocate($logo,32,92,80)); imagestring($logo,5,20,20,'PAKETSERVICE',imagecolorallocate($logo,255,255,255)); ob_start(); imagepng($logo); $logoBytes = ob_get_clean(); imagedestroy($logo);
$brand['logo'] = 'data:image/png;base64,' . base64_encode($logoBytes); file_put_contents($artifacts . '/logo.pdf', EE_PDF::generate($received, $brand, 'EE-DEMO-LOGO'));
$long = $we; $long['contents'] = str_repeat('Große Bücher, Zubehör und sorgfältig verpackte Gegenstände. ', 24); $long['address'] = str_repeat('Weitere Adressangabe, Haus und Gebäude ', 12); $long['recipient'] = str_repeat('Empfangsabteilung und Ansprechpartner ', 12);
file_put_contents($artifacts . '/long.pdf', EE_PDF::generate($long, $brand, 'EE-DEMO-LANG'));
foreach (['courier', 'branch', 'unpaid'] as $payment) { $cod = $data; $cod['cod'] = 'yes'; $cod['cod_payment'] = $payment; file_put_contents($artifacts . '/cod-' . $payment . '.pdf', EE_PDF::generate($cod, $brand, 'EE-DEMO-' . strtoupper($payment))); }
expect(is_wp_error(send_data($data + ['session' => 'tampered'])), 'tampered session rejected');
expect(is_wp_error(send_data($data + ['session' => challenge(8000)])), 'expired session rejected');
expect(is_wp_error(send_data($data + ['session' => challenge(), 'website' => 'spam'])), 'honeypot rejected');
update_option('ee_test_mail_fail', false);
$mailSettings = EE_Plugin::settings();
$mailSettings['service_body'] = "E-Mail: {email}\nVersanddienstleister: {versanddienstleister}\nAbsender: {absender}\nAdressiert an: {empfaenger}\nSendungsinhalt: {sendungsinhalt}";
update_option('ee_settings', $mailSettings);
$sentMail = [];
add_filter('wp_mail', static function ($args) use (&$sentMail) {
    $sentMail[] = $args;
    return $args;
});
$submission = $data + ['session' => challenge()]; $result = send_data($submission);
expect($result instanceof WP_REST_Response, 'submission succeeds in real WordPress');
$output = $result->get_data(); $query = []; parse_str(wp_parse_url($output['download'], PHP_URL_QUERY), $query); $id = (int) $query['id'];
$row = EE_Store::get($id); expect($row->customer_status === 'handed_off' && $row->service_status === 'handed_off', 'separate mail statuses saved');
expect(hash_equals($row->token_hash, hash('sha256', $query['token'])), 'only hashed download token persisted');
expect(json_decode($row->data, true)['_signature'] === EE_Validation::validate($data)['signature'], 'validated signature retained in protected archive');
expect(count($sentMail) === 2 && in_array('Reply-To: ' . $data['email'], $sentMail[0]['headers'], true) && count($sentMail[1]['headers']) === 1, 'service mail replies to customer; customer copy has no reply-to');
expect(str_contains($sentMail[0]['message'], 'E-Mail: ' . $data['email']) && str_contains($sentMail[0]['message'], 'Versanddienstleister: DHL') && str_contains($sentMail[0]['message'], 'Sendungsinhalt: ' . $data['contents']) && !str_contains($sentMail[0]['message'], '{'), 'saved service template fields are expanded');
$duplicate = send_data($submission); expect($duplicate->get_data()['reference'] === $output['reference'], 'retry returns same declaration');
$changed = $submission; $changed['contents'] = 'Changed'; expect(is_wp_error(send_data($changed)) && send_data($changed)->get_error_code() === 'already_submitted', 'changed payload cannot overwrite signed declaration');
$bytesBefore = $row->pdf; $settings = EE_Plugin::settings(); $settings['company'] = 'Changed branding'; update_option('ee_settings', $settings); expect(EE_Store::get($id)->pdf === $bytesBefore, 'settings preserve original PDF'); $settings['company'] = 'Paketservice'; update_option('ee_settings', $settings);
update_option('ee_test_mail_fail', true); $failed = send_data($data + ['session' => challenge()]); expect($failed instanceof WP_REST_Response, 'mail failure does not lose declaration');
$failedOut = $failed->get_data(); parse_str(wp_parse_url($failedOut['download'], PHP_URL_QUERY), $query); $failedId = (int) $query['id']; expect(EE_Store::get($failedId)->customer_status === 'failed' && EE_Store::get($failedId)->service_status === 'failed', 'mail failure recorded separately');
update_option('ee_test_mail_fail', false); EE_Store::send($failedId, 'customer', true); expect(EE_Store::get($failedId)->customer_status === 'handed_off' && EE_Store::get($failedId)->service_status === 'failed', 'targeted resend succeeds');
$originalRow = EE_Store::get($id); $revision = hash('sha256', $originalRow->data);
$edit = $data; unset($edit['signature']); $edit['contents'] = 'Korrigierter Inhalt'; $edit['email'] = 'corrected@example.test'; $edit['tracking'] = '00340434738299999999';
expect(is_wp_error(EE_Store::revise($id, $edit, $revision)), 'anonymous cannot revise');
wp_set_current_user(get_user_by('login', 'ee-admin')->ID);
$badEdit = $edit; $badEdit['tracking'] = 'LETTERS'; expect(is_wp_error(EE_Store::revise($id, $badEdit, $revision)), 'backend revision validates tracking');
expect(EE_Store::get($id)->data === $originalRow->data, 'failed revision leaves data untouched');
expect(EE_Store::revise($id, $edit, $revision) === true, 'administrator can revise using original signature');
$revisedRow = EE_Store::get($id); $stored = json_decode($revisedRow->data, true); $correction = $stored['_correction'];
expect($correction['contents'] === 'Korrigierter Inhalt' && $revisedRow->tracking === $edit['tracking'] && $revisedRow->customer_email === $edit['email'], 'revised fields and searchable columns saved');
expect($revisedRow->pdf === $originalRow->pdf && $stored['contents'] === $data['contents'] && $stored['email'] === $data['email'], 'original PDF and original fields preserved');
expect($revisedRow->payload_hash === $originalRow->payload_hash && $revisedRow->token_hash === $originalRow->token_hash && $revisedRow->customer_status === $originalRow->customer_status, 'revision preserves retries tokens and mail status');
expect(!isset($correction['signature']) && !isset($correction['confirmed']), 'correction keeps signature only in protected original data');
$html = EE_PDF::html($correction + ['signature' => $stored['_signature']], $brand, $revisedRow->reference);
expect(str_contains($html, 'nicht erneut unterschrieben') && str_contains($html, 'Unterschrift aus dem Original übernommen') && str_contains($html, '<img class="signature"'), 'correction PDF includes original signature and clear revision notice');
file_put_contents($artifacts . '/revised.pdf', base64_decode($correction['_pdf']));
expect(str_starts_with(base64_decode($correction['_pdf']), '%PDF-'), 'correction stores real PDF');
expect(is_wp_error(EE_Store::revise($id, $edit, $revision)), 'stale editor cannot overwrite newer revision');
$edit['contents'] = 'Zweite Überarbeitung'; expect(EE_Store::revise($id, $edit, hash('sha256', $revisedRow->data)) === true, 'current revision can be edited again');
expect(json_decode(EE_Store::get($id)->data, true)['_correction']['_revision_number'] === 2, 'revision number incremented');
expect(send_data($submission)->get_data()['reference'] === $output['reference'], 'original submission remains idempotent after revision');
wp_set_current_user(0);
expect(EE_Store::remove([$id, $failedId]) === 2 && !EE_Store::get($id) && !EE_Store::get($failedId), 'bulk delete removes rows and PDFs');
$request = new WP_REST_Request('POST'); $request->set_header('Origin', 'https://attacker.example'); expect(is_wp_error(EE_Plugin::session($request)), 'foreign origin rejected');
$request = new WP_REST_Request('POST'); $_SERVER['REMOTE_ADDR'] = '127.0.0.88';
for ($i = 0; $i < 60; $i++) { EE_Plugin::session($request); }
$limited = EE_Plugin::session($request); expect(is_wp_error($limited) && $limited->get_error_code() === 'rate', 'session rate limit enforced');
$subscriber = get_user_by('login', 'ee-reader'); wp_set_current_user($subscriber->ID); expect(!current_user_can('manage_ee_declarations'), 'subscriber cannot manage declarations'); wp_set_current_user(0);
echo "\n$passed assertions passed.\n";
