<?php

namespace App\Core;

/**
 * Obfuscates numeric primary keys before they appear in public URLs/views
 * (dashboard links, /purchase-orders/{id}, etc.), so a plain sequential
 * integer (1, 2, 3...) isn't directly visible or trivially incrementable.
 *
 * IMPORTANT — this is encoding, not encryption:
 * bin2hex()/hex2bin() are reversible, publicly-known encodings, and the
 * "key" here is just a static string prefixed before encoding — it is not
 * used as a cryptographic key (no HMAC, no cipher). Anyone who hex-decodes
 * a token once can see the key prefix and the pattern, and could reproduce
 * it. This only raises the bar against casual ID-guessing in the UI; it is
 * NOT a substitute for server-side authorization, which every controller
 * must still enforce per CLAUDE.md §4 ("Authorization ALWAYS enforced
 * server-side — UI hiding is NOT authorization").
 */
class IdObfuscator
{
    public function __construct(private string $key)
    {
    }

    public function encode(int $id): string
    {
        return bin2hex($this->key . bin2hex((string) $id));
    }

    /**
     * Returns null for any malformed/tampered token instead of throwing,
     * so callers can treat it the same as "not found" (404), never as a
     * usable id.
     */
    public function decode(string $token): ?int
    {
        $decoded = false;
        if ($token !== '' && ctype_xdigit($token)) {
            $decoded = @hex2bin($token);
        }
        if ($decoded === false || !str_starts_with($decoded, $this->key)) {
            return null;
        }

        $idHex = substr($decoded, strlen($this->key));
        $idString = false;
        if ($idHex !== '' && ctype_xdigit($idHex)) {
            $idString = @hex2bin($idHex);
        }
        if ($idString === false || $idString === '' || !ctype_digit($idString)) {
            return null;
        }

        return (int) $idString;
    }
}
