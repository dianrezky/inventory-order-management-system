import { test, expect } from '../../support/fixtures';
import { TAGS } from '../../support/tags';
import { CSV_HEADERS, CSV_FILENAMES } from '../../support/messages';

test('smoke: categories CSV export has the exact contract header and includes our own row', { tag: [TAGS.smoke, TAGS.export] }, async ({
  adminRequest,
  factories,
  http,
}) => {
  const category = await factories.categories.create();
  const res = await http.downloadCsv(adminRequest, '/categories/export', { name: category.name });
  expect(res.status).toBe(200);
  expect(res.contentType).toContain('text/csv');
  expect(res.filename).toBe(CSV_FILENAMES.categories);
  expect(res.rows[0]?.join(',')).toBe(CSV_HEADERS.categories);
  const dataRow = res.rows.find((r) => r[1] === category.name) as string[] | undefined;
  expect(dataRow).toBeDefined();
  expect(dataRow?.[0]).toBe(category.code);
});
