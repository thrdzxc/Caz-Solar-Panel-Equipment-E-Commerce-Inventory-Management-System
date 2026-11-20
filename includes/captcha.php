<?php
/** Local, session-bound human verification. No answer is sent as page text. */
declare(strict_types=1);

function caz_captcha_issue(string $purpose): array {
    if (!in_array($purpose, ['login', 'register'], true)) throw new InvalidArgumentException('Invalid challenge purpose.');
    $now = time();
    $challenges = $_SESSION['caz_captcha_challenges'] ?? [];
    foreach ($challenges as $id => $challenge) {
        if (($challenge['expires'] ?? 0) < $now) unset($challenges[$id]);
    }
    while (count($challenges) >= 6) array_shift($challenges);
    $groups = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abdefghjmnqrt', '23456789'];
    $alphabet = implode('', $groups);
    $letters = [];
    foreach ($groups as $group) $letters[] = $group[random_int(0, strlen($group) - 1)];
    for ($i = 0; $i < 3; $i++) $letters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
    for ($i = count($letters) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$letters[$i],$letters[$j]] = [$letters[$j],$letters[$i]]; }
    $answer = implode('', $letters);
    $id = bin2hex(random_bytes(24));
    $challenges[$id] = ['answer' => $answer, 'purpose' => $purpose, 'expires' => $now + 300];
    $_SESSION['caz_captcha_challenges'] = $challenges;
    if (empty($_SESSION['caz_captcha_refresh'])) $_SESSION['caz_captcha_refresh'] = bin2hex(random_bytes(32));
    return ['id' => $id, 'token' => $_SESSION['caz_captcha_refresh']];
}

function caz_captcha_verify(string $purpose, $id, $answer): ?string {
    $now = time();
    $challenge = is_string($id) ? ($_SESSION['caz_captcha_challenges'][$id] ?? null) : null;
    // Every attempt consumes its challenge, including incorrect and expired answers.
    if (is_string($id)) unset($_SESSION['caz_captcha_challenges'][$id]);
    if (($_SESSION['caz_captcha_locked_until'] ?? 0) > $now) {
        return 'Please wait 30 seconds, then refresh the verification code and try again.';
    }
    $valid = is_array($challenge) && ($challenge['purpose'] ?? '') === $purpose
        && ($challenge['expires'] ?? 0) >= $now && is_string($answer)
        && preg_match('/^[A-Za-z2-9]{6}$/D', trim($answer))
        && hash_equals($challenge['answer'], trim($answer));
    if ($valid) {
        unset($_SESSION['caz_captcha_failures'], $_SESSION['caz_captcha_locked_until']);
        return null;
    }
    $_SESSION['caz_captcha_failures'] = (int)($_SESSION['caz_captcha_failures'] ?? 0) + 1;
    if ($_SESSION['caz_captcha_failures'] >= 10) {
        $_SESSION['caz_captcha_locked_until'] = $now + 30;
        $_SESSION['caz_captcha_failures'] = 0;
    }
    return 'Please enter the six characters in the new verification image. Codes expire after 5 minutes and can be used once.';
}

function caz_captcha_form(string $purpose): void {
    $challenge = caz_captcha_issue($purpose);
    $id = htmlspecialchars($challenge['id'], ENT_QUOTES, 'UTF-8');
    $token = htmlspecialchars($challenge['token'], ENT_QUOTES, 'UTF-8');
    echo '<fieldset class="caz-captcha full-width" data-purpose="' . $purpose . '" data-refresh-token="' . $token . '">'
        . '<legend>Human verification</legend>'
        . '<p id="captcha-help">Type the six characters exactly as shown: uppercase letters, lowercase letters and numbers.</p>'
        . '<div class="captcha-image-row"><img class="captcha-image" src="captcha.php?id=' . $id . '" width="276" height="84" alt="Verification code containing six letters and numbers" draggable="false">'
        . '<button type="button" class="captcha-refresh">New code</button></div>'
        . '<input type="hidden" name="captcha_id" value="' . $id . '">'
        . '<label for="captcha-answer">Verification code</label>'
        . '<input id="captcha-answer" name="captcha_answer" type="text" minlength="6" maxlength="6" pattern="[A-Za-z2-9]{6}" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="captcha-help captcha-status" placeholder="Enter the 6 characters" required>'
        . '<p id="captcha-status" class="captcha-status" role="status" aria-live="polite">Can’t read the image? Choose New code.</p>'
        . '<noscript><p>Refresh this page to get a different code.</p></noscript></fieldset>';
}

