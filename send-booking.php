<?php
// Endpoint pentru formularul de programari catharsisgalati.ro.
// Trimite cererea pe emailul cabinetului. Antispam: honeypot, timp minim
// de completare, limita per IP, validare server-side.

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo '{"ok":false,"err":"method"}';
  exit;
}

// Doar de pe site-ul nostru (daca browserul trimite antetul Origin)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
  $oh = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
  if (!in_array($oh, array('catharsisgalati.ro', 'www.catharsisgalati.ro', 'localhost'), true)) {
    http_response_code(403);
    echo '{"ok":false,"err":"origin"}';
    exit;
  }
}

$d = json_decode(file_get_contents('php://input'), true);
if (!is_array($d)) {
  http_response_code(400);
  echo '{"ok":false,"err":"body"}';
  exit;
}

$txt = function ($k, $max) use ($d) {
  $v = isset($d[$k]) ? trim((string)$d[$k]) : '';
  $v = str_replace(array("\r", "\n"), ' ', $v); // fara injectie de antete
  return mb_substr($v, 0, $max, 'UTF-8');
};

// 1) Honeypot: camp invizibil pentru oameni; daca e completat, e bot.
//    Raspundem cu succes fals ca botul sa nu reincerce.
if ($txt('website', 40) !== '') { echo '{"ok":true}'; exit; }

// 2) Completare suspect de rapida (sub 3 secunde de la incarcarea paginii)
$elapsed = isset($d['elapsed']) ? (int)$d['elapsed'] : -1;
if ($elapsed >= 0 && $elapsed < 3000) { echo '{"ok":true}'; exit; }

$nume     = $txt('nume', 80);
$telefon  = $txt('telefon', 20);
$serviciu = $txt('serviciu', 60);
$medic    = $txt('medic', 60);
$zi       = $txt('zi', 20);
$interval = $txt('interval', 40);
$mesaj    = isset($d['mesaj']) ? mb_substr(trim((string)$d['mesaj']), 0, 1000, 'UTF-8') : '';

$phDigits = preg_replace('/[^0-9+]/', '', $telefon);
if ($nume === '' || !preg_match('/^(\+40|0040|0)7\d{8}$/', $phDigits)) {
  http_response_code(422);
  echo '{"ok":false,"err":"invalid"}';
  exit;
}

try {

// 3) Limita: maximum 5 cereri pe ora de la acelasi IP
$dir = __DIR__ . '/../.booking-rate';
if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
if (!is_dir($dir) || !is_writable($dir)) { $dir = sys_get_temp_dir(); }
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'necunoscut';
$ipFile = $dir . '/cg-rate-' . md5($ip);
$hits = array();
if (is_file($ipFile)) {
  foreach (file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $t = (int)$line;
    if ($t > time() - 3600) { $hits[] = $t; }
  }
}
if (count($hits) >= 5) {
  http_response_code(429);
  echo '{"ok":false,"err":"rate"}';
  exit;
}
$hits[] = time();
@file_put_contents($ipFile, implode("\n", $hits), LOCK_EX);

// Compune si trimite emailul
$en = (isset($d['lang']) && $d['lang'] === 'en');
$to = 'catharsis.galati@gmail.com';
$subject = 'Solicitare programare — ' . $nume;
$body = 'Nume și prenume: ' . $nume . "\n"
      . 'Telefon: ' . $telefon . "\n"
      . 'Serviciu dorit: ' . ($serviciu !== '' ? $serviciu : '—') . "\n"
      . 'Medic / psiholog preferat: ' . ($medic !== '' ? $medic : 'Fără preferință') . "\n"
      . 'Zi preferată: ' . ($zi !== '' ? $zi : '—') . "\n"
      . 'Interval orar: ' . ($interval !== '' ? $interval : 'Oricând') . "\n"
      . 'Mesaj: ' . ($mesaj !== '' ? $mesaj : '—') . "\n"
      . 'Limba formularului: ' . ($en ? 'engleză' : 'română') . "\n\n"
      . '— trimis automat de pe catharsisgalati.ro (IP: ' . $ip . ', ' . date('d.m.Y H:i') . ")\n";

$headers = 'From: Programari Catharsis <programari@catharsisgalati.ro>' . "\r\n"
         . 'Reply-To: catharsis.galati@gmail.com' . "\r\n"
         . 'Cc: catharsisgalati@yahoo.com' . "\r\n"
         . 'MIME-Version: 1.0' . "\r\n"
         . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
         . 'Content-Transfer-Encoding: 8bit';

$encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
if (!function_exists('mail')) { throw new Exception('functia mail() nu exista / e dezactivata'); }
$ok = @mail($to, $encSubject, $body, $headers, '-fprogramari@catharsisgalati.ro');
if (!$ok) { $ok = @mail($to, $encSubject, $body, $headers); }

echo json_encode(array('ok' => (bool)$ok));

} catch (Throwable $e) {
  @error_log('[send-booking] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  $dbg = (isset($d['debug']) && $d['debug'] === 'cg2026');
  echo json_encode(array('ok' => false, 'err' => 'server', 'msg' => $dbg ? ($e->getMessage() . ' @ linia ' . $e->getLine()) : null));
}
