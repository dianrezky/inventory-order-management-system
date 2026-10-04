# ADR-003: i18n Library — i18next-core (UMD via CDN/local)

> **Status update (2026-09-08, CF-02):** I18N-01 (and THEME-01) were declared **OUT OF SCOPE** for the
> current release — see `master-project-specification.md` §46 CF-02 and
> `docs/design/DESIGN_DECISION_RECORD_B01_B09.md` §B-02. Reason: `i18next` is a third-party JS library
> outside the brief's allowed frontend list (Fetch API + icon library only). The pre-existing
> `i18next` implementation was not removed, but it is not a graded requirement and there is no
> EN/ID locale toggle in the shipped UI (`ux-ui-spec.md` §3 was amended to drop it, v1.3). This ADR is
> kept as a historical record of the original (superseded) decision below.

- **Status:** Accepted, then superseded by out-of-scope decision (see note above)
- **Date:** 2026-09-01
- **Stage:** 4 / 9 (Technical Design)
- **Related Requirements:** I18N-01 (PRD §10), BR-020 (no hardcoded UI strings)
- **Related Vision:** §4.2 #5, §5.1, §6.2 E9, §14

---

## Context (Konteks)

Aplikasi harus multi-bahasa (EN default, ID alternatip) untuk melayani 4 persona termasuk Grace (expat English). Semua label UI, pesan validasi, header laporan, dan empty state wajib lewat i18n key — tidak boleh hardcoded.

