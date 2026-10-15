<?php
declare(strict_types=1);

const AVATAR_MAX_BYTES = 3145728;   // 3 MB upload limit before processing

/** Validates an uploaded picture, crops it to a square and re-encodes it (removes EXIF/location data and any hidden payload). Returns [mime, bytes] or throws UserError. */
function process_avatar_upload(array $f): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new UserError('No picture selected.');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new UserError($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? 'The picture is too large (maximum 3 MB).' : 'The picture could not be uploaded. Please try again.');
    if (!is_uploaded_file($f['tmp_name']) || $f['size'] > AVATAR_MAX_BYTES) throw new UserError('The picture is too large (maximum 3 MB).');
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
    if (!$info || !isset($types[$info[2]])) throw new UserError('Please upload a JPG, PNG or WebP picture.');
    if ($info[0] < 32 || $info[1] < 32 || $info[0] * $info[1] > 40000000) throw new UserError('That picture size is not supported.');
    $raw = (string)file_get_contents($f['tmp_name']);
    if (!extension_loaded('gd')) {   // no image library: accept only small, validated originals
        if (strlen($raw) > 1048576) throw new UserError('The picture is too large for this server (maximum 1 MB).');
        return [$types[$info[2]], $raw];
    }
    $src = @imagecreatefromstring($raw);
    if (!$src) throw new UserError('That picture could not be read.');
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {   // honour camera rotation, then drop all metadata by re-encoding
        $ex = @exif_read_data($f['tmp_name']);
        $o = (int)($ex['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }
    $w = imagesx($src); $h = imagesy($src); $s = min($w, $h);
    $dst = imagecreatetruecolor(480, 480);
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $s, 2), intdiv($h - $s, 2), 480, 480, $s, $s);
    imagedestroy($src);
    ob_start(); imagejpeg($dst, null, 84); $out = (string)ob_get_clean(); imagedestroy($dst);
    return ['image/jpeg', $out];
}
function save_avatar(int $uid, string $mime, string $bytes): void {
    $sql = 'INSERT INTO user_avatars(user_id,mime,data,bytes,updated_at) VALUES(:u,:m,:d,:b,:t) ON CONFLICT(user_id) DO UPDATE SET mime=excluded.mime, data=excluded.data, bytes=excluded.bytes, updated_at=excluded.updated_at';
    $st = db()->prepare($sql);
    $fp = fopen('php://memory', 'r+'); fwrite($fp, $bytes); rewind($fp);   // BYTEA/BLOB must be sent as a stream (strings are mangled by pdo_pgsql)
    $len = strlen($bytes); $now = now();
    $st->bindValue(':u', $uid, PDO::PARAM_INT);
    $st->bindValue(':m', $mime);
    $st->bindParam(':d', $fp, PDO::PARAM_LOB);
    $st->bindValue(':b', $len, PDO::PARAM_INT);
    $st->bindValue(':t', $now);
    $st->execute();
    fclose($fp);
}
function load_avatar(int $uid): ?array {
    $st = db()->prepare('SELECT mime, data, updated_at FROM user_avatars WHERE user_id = ?');
    $st->execute([$uid]);
    $r = $st->fetch();
    if (!$r) return null;
    $d = $r['data'];
    if (is_resource($d)) $d = stream_get_contents($d);   // PostgreSQL returns BYTEA as a stream
    return ['mime' => $r['mime'], 'data' => (string)$d, 'updated_at' => $r['updated_at']];
}
function has_avatar(int $uid): bool { return (bool)db_val('SELECT 1 FROM user_avatars WHERE user_id=?', [$uid]); }
/** <img> or an initials badge when there is no picture. */
function avatar_html(array $u, int $size = 64, string $cls = ''): string {
    $id = (int)($u['id'] ?? $u['user_id'] ?? 0);
    $name = (string)($u['full_name'] ?? $u['participant_name'] ?? '?');
    $has = array_key_exists('has_avatar', $u) ? (bool)$u['has_avatar'] : false;
    if ($has) return '<img class="avatar ' . e($cls) . '" src="' . e(url('/avatar/' . $id)) . '?v=' . e((string)($u['avatar_v'] ?? '1')) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy" decoding="async">';
    $parts = preg_split('/\s+/', trim($name)) ?: ['?'];
    $ini = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    return '<span class="avatar avatar-ini ' . e($cls) . '" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size / 2.6) . 'px" aria-hidden="true">' . e($ini) . '</span>';
}
