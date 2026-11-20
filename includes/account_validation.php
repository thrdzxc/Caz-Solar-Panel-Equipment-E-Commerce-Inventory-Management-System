<?php
/** Rules for newly supplied account details; existing credentials are unchanged. */
function caz_account_name_valid(string $name): bool {
    return mb_strlen($name, 'UTF-8') <= 100
        && preg_match('/^\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)*$/uD', $name) === 1;
}

function caz_account_phone_valid(string $phone): bool {
    return preg_match('/^09[0-9]{9}$/D', $phone) === 1;
}

function caz_account_password_error(string $password): ?string {
    if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 8) {
        return 'Create a password with at least 8 characters and a special character, such as !, @ or #.';
    }
    // Bcrypt accepts at most 72 bytes. Reject longer input instead of silently truncating it.
    if (strlen($password) > 72 || str_contains($password, "\0")) {
        return 'Keep your password within 72 bytes (up to 72 plain-text characters).';
    }
    if (preg_match('/[\p{P}\p{S}]/u', $password) !== 1) {
        return 'Add at least one special character to your password, such as !, @ or #. Spaces do not count.';
    }
    return null;
}
