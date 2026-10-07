import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { mockMapTiles } from './map-fixture.mjs';

// Use a disposable database. Address-search results come through the real API,
// configured with a deterministic Photon-compatible test provider.
const base = process.env.BROWSER_BASE_URL || 'http://localhost:5173';
const apiBase = process.env.BROWSER_API_URL || 'http://localhost:8000';
const stamp = Date.now();
const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || undefined,
  headless: true,
  args: ['--no-sandbox'],
});
const context = await browser.newContext({
  viewport: { width: 1440, height: 1000 },
});
await context.addInitScript(() =>
  localStorage.setItem('dispatch.locale', 'en'),
);
await mockMapTiles(context);
const page = await context.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const dir = process.env.BROWSER_SCREENSHOT_DIR || 'test-results';
await mkdir(dir, { recursive: true });

async function go(path) {
  await page.goto(base + path);
  await page.locator('h1').last().waitFor();
  await page.waitForLoadState('networkidle');
}
async function login(email) {
  await go('/login');
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL((url) => url.pathname !== '/login');
  await page
    .getByRole('heading', { name: 'Sign in to your workspace' })
    .waitFor({ state: 'hidden' });
}
async function submit(endpoint, trigger, method = 'POST') {
  const pending = page.waitForResponse(
    (r) => r.url() === apiBase + endpoint && r.request().method() === method,
  );
  await trigger();
  const response = await pending,
    body = await response.json();
  assert.ok(response.ok(), JSON.stringify(body));
  return body.data;
}
async function current(endpoint) {
  return page.evaluate(
    async (url) =>
      (
        await (
          await fetch(url, {
            credentials: 'include',
            headers: { Accept: 'application/json' },
          })
        ).json()
      ).data,
    apiBase + endpoint,
  );
}
async function logout() {
  await page.getByRole('button', { name: 'Log out' }).click();
  await page.waitForURL('**/login');
}
async function pickAddress(input, query, street) {
  await input.fill(query);
  const option = page.getByRole('option').filter({ hasText: street });
  await option.waitFor();
  await option.click();
}

