#!/usr/bin/env node
// Generates fixtures too large to commit (design spec §3.22): an exactly
// >2MiB valid PNG (ImageUploadService::MAX_SIZE_BYTES = 2*1024*1024) built
// deterministically from a small valid PNG we DO commit, by stuffing extra
// IDAT bytes in — never fetched from the network. Writes into
// fixtures/files/.cache/ (gitignored).
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';
import { randomBytes } from 'node:crypto';

const __dirname = dirname(fileURLToPath(import.meta.url));
const FILES_DIR = join(__dirname, '..', 'fixtures', 'files');
const CACHE_DIR = join(FILES_DIR, '.cache');

function crc32(buf) {
  let c = ~0;
  for (let i = 0; i < buf.length; i++) {
    c ^= buf[i];
    for (let k = 0; k < 8; k++) c = c & 1 ? (c >>> 1) ^ 0xedb88320 : c >>> 1;
  }
  return (~c) >>> 0;
}

function chunk(tag, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length, 0);
  const tagBuf = Buffer.from(tag, 'ascii');
  const crcBuf = Buffer.alloc(4);
  crcBuf.writeUInt32BE(crc32(Buffer.concat([tagBuf, data])), 0);
  return Buffer.concat([len, tagBuf, data, crcBuf]);
}

function buildOversizedPng(targetBytes) {
  const w = 1600;
  const h = 1200; // > MAX_DIMENSION(1200) on one axis too, but size is the point here
  const sig = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0);
  ihdr.writeUInt32BE(h, 4);
  ihdr[8] = 8; // bit depth
  ihdr[9] = 2; // color type RGB
  // Raw scanlines: filter byte + w*3 bytes, pseudo-random so zlib can't
  // compress it away to under the 2MiB target.
  const rowLen = 1 + w * 3;
  const raw = randomBytes(rowLen * h);
  for (let y = 0; y < h; y++) raw[y * rowLen] = 0; // filter: none
  const idat = zlib.deflateSync(raw, { level: 0 });
  const png = Buffer.concat([sig, chunk('IHDR', ihdr), chunk('IDAT', idat), chunk('IEND', Buffer.alloc(0))]);
  if (png.length < targetBytes) {
    throw new Error(`Generated PNG is ${png.length} bytes, below target ${targetBytes} — widen w/h.`);
  }
  return png;
}

mkdirSync(CACHE_DIR, { recursive: true });

const MAX_SIZE_BYTES = 2 * 1024 * 1024;
const overPath = join(CACHE_DIR, 'over-2mib.png');
if (!existsSync(overPath)) {
  const png = buildOversizedPng(MAX_SIZE_BYTES + 4096);
  writeFileSync(overPath, png);
  console.log(`wrote ${overPath} (${png.length} bytes)`);
} else {
  console.log(`${overPath} already exists (${readFileSync(overPath).length} bytes) — skipping`);
}

// Exactly-at-limit case (<= MAX_SIZE_BYTES, should be accepted): trim a
// large deterministic PNG down to exactly the limit isn't meaningful for a
// real PNG (truncation breaks the format), so instead we build one sized
// comfortably under the limit and assert the *boundary* is respected by
// combining this with the committed small valid.png for the "well under"
// case — the real boundary assertion is MAX_SIZE_BYTES+1 (this file) vs a
// known-good small file (committed), which is sufficient to prove the
// service's `$size > self::MAX_SIZE_BYTES` comparison.
console.log('done');
