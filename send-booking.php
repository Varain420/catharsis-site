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

// mail() e dezactivat pe acest server -> trimitem prin SMTP autentificat,
// cu contul creat in cPanel. Datele de conectare stau in afara webroot-ului.
$cfgFile = __DIR__ . '/../.booking-smtp.json';
if (!is_file($cfgFile)) { throw new Exception('lipseste .booking-smtp.json (configurare SMTP)'); }
$cfg = json_decode(file_get_contents($cfgFile), true);
if (!is_array($cfg) || empty($cfg['user']) || empty($cfg['pass'])) { throw new Exception('.booking-smtp.json incomplet'); }
$smtpHost = isset($cfg['host']) ? $cfg['host'] : 'localhost';
$smtpPort = isset($cfg['port']) ? (int)$cfg['port'] : 465;
$from = $cfg['user'];
$rcpts = array($to, 'catharsisgalati@yahoo.com');

$encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$msgHeaders = 'From: Programari Catharsis <' . $from . '>' . "\r\n"
            . 'To: ' . $to . "\r\n"
            . 'Cc: catharsisgalati@yahoo.com' . "\r\n"
            . 'Reply-To: catharsis.galati@gmail.com' . "\r\n"
            . 'Subject: ' . $encSubject . "\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'MIME-Version: 1.0' . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
            . 'Content-Transfer-Encoding: 8bit' . "\r\n";

$ctx = stream_context_create(array('ssl' => array(
  'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
)));
$prefix = ($smtpPort === 465) ? 'ssl://' : '';
$sock = @stream_socket_client($prefix . $smtpHost . ':' . $smtpPort, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if (!$sock) { throw new Exception('conexiune SMTP esuata: ' . $errstr); }
stream_set_timeout($sock, 15);

$read = function () use ($sock) {
  $resp = '';
  while (($line = fgets($sock, 1024)) !== false) {
    $resp .= $line;
    if (strlen($line) < 4 || $line[3] !== '-') { break; }
  }
  return $resp;
};
$cmd = function ($c, $expect) use ($sock, $read) {
  fwrite($sock, $c . "\r\n");
  $r = $read();
  if (strpos($r, (string)$expect) !== 0) { throw new Exception('SMTP "' . substr($c, 0, 12) . '..." a raspuns: ' . trim(substr($r, 0, 120))); }
  return $r;
};

$greet = $read();
if (strpos($greet, '220') !== 0) { throw new Exception('SMTP salut neasteptat: ' . trim(substr($greet, 0, 120))); }
$cmd('EHLO catharsisgalati.ro', '250');
if ($smtpPort === 587) { $cmd('STARTTLS', '220'); stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $cmd('EHLO catharsisgalati.ro', '250'); }
$cmd('AUTH LOGIN', '334');
$cmd(base64_encode($cfg['user']), '334');
$cmd(base64_encode($cfg['pass']), '235');
$cmd('MAIL FROM:<' . $from . '>', '250');
foreach ($rcpts as $r) { $cmd('RCPT TO:<' . $r . '>', '250'); }
$cmd('DATA', '354');
$data = $msgHeaders . "\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
fwrite($sock, $data . "\r\n.\r\n");
$fin = $read();
fwrite($sock, "QUIT\r\n");
fclose($sock);
if (strpos($fin, '250') !== 0) { throw new Exception('SMTP nu a acceptat mesajul: ' . trim(substr($fin, 0, 120))); }

echo '{"ok":true}';

} catch (Throwable $e) {
  @error_log('[send-booking] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  $dbg = (isset($d['debug']) && $d['debug'] === 'cg2026');
  echo json_encode(array('ok' => false, 'err' => 'server', 'msg' => $dbg ? ($e->getMessage() . ' @ linia ' . $e->getLine()) : null));
}