**Constraint utama:**
- Frontend hanya boleh HTML + CSS + Vanilla JS + Fetch API (CLAUDE.md Rule #9, Brief §2).
- Tidak boleh pakai framework (React/Vue/Angular) atau CSS framework (Tailwind, Bootstrap).
- i18n library harus ringan dan framework-agnostic (UMD distribution).
- Total UI asset target < 100 KB (E11 metric).

**Library yang dievaluasi oleh user di Vision session:**
1. **i18next-core** + **i18next-http-backend** (UMD, framework-agnostic) ← **DITERIMA**
2. FormatJS / react-intl (butuh React) — ditolak karena framework lock
3. Custom JS dictionary (sederhana tapi reinvent the wheel) — opsi fallback

---

## Decision (Keputusan)

Kami menggunakan **`i18next-core` + `i18next-http-backend`** dalam mode **UMD** (script tag di HTML, expose global `i18next`).

### 1. Struktur File

```
public/assets/
├── vendor/
│   ├── i18next.min.js              ← UMD bundle (~15 KB gzip)
│   └── i18nextHttpBackend.min.js   ← lazy-load backend (~3 KB gzip)
└── locales/
    ├── en/
    │   └── translation.json
    └── id/
        └── translation.json
```

Total ~18 KB (gzip). Memenuhi constraint E11 (total UI asset < 100 KB).

### 2. Inisialisasi

`public/assets/js/i18n-init.js` (vanilla JS):

```javascript
window.addEventListener('DOMContentLoaded', async () => {
    let locale = localStorage.getItem('locale') 
                 || document.documentElement.lang 
                 || 'en';

    await i18next.use(i18nextHttpBackend).init({
        lng: locale,
        fallbackLng: 'en',
        backend: {
            loadPath: '/assets/locales/{{lng}}/translation.json'
        },
        interpolation: { escapeValue: false },
    });

    // Initial render: replace semua [data-i18n] elements
    document.querySelectorAll('[data-i18n]').forEach(el => {
        el.textContent = i18next.t(el.getAttribute('data-i18n'));
    });

    // Expose global untuk digunakan di tempat lain
    window.i18n = i18next;
});
```

### 3. Pemakaian di HTML

```html
<button data-i18n="auth.login.submit">Sign In</button>
<input type="email" data-i18n-attr="placeholder|auth.login.email_placeholder" />

<script>
    // Di event handler:
    alert(window.i18n.t('product.create.success'));
</script>
```

Atribut `data-i18n` di-render setelah init selesai. Atribut `data-i18n-attr` untuk translate attribute (placeholder, title, aria-label).

### 4. Server-Side Initial Locale (anti FOUT)

Backend memilih locale default berdasarkan:

1. Query string `?lang=id` (override via URL)
2. `$_SESSION['locale']` (kalau pernah di-set)
3. Header `Accept-Language` (parsed best-match)
4. Fallback `en`

Tag `<html lang="<?= htmlspecialchars($locale) ?>">` di-set saat render HTML. JavaScript baca dari `document.documentElement.lang` saat init.

```php
// app/Core/LocaleResolver.php
public function resolve(Request $req): string
{
    if (!empty($_GET['lang']) && in_array($_GET['lang'], ['en', 'id'])) {
        return $_GET['lang'];
    }
    if (!empty($_SESSION['locale'])) {
        return $_SESSION['locale'];
    }
    $al = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (stripos($al, 'id') !== false) return 'id';
    return 'en';
}
```

### 5. Locale Switcher

Di header, tombol `data-i18n="header.locale.toggle"` dengan onClick:

```javascript
function toggleLocale() {
    const current = i18next.language;
    const next = current === 'en' ? 'id' : 'en';
    localStorage.setItem('locale', next);
    location.reload(); // simple reload, semua teks re-render
}
```

`document.documentElement.lang` di-update sebelum reload.

### 6. Translation File Format

`public/assets/locales/en/translation.json`:

```json
{
  "auth": {
    "login": {
      "title": "Sign In",
      "email_placeholder": "you@example.com",
      "password_placeholder": "Enter password",
      "submit": "Sign In",
      "error_generic": "Email or password is incorrect"
    }
  },
  "common": {
    "save": "Save",
    "cancel": "Cancel",
    "loading": "Loading...",
    "empty_state": {
      "products": "No products yet",
      "products_cta": "Click 'Add Product' to get started"
    }
  },
  "sales_order": {
    "status": {
      "Draft": "Draft",
      "PendingApproval": "Pending Approval",
      "Approved": "Approved",
      "Fulfilled": "Fulfilled",
      "Cancelled": "Cancelled"
    }
  }
}
```

Struktur nested (key path dengan dot notation) untuk organize.

### 7. PHP-side Backend Localization

Untuk render view server-side (di tahap awal, sebelum JS hydrate), kami pakai helper:

```php
// app/Core/Translator.php
function t(string $key, array $params = []): string {
    static $translations = null;
    if ($translations === null) {
        $locale = $_SESSION['locale'] ?? 'en';
        $file = __DIR__ . "/../../public/assets/locales/{$locale}/translation.json";
        $translations = json_decode(file_get_contents($file), true);
    }
    // Resolve key path "auth.login.title"
    $parts = explode('.', $key);
    $val = $translations;
    foreach ($parts as $p) {
        $val = $val[$p] ?? null;
        if ($val === null) return "{{{$key}}}"; // missing key sentinel
    }
    return is_string($val) ? $val : "{{{$key}}}";
}
```

Helper ini **shared source** antara PHP (server render) dan JS (client interactive). Tidak ada string UI yang terpisah.

### 8. CSV Header Localization (REPORT-01 integration)

CSV export pakai PHP-side translation. Header EN: "Product", "Warehouse", "Type", "Quantity", "Reference", "By", "At".

---

## Consequences

### Positif
- **Ringan & framework-agnostic** — i18next-core UMD tidak memaksa pola tertentu.
- **Lazy-load namespace** — `i18next-http-backend` hanya load JSON untuk namespace yang diperlukan.
- **Shared source** — Backend & frontend pakai key yang sama, fallback ke `en` kalau missing.
- **i18next mature** — Disukai komunitas, support banyak format, dokumentasi baik.

### Negatif
- **Dua paralel implementation** — `t()` di PHP dan `i18next.t()` di JS. Risiko drift kalau tidak disiplin update keduanya. Mitigasi: source JSON adalah single source of truth.
- **FOUT mitigation butuh kerja ekstra** — Solusi: server-side set lang + initial attribute, JS hydrate setelah init. Tidak seamless SSR tapi cukup untuk scope.
- **Tidak support pluralization kompleks** — i18next mendukung plural rules tetapi untuk scope MVP ini cukup plain key lookup.

---

## Alternatives Considered

### Opsi A: i18next-core + http-backend → **DITERIMA**
### Opsi B: FormatJS — Ditolak (perlu React)
### Opsi C: Custom JS dictionary — Ditolak (reinvent wheel, susah namespace management)
### Opsi D: Hanya server-side, single language EN — Ditolak (Vision value prop #5 + I18N-01)

---

## Implementation Notes

1. **CDN vs self-host:** Kami **self-host** di `public/assets/vendor/`. Alasan:
   - Hindari dependency network saat offline demo.
   - Lighthouse score lebih konsisten (tidak ada third-party blocking).
   - Lebih cepat untuk assessor grading di Docker bersih.

2. **Version pinning:** Simpan file dengan hash filename (`i18next.min.{hash}.js`) atau version comment. Tidak pakai auto-update CDN.

3. **Testing:**
   - **Static analysis:** Script PHP `scripts/check-i18n-keys.php` scan semua file `views/**/*.php` dan `public/assets/js/**/*.js`, ekstrak key, bandingkan dengan `en/translation.json`. Missing → exit 1.
   - **E9 metric:** Total string hardcoded di luar `t()` harus 0.
   - **Acceptance:** Switch bahasa 3 halaman utama → semua label berubah.

4. **Fallback chain:** `en` adalah fallback untuk semua locale (mis. `id` ada missing → fallback `en`).

5. **Pluralization** (jika dibutuhkan nanti): i18next support `key_one` / `key_other` plural rules. Untuk MVP tidak dipakai.

6. **Anti-FOUT:** `<html lang>` di-set saat render. JS hydration. Worst-case: flash sebentar (~50-100ms) saat first load; acceptable untuk scope.

---

## Validation / How to Verify

- [x] I18N-01 AC1 — Default first visit → EN tampil.
- [x] I18N-01 AC2 — Switch ke ID + refresh + reopen → tetap ID.
- [x] I18N-01 AC3 — `grep` codebase tidak ada string UI di luar `t()`.
- [x] I18N-01 AC4 — CSV export EN dengan header translated.
- [x] E9 metric tercapai (zero hardcoded UI string).

---

## References
- `docs/planning/prd.md` §10 I18N-01, §1 BR-020
- `docs/planning/product-vision.md` §4.2 #5, §6.2 E9
- i18next docs: <https://www.i18next.com/>
- i18next-http-backend: <https://github.com/i18next/i18next-http-backend>
