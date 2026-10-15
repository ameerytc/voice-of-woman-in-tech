<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';
function ask(string $prompt, bool $hidden = false): string {
    echo $prompt . ': ';
    if ($hidden && DIRECTORY_SEPARATOR === '\\') {
        $v = trim((string)shell_exec('powershell -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"'));
        return $v;
    }
    if ($hidden) system('stty -echo 2>/dev/null');
    $v = trim((string)fgets(STDIN));
    if ($hidden) { system('stty echo 2>/dev/null'); echo "\n"; }
    return $v;
}
