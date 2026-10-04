// Upload fixtures (design spec §3.22 / FOUNDATION_CONTRACT.md §6). Deterministic
// local binaries only — nothing here ever touches the network. Run
// `node scripts/make-fixtures.mjs` once (or let auto-ensure below do it) to
// materialize the oversized case into fixtures/files/.cache (gitignored; too
// big to commit).
//
// IMPORTANT — validated against `app/Service/FileSignatureValidator.php` +
// the seeded `file_validation_rules` (database/seed.sql), which landed in
// this repo AFTER this suite's original research pass. Validation order is:
//   1. upload error / size (0 < size <= 2 MiB)
//   2. extension allow-list (jpg, jpeg, png, webp only — database/seed.sql)
//   3. dangerous double-extension (e.g. "evil.php.jpg" — any inner segment
//      matching FileSignatureValidator::DANGEROUS_EXTENSIONS is rejected,
//      independent of the outer extension's own validity)
//   4. magic-byte HEADER match for the claimed extension's rule
//   5. magic-byte FOOTER match (tolerant: must appear in the last 64 bytes,
//      not at the exact tail) — PNG/JPEG only; WebP has no footer rule
//   6. finfo MIME cross-check + getimagesize() sanity read (ImageUploadService)
// The extension is checked FIRST and its own signature is what gets
// validated — a real PNG file named ".jpg" is REJECTED (its header doesn't
// match the "jpg" rule's FFD8FF), not silently accepted. This supersedes an
// earlier (pre-FileSignatureValidator) assumption that extension was
// ignored entirely — see KNOWN_DEFECTS.md "post-merge correction" note.

import path from 'node:path';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const FILES_DIR = path.resolve(__dirname, '..', 'fixtures', 'files');
const SCRIPTS_DIR = path.resolve(__dirname, '..', 'scripts');

export const UPLOAD_FIXTURES = {
  /** Real PNG, well under limits — the baseline accepted case. */
  validPng: path.join(FILES_DIR, 'valid.png'),
  /** Real baseline JPEG. */
  validJpg: path.join(FILES_DIR, 'valid.jpg'),
  /** Real PNG bytes, named ".jpg" — REJECTED: extension "jpg" maps to the
   * FFD8FF/FFD9 rule, but the bytes are PNG's 89504E47 header, so this hits
   * FileSignatureValidator::MESSAGE_SIGNATURE_MISMATCH ("The file content
   * does not match its extension. Please upload a genuine image."). */
  signatureMismatch: path.join(FILES_DIR, 'wrong-extension.jpg'),
  /** Real JPEG bytes, named "double-extension.php.jpg" — REJECTED:
   * FileSignatureValidator::MESSAGE_DOUBLE_EXTENSION ("The file name has
   * multiple extensions, which is not allowed."), regardless of the outer
   * extension/signature both being otherwise valid. */
  dangerousDoubleExtension: path.join(FILES_DIR, 'double-extension.php.jpg'),
  /** Plain text, named ".png" — REJECTED at the same signature-mismatch
   * stage as `signatureMismatch` above (extension "png" is allow-listed,
   * but the bytes don't start with PNG's 89504E47 header). */
  textNamedPng: path.join(FILES_DIR, 'invalid-not-an-image.png'),
  /** Zero-byte file — rejected at the size check (size <= 0), before any
   * signature check runs. */
  empty: path.join(FILES_DIR, 'empty.png'),
  /** Real GIF — rejected: extension "gif" is not in the allow-list at all
   * (FileSignatureValidator::MESSAGE_INVALID_TYPE), never reaches a
   * signature check. */
  invalidGif: path.join(FILES_DIR, 'invalid.gif'),
  /** 2000x1500 real PNG, under the 2MiB size limit — exercises the
   * >MAX_DIMENSION(1200) scale-down path, not any rejection path. */
  oversizedDimensions: path.join(FILES_DIR, 'oversized-source.png'),
  /** >2MiB real PNG — rejected (size check). Generated on demand (not
   * committed) by scripts/make-fixtures.mjs. */
  overSizeLimit: path.join(FILES_DIR, '.cache', 'over-2mib.png'),
} as const;

/** Ensures generated (non-committed) fixtures exist, regenerating them
 * deterministically if missing. Call once from globalSetup or lazily from
 * the upload spec's own beforeAll. */
export async function ensureGeneratedFixtures(): Promise<void> {
  if (existsSync(UPLOAD_FIXTURES.overSizeLimit)) return;
  await execFileAsync('node', [path.join(SCRIPTS_DIR, 'make-fixtures.mjs')]);
}