/** Portable PNG renderer: works with standard PHP/zlib even when GD is disabled. */
function caz_captcha_png(string $answer): string {
    $font = [
        'A'=>['01110','10001','10001','11111','10001','10001','10001'],
        'B'=>['11110','10001','10001','11110','10001','10001','11110'],
        'C'=>['01111','10000','10000','10000','10000','10000','01111'],
        'D'=>['11110','10001','10001','10001','10001','10001','11110'],
        'E'=>['11111','10000','10000','11110','10000','10000','11111'],
        'F'=>['11111','10000','10000','11110','10000','10000','10000'],
        'G'=>['01111','10000','10000','10111','10001','10001','01111'],
        'H'=>['10001','10001','10001','11111','10001','10001','10001'],
        'J'=>['00111','00010','00010','00010','10010','10010','01100'],
        'K'=>['10001','10010','10100','11000','10100','10010','10001'],
        'L'=>['10000','10000','10000','10000','10000','10000','11111'],
        'M'=>['10001','11011','10101','10101','10001','10001','10001'],
        'N'=>['10001','11001','11001','10101','10011','10011','10001'],
        'P'=>['11110','10001','10001','11110','10000','10000','10000'],
        'Q'=>['01110','10001','10001','10001','10101','10010','01101'],
        'R'=>['11110','10001','10001','11110','10100','10010','10001'],
        'S'=>['01111','10000','10000','01110','00001','00001','11110'],
        'T'=>['11111','00100','00100','00100','00100','00100','00100'],
        'U'=>['10001','10001','10001','10001','10001','10001','01110'],
        'V'=>['10001','10001','10001','10001','10001','01010','00100'],
        'W'=>['10001','10001','10001','10101','10101','11011','10001'],
        'X'=>['10001','10001','01010','00100','01010','10001','10001'],
        'Y'=>['10001','10001','01010','00100','00100','00100','00100'],
        'Z'=>['11111','00001','00010','00100','01000','10000','11111'],
        '2'=>['01110','10001','00001','00010','00100','01000','11111'],
        '3'=>['11110','00001','00001','01110','00001','00001','11110'],
        '4'=>['00010','00110','01010','10010','11111','00010','00010'],
        '5'=>['11111','10000','10000','11110','00001','00001','11110'],
        '6'=>['01110','10000','10000','11110','10001','10001','01110'],
        '7'=>['11111','00001','00010','00100','01000','01000','01000'],
        '8'=>['01110','10001','10001','01110','10001','10001','01110'],
        '9'=>['01110','10001','10001','01111','00001','00001','01110'],
    ];
    $font += [
        'a'=>['00000','00000','01110','00001','01111','10001','01111'],
        'b'=>['10000','10000','10110','11001','10001','10001','11110'],
        'd'=>['00001','00001','01101','10011','10001','10001','01111'],
        'e'=>['00000','00000','01110','10001','11111','10000','01110'],
        'f'=>['00110','01001','01000','11100','01000','01000','01000'],
        'g'=>['00000','01111','10001','10001','01111','00001','01110'],
        'h'=>['10000','10000','10110','11001','10001','10001','10001'],
        'j'=>['00010','00000','00110','00010','00010','10010','01100'],
        'm'=>['00000','00000','11010','10101','10101','10101','10101'],
        'n'=>['00000','00000','10110','11001','10001','10001','10001'],
        'q'=>['00000','01101','10011','10001','01111','00001','00001'],
        'r'=>['00000','00000','10110','11001','10000','10000','10000'],
        't'=>['01000','01000','11100','01000','01000','01001','00110'],
    ];
    $width = 276; $height = 84;
    $rows = array_fill(0, $height, str_repeat("\xE9\xFA\xFA", $width));
    $pixel = static function (int $x, int $y, string $rgb) use (&$rows, $width, $height): void {
        if ($x < 0 || $x >= $width || $y < 0 || $y >= $height) return;
        $offset = $x * 3;
        $rows[$y][$offset] = $rgb[0]; $rows[$y][$offset + 1] = $rgb[1]; $rows[$y][$offset + 2] = $rgb[2];
    };
    // Subtle background dots; dark, large glyphs remain easy to distinguish.
    for ($i = 0; $i < 450; $i++) $pixel(random_int(0, $width - 1), random_int(0, $height - 1), "\xAD\xD5\xD8");
    foreach (str_split($answer) as $index => $letter) {
        $originX = 16 + $index * 43; $originY = random_int(17, 23); $slant = random_int(-2, 2);
        foreach ($font[$letter] as $row => $bits) for ($column = 0; $column < 5; $column++) {
            if ($bits[$column] !== '1') continue;
            for ($dy = 0; $dy < 6; $dy++) for ($dx = 0; $dx < 6; $dx++) {
                $pixel($originX + $column * 6 + $dx + (int)round(($row - 3) * $slant / 2), $originY + $row * 6 + $dy, "\x0B\x48\x52");
            }
        }
    }
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    $raw = ''; foreach ($rows as $row) $raw .= "\0" . $row;
    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw, 6)) . $chunk('IEND', '');
}
