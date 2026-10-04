// Contract-level message registry (FOUNDATION_CONTRACT.md §11). Every
// string here is copied VERBATIM from the PHP source cited in its comment —
// never retyped from memory. Agent 8's audit phase greps app/** for each
// literal and fails if one no longer appears verbatim, so this file cannot
// silently drift from the application.
//
// These are CONTRACT-LEVEL assertions (design spec §3.19): exact text, exact
// status code, for behaviour classified EXPECTED_BEHAVIOR in
// docs/testing/e2e-playwright/KNOWN_DEFECTS.md. Generic/semantic messages are
// NOT listed here — those are asserted by fragment/regex at the call site.
// A message for behaviour classified KNOWN_DEFECT/SPEC_CODE_MISMATCH lives
// instead next to its `@quirk`-tagged regression test, not here, so this
// registry never normalizes a bug into the "intended" contract.

export const MESSAGES = {
  auth: {
    // AuthController::MESSAGE_INVALID_CREDENTIALS — identical text for an
    // unknown email, a wrong password, AND a deactivated user
    // (AuthService::login), deliberately generic to avoid account
    // enumeration. EXPECTED_BEHAVIOR.
    invalidCredentials: 'The email address or password you entered is incorrect.',
    // BaseController::requireCsrf — same text returned for missing, invalid
    // or stale CSRF tokens on any POST route. EXPECTED_BEHAVIOR.
    csrfInvalid: 'Your session has expired or the form is invalid. Please try again.',
  },
  salesOrder: {
    // SalesOrderPolicy::assertCanDecide — thrown for approve AND reject when
    // the actor is not Admin. EXPECTED_BEHAVIOR (role-based SOD; see
    // KNOWN_DEFECTS.md SPEC-01 for the docs/testing/README.md discrepancy).
    approveForbidden: 'Only an administrator can approve a sales order.',
    rejectForbidden: 'Only an administrator can reject a sales order.',
    submitNotCreator: 'Only the sales person who created this order can submit it.',
    submitNotDraft: 'Only a Draft sales order can be submitted for approval.',
    approveNotPending: 'Only a Pending Approval sales order can be approved.',
    rejectNotPending: 'Only a Pending Approval sales order can be rejected.',
    cancelNotCreator: 'Only the sales person who created this order can cancel it.',
    cancelFulfilled: 'A fulfilled sales order can no longer be cancelled.',
    cancelAlreadyCancelled: 'This sales order is already cancelled.',
    issueNotApproved: 'Goods can only be issued for an approved sales order.',
    insufficientStock: 'Insufficient stock: there is not enough stock on hand to issue this sales order.',
    atLeastOneLine: 'A sales order needs at least one line item.',
  },
  purchaseOrder: {
    submitNotDraft: 'Only a Draft purchase order can be submitted.',
    atLeastOneLine: 'A purchase order needs at least one line item.',
    cancelGuard:
      'This purchase order can no longer be cancelled — it has already been fully received or is already cancelled.',
    receiveWrongState: 'Goods can only be received for an Ordered or Partially Received purchase order.',
    overReceipt: 'Quantity received cannot exceed the quantity still outstanding for this line.',
    invalidLineItem: 'Invalid purchase order line item.',
    enterQtyAtLeastOne: 'Enter a quantity for at least one line item.',
  },
  recordNotFound: 'Record not found.',
  product: {
    skuRequired: 'SKU is required.',
    skuTooLong: 'SKU must be 30 characters or fewer.', // SPEC_CODE_MISMATCH — see KNOWN_DEFECTS.md DEF-01 (schema allows 50)
    nameRequired: 'Product name is required.',
    nameBounds: 'Product name must be between 3 and 150 characters.', // KNOWN_DEFECT (byte length, not char length) — DEF-02
    descriptionTooLong: 'Description must be 500 characters or fewer.',
    unitRequired: 'Unit of measure is required.',
    categoryRequired: 'Please select a category.',
    invalidPurchasePrice: 'Please enter a valid, non-negative purchase price.',
    invalidSalePrice: 'Please enter a valid, non-negative selling price.',
    saleBelowPurchase: 'Selling price must be greater than or equal to purchase price.',
    reorderNegative: 'Minimum stock threshold cannot be negative.',
    skuTaken: 'This SKU is already in use.',
    categoryMissing: 'Selected category does not exist.',
  },
  image: {
    // ImageUploadService size/transport-level messages.
    tooLarge: 'File size exceeds the maximum allowed.',
    uploadFailed: 'The image could not be uploaded. Please try again.',
    // FileSignatureValidator (added after this suite's original research
    // pass — see KNOWN_DEFECTS.md "post-merge correction"; validated
    // against the live source and the seeded file_validation_rules).
    // Content-level checks run in this order: extension allow-list ->
    // double-extension -> magic-byte header -> magic-byte footer.
    invalidType: 'Only JPEG, PNG, or WebP images are allowed.', // extension not in file_validation_rules
    doubleExtension: 'The file name has multiple extensions, which is not allowed.',
    signatureMismatch: 'The file content does not match its extension. Please upload a genuine image.',
    suspiciousFooter: 'The file appears to be corrupted or tampered with and was rejected.',
    rulesUnavailable: 'The image could not be processed. Please try again.', // file_validation_rules table/cache empty
    // ImageUploadService's own post-signature finfo/GD sanity check reuses
    // the same invalidType text for a mismatch at that later stage too.
    processingFailed: 'The image could not be processed. Please try a different file.',
  },
  category: {
    nameRequired: 'Name is required.',
    nameBounds: 'Category name must be between 3 and 80 characters.',
    descriptionTooLong: 'Description must be at most 250 characters.',
    nameTaken: 'This name is already in use.',
    codeFormat: 'Category code must be 3-20 characters, using only letters, numbers, and hyphens.',
    codeTaken: 'This category code is already in use.',
  },
  warehouse: {
    codeRequired: 'Code is required.',
    nameRequired: 'Name is required.',
    codeTaken: 'This code is already in use.',
  },
  party: {
    // Shared by Customer + Supplier services — identical strings.
    nameRequired: 'Name is required.',
    invalidEmail: 'Please enter a valid email address.',
  },
  user: {
    nameRequired: 'Name is required.',
    invalidEmail: 'Please enter a valid email address.',
    invalidRole: 'Please select a valid role.',
    passwordTooShort: 'Password must be at least 6 characters.',
    emailTaken: 'This email is already in use.',
    cannotChangeOwnRole: 'You cannot change your own role.',
    cannotDeactivateSelf: 'You cannot deactivate your own account.',
    created: 'The user account has been created.',
    updated: 'The user account has been updated.',
  },
  profile: {
    updated: 'Your profile has been updated.',
  },
  report: {
    dateRangeRequired: 'Please select both a start date and an end date.',
  },
  notification: {
    forbidden: "You don't have permission to manage notifications.",
  },
  forbiddenDefault: "You don't have permission to access this page.",
  badRequestDefault: 'Bad request.',
  internalError: 'An internal error occurred. Please try again later.',
} as const;

export const CSV_HEADERS = {
  // CsvExportService — column order/labels, English locale (RFC 4180, CRLF).
  stockLedger: 'Date,Product,SKU,Warehouse,Type,Quantity,Ref Type,Ref ID,Done By',
  orders: 'Type,Order No.,Date,Customer/Supplier,Warehouse,Status,Items Count,Total Value,Created By',
  categories: 'Code,Name,Description,Assigned SKUs,Status,Last Updated',
} as const;

export const CSV_FILENAMES = {
  stockLedger: 'stock-ledger.csv',
  orders: 'orders.csv',
  categories: 'categories.csv',
} as const;

/** The stock-ledger table's negative quantities are rendered with the
 * Unicode MINUS SIGN (U+2212), not ASCII hyphen — confirmed in
 * views/inventory/_stock-ledger-rows.php. A naive `-` regex will not match. */
export const UNICODE_MINUS = '−';