try {
  await login('admin@example.com');
  await go('/drivers');
  await page
    .getByRole('button', { name: 'Create driver', exact: true })
    .click();
  const modal = page.getByRole('dialog');
  const email = `map-driver-${stamp}@example.com`;
  await modal.getByLabel('Full name').fill('Map Test Driver');
  await modal.getByLabel('Email', { exact: true }).fill(email);
  await modal.getByLabel('Password', { exact: true }).fill('password123');
  const driver = await submit('/api/users', () =>
    modal.getByRole('button', { name: 'Save driver' }).click(),
  );
  await modal.waitFor({ state: 'detached' });

  await go('/customers');
  await page.getByRole('button', { name: 'Create customer' }).click();
  await modal.getByLabel('Customer name').fill(`Map Customer ${stamp}`);
  await modal.getByLabel('Phone', { exact: true }).fill('+995555123456');
  const customer = await submit('/api/customers', () =>
    modal.getByRole('button', { name: 'Save customer' }).click(),
  );
  await modal.waitFor({ state: 'detached' });
  await go('/customers/' + customer.id);
  await page.getByRole('button', { name: 'Add address' }).click();
  await modal.getByLabel('Label', { exact: true }).fill('Home');
  const savedInput = modal.getByRole('combobox', {
    name: 'Address',
    exact: true,
  });
  await savedInput.fill('Rustaveli');
  await page
    .getByRole('option')
    .filter({ hasText: '12 Rustaveli Avenue' })
    .waitFor();
  await savedInput.press('Enter');
  assert.equal(await savedInput.inputValue(), '12 Rustaveli Avenue');
  assert.equal(
    await modal.getByLabel('City', { exact: true }).inputValue(),
    'Tbilisi',
  );
  await modal
    .getByRole('region', { name: 'Saved address location picker' })
    .locator('.destination-pin')
    .waitFor();
  await modal.getByLabel('Use as default address').check();
  const address = await submit(`/api/customers/${customer.id}/addresses`, () =>
    modal.getByRole('button', { name: 'Save address' }).click(),
  );
  assert.equal(address.latitude, 41.7151234);
  assert.equal(address.longitude, 44.8271234);
  await modal.waitFor({ state: 'detached' });
  console.log(
    'PASS saved-address suggestions, keyboard selection, city and persisted pin',
  );

  await go('/orders/create');
  await page
    .getByLabel('Customer (optional)')
    .selectOption(String(customer.id));
  const input = page.getByRole('combobox', {
    name: 'Order address',
    exact: true,
  });
  assert.equal(await input.inputValue(), '12 Rustaveli Avenue');
  await page.getByText(/Location pinned · 41.715123, 44.827123/).waitFor();
  assert.equal(await page.getByLabel('Time from').inputValue(), '09:00');
  assert.equal(await page.getByLabel('Time to').inputValue(), '18:00');
  const rideDate = await page.getByLabel('Date from').inputValue();
  const shifted = (days) =>
    new Date(new Date(rideDate + 'T12:00:00Z').getTime() + days * 86400000)
      .toISOString()
      .slice(0, 10);
  await page.getByLabel('Date from').fill(shifted(-1));
  await page.getByLabel('Date to').fill(shifted(1));
  await page.getByLabel('Time from').fill('10:00');
  await page.getByLabel('Time to').fill('17:30');
  await page.getByLabel('Recipient name').fill(`Map Recipient ${stamp}`);
  await page.getByLabel('Driver (optional)').selectOption(String(driver.id));
  // Empty/error states keep the typed address available for manual pinning.
  await input.fill('No Match Street');
  await page
    .getByText(
      'No matching addresses. You can still enter the address and place the pin manually.',
    )
    .waitFor();
  await input.fill('Provider Down Street');
  await page
    .getByText(
      'Address suggestions are temporarily unavailable. Enter the address and place the pin manually.',
    )
    .waitFor();

  // A response from an old query must not reopen/replace a selected result.
  let received, finished;
  const oldReceived = new Promise((resolve) => (received = resolve));
  const oldFinished = new Promise((resolve) => (finished = resolve));
  await context.route('**/api/addresses/search?**', async (route) => {
    const q = new URL(route.request().url()).searchParams.get('q');
    if (q !== 'Slow Street') {
      await route.continue();
      return;
    }
    received();
    await new Promise((resolve) => setTimeout(resolve, 1600));
    try {
      await route.fulfill({
        json: {
          data: [
            {
              id: 'stale',
              address: 'Old Street',
              label: 'Old Street',
              city: 'Tbilisi',
              latitude: 0,
              longitude: 0,
            },
          ],
        },
      });
    } catch {
      /* Request was cancelled. */
    }
    finished();
  });
  await input.fill('Slow Street');
  await oldReceived;
  await pickAddress(input, 'Kazbegi', '26 Kazbegi Avenue');
  await oldFinished;
  assert.equal(await input.inputValue(), '26 Kazbegi Avenue');
  assert.equal(await page.getByRole('listbox').getByRole('option').count(), 0);
  await page.getByText(/Location pinned · 41.727654, 44.765432/).waitFor();

  const picker = page.getByRole('region', { name: 'Order location picker' });
  const pin = picker.locator('.destination-pin');
  await pin.scrollIntoViewIfNeeded();
  const bounds = await pin.boundingBox();
  const oldText = await page.getByText(/Location pinned ·/).textContent();
  await page.mouse.move(bounds.x + 16, bounds.y + 15);
  await page.mouse.down();
  await page.mouse.move(bounds.x + 56, bounds.y - 15, { steps: 12 });
  await page.mouse.up();
  await page.waitForFunction(
    (old) =>
      [...document.querySelectorAll('.location-map p')].some(
        (p) =>
          p.textContent.includes('Location pinned') &&
          p.textContent.trim() !== old.trim(),
      ),
    oldText,
  );
  assert.equal(await input.inputValue(), '26 Kazbegi Avenue');
  await picker.screenshot({ path: dir + '/location-picker.png' });
  const task = await submit('/api/orders', () =>
    page.getByRole('button', { name: 'Create order', exact: true }).click(),
  );
  assert.notEqual(task.latitude, 41.7276543);
  assert.notEqual(task.longitude, 44.7654321);
  await page.waitForURL('**/orders/' + task.id);
  const storedCustomer = await current('/api/customers/' + customer.id);
  assert.equal(storedCustomer.addresses[0].latitude, 41.7151234);
  console.log(
    'PASS click suggestions, loading/empty/provider failure, stale-query cancellation and manual pin dragging',
  );

  await go(`/orders/${task.id}/edit`);
  await page
    .getByRole('region', { name: 'Order location picker' })
    .locator('.destination-pin')
    .waitFor();
  assert.equal(
    await page
      .getByRole('combobox', { name: 'Order address', exact: true })
      .inputValue(),
    '26 Kazbegi Avenue',
  );
  await page
    .getByRole('region', { name: 'Order location picker' })
    .click({ position: { x: 180, y: 120 } });
  const updated = await submit(
    '/api/orders/' + task.id,
    () => page.getByRole('button', { name: 'Save changes' }).click(),
    'PUT',
  );
  assert.notEqual(updated.latitude, task.latitude);
  await page.waitForURL('**/orders/' + task.id);
  await page.reload();
  await page
    .getByRole('region', { name: 'Order destination map' })
    .locator('.destination-pin')
    .waitFor();
  const link = page.getByRole('link', { name: 'Open navigation' });
  assert.equal(
    new URL(await link.getAttribute('href')).searchParams.get('query'),
    `${updated.latitude},${updated.longitude}`,
  );
  console.log(
    'PASS map click, edited pin persistence, reload and exact-coordinate navigation',
  );

  await go('/rides');
  await page.getByRole('button', { name: 'Create ride', exact: true }).click();
  await modal.getByLabel('Driver').selectOption(String(driver.id));
  await modal.getByLabel('Date').fill(rideDate);
  const plannedRide = await submit('/api/rides', () =>
    modal.getByRole('button', { name: 'Create ride', exact: true }).click(),
  );
  await go('/rides/' + plannedRide.id);
  await page.getByRole('button', { name: 'Add stops' }).click();
  await modal
    .locator('label')
    .filter({ hasText: `Map Recipient ${stamp}` })
    .getByRole('checkbox')
    .check();
  await submit('/api/rides/' + plannedRide.id + '/tasks', () =>
    modal.getByRole('button', { name: 'Add 1 stops' }).click(),
  );
  await modal.waitFor({ state: 'detached' });
  console.log(
    'PASS editable date range and hours, ride eligibility on an interior date',
  );

  await logout();
  await page.setViewportSize({ width: 390, height: 844 });
  await login(email);
  await page.getByRole('link', { name: 'View pinned destination →' }).waitFor();
  await page.getByRole('link', { name: 'View pinned destination →' }).click();
  const driverMap = page.getByRole('region', {
    name: 'Order destination map',
  });
  await driverMap.locator('.destination-pin').waitFor();
  assert.equal(await driverMap.locator('.leaflet-marker-draggable').count(), 0);
  assert.equal(
    await page.getByRole('button', { name: 'Clear pin' }).count(),
    0,
  );
  const driverText = await page.getByText(/Location pinned ·/).textContent();
  await driverMap.click({ position: { x: 100, y: 100 } });
  assert.equal(
    await page.getByText(/Location pinned ·/).textContent(),
    driverText,
  );
  assert.equal(
    new URL(
      await page
        .getByRole('link', { name: 'Open navigation' })
        .getAttribute('href'),
    ).searchParams.get('query'),
    `${updated.latitude},${updated.longitude}`,
  );
  assert.equal(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
    true,
  );
  await page.screenshot({
    path: dir + '/driver-destination-map.png',
    fullPage: true,
  });
  assert.equal(
    await page
      .getByRole('button', { name: 'Delete order', exact: true })
      .count(),
    0,
  );
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await logout();
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login('admin@example.com');
  await go('/orders/' + task.id);
  await page.getByRole('button', { name: 'Delete order', exact: true }).click();
  await modal.getByRole('button', { name: 'Keep order' }).click();
  await page.getByRole('button', { name: 'Delete order', exact: true }).click();
  const deletedResponse = page.waitForResponse(
    (r) =>
      r.url() === apiBase + '/api/orders/' + task.id &&
      r.request().method() === 'DELETE',
  );
  await modal
    .getByRole('button', { name: 'Delete order', exact: true })
    .click();
  assert.equal((await deletedResponse).status(), 204);
  await page.waitForURL('**/orders');
  const remainingRide = await current('/api/rides/' + plannedRide.id);
  assert.equal(remainingRide.tasks.length, 0);
  console.log(
    'PASS admin delete confirmation, cancel and soft deletion from active ride',
  );
  assert.deepEqual(errors, []);
  console.log(
    'PASS mobile driver sees the saved pin, cannot move it, and navigates to exact coordinates; no runtime errors',
  );
} catch (error) {
  await page.screenshot({ path: dir + '/map-failure.png', fullPage: true });
  console.error('Failure URL:', page.url());
  throw error;
} finally {
  await browser.close();
}
