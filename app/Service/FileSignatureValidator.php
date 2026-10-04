<?php

namespace App\Service;

use App\Core\Result;

// Pure, stateless upload checks ported from the DMS FileValidationTrait concept:
//   1. extension allow-list      (sanitizeExstension)
//   2. dangerous double-extension (sanitizeExstension double-ext intent)
//   3. magic-byte header match    (validateFileHeader)
//   4. magic-byte footer match    (validateFileFooter)
// The allow-list + signatures are supplied by the caller (DB-backed, cached by
// FileValidationService). This class holds only logic + the security policy
// constant below, so it is fully unit-testable without a DB, a cache, or a real
// HTTP upload.
class FileSignatureValidator
{
    public const MESSAGE_INVALID_TYPE = 'Only JPEG, PNG, or WebP images are allowed.';
    public const MESSAGE_DOUBLE_EXTENSION = 'The file name has multiple extensions, which is not allowed.';
    public const MESSAGE_SIGNATURE_MISMATCH = 'The file content does not match its extension. Please upload a genuine image.';
    public const MESSAGE_SUSPICIOUS_FOOTER = 'The file appears to be corrupted or tampered with and was rejected.';
    public const MESSAGE_RULES_UNAVAILABLE = 'The image could not be processed. Please try again.';

    // Executable/script extensions that must never appear anywhere in an upload
    // name — this is what makes `evil.php.jpg` a rejected double-extension while
    // a legitimate `my.photo.jpg` passes. Kept in code (not the DB) on purpose:
    // it is security policy, and it must stay enforced even when the DB or cache
    // is unavailable.
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht',
        'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl', 'py', 'rb',
        'js', 'mjs', 'jsp', 'asp', 'aspx', 'jar', 'war', 'dll', 'so',
        'html', 'htm', 'xhtml', 'svg', 'xml', 'shtml',
    ];

    // Returns a Result: CODE_SUCCESS when the file passes every check, otherwise
    // CODE_VALIDATION with a user-facing message. $rules is the allow-list keyed
    // by lowercase extension (see FileValidationRepositoryInterface).
    public static function validate($filename, $bytes, array $rules)
    {
        $result = new Result();
        $error = self::firstError((string) $filename, (string) $bytes, $rules);

        if ($error === '') {
            $result->code = Result::CODE_SUCCESS;
            $result->info = 'The file name and signature are valid';
            $result->data = null;
        } else {
            $result->code = Result::CODE_VALIDATION;
            $result->info = $error;
            $result->data = null;
        }

        return $result;
    }

    // Returns the first failing check's message, or '' when every check passes.
    // Checks run in order of specificity so the user sees the most actionable
    // message: rules availability → extension allow-list → double-extension →
    // magic-byte signature.
    private static function firstError($filename, $bytes, array $rules)
    {
        if ($rules === []) {
            return self::MESSAGE_RULES_UNAVAILABLE;
        }

        $extension = self::extensionOf($filename);

        $error = '';
        if (!isset($rules[$extension])) {
            $error = self::MESSAGE_INVALID_TYPE;
        } elseif (self::hasDangerousDoubleExtension($filename)) {
            $error = self::MESSAGE_DOUBLE_EXTENSION;
        } else {
            $error = self::signatureError($bytes, $rules[$extension]);
        }

        return $error;
    }

    public static function extensionOf($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    // True when any segment of the name — other than the base name and the final
    // extension — is a known executable/script extension (e.g. the "php" in
    // "evil.php.jpg"). Legitimate dotted names like "my.photo.jpg" pass because
    // "photo" is not in DANGEROUS_EXTENSIONS.
    public static function hasDangerousDoubleExtension($filename)
    {
        $basename = pathinfo($filename, PATHINFO_BASENAME);
        $segments = explode('.', $basename);

        $isDangerous = false;
        if (count($segments) > 2) {
            // Drop the leading name and the trailing (already allow-listed) extension.
            $innerSegments = array_slice($segments, 1, -1);
            foreach ($innerSegments as $segment) {
                if (in_array(strtolower($segment), self::DANGEROUS_EXTENSIONS, true)) {
                    $isDangerous = true;
                    break;
                }
            }
        }

        return $isDangerous;
    }

    // Returns '' when the bytes match the rule's header/footer, otherwise the
    // message describing the mismatch. A NULL header/footer in the rule skips
    // that check (e.g. WebP has no simple footer).
    private static function signatureError($bytes, array $rule)
    {
        $error = '';

        $headerHex = $rule['header_hex'] ?? null;
        if ($error === '' && $headerHex !== null && $headerHex !== '') {
            $readBytes = (int) ($rule['read_bytes'] ?? 0);
            if ($readBytes <= 0) {
                $readBytes = (int) (strlen($headerHex) / 2);
            }
            $actualHeader = strtoupper(bin2hex(substr($bytes, 0, $readBytes)));
            if (strpos($actualHeader, $headerHex) !== 0) {
                $error = self::MESSAGE_SIGNATURE_MISMATCH;
            }
        }

        $footerHex = $rule['footer_hex'] ?? null;
        if ($error === '' && $footerHex !== null && $footerHex !== '') {
            // Tolerate a little trailing metadata (as DMS does): the footer must
            // appear within the last 64 bytes rather than being the exact tail.
            $tailHex = strtoupper(bin2hex(substr($bytes, -64)));
            if (strpos($tailHex, $footerHex) === false) {
                $error = self::MESSAGE_SUSPICIOUS_FOOTER;
            }
        }

        return $error;
    }
}
