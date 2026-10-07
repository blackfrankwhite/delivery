import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { mockMapTiles } from './map-fixture.mjs';

// Run only against a disposable development database: this creates demo records.
const base = process.env.BROWSER_BASE_URL || 'http://localhost:5173';
const apiBase = process.env.BROWSER_API_URL || 'http://localhost:8000';
const stamp = Date.now();
const recipientA = `Browser Recipient A ${stamp}`;
const recipientB = `Browser Recipient B ${stamp}`;
const driverEmail = `browser-driver-${stamp}@example.com`;
const operatorEmail = `browser-operator-${stamp}@example.com`;
const password = 'password123';
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
await context.route('**/api/addresses/search?**', (route) =>
  route.fulfill({ json: { data: [] } }),
);
const page = await context.newPage();
const pageErrors = [];
page.on('pageerror', (error) => pageErrors.push(error.message));
const screenshotDir = process.env.BROWSER_SCREENSHOT_DIR || 'test-results';
await mkdir(screenshotDir, { recursive: true });

async function go(path) {
  await page.goto(base + path);
  await page.locator('h1').last().waitFor();
  await page.waitForLoadState('networkidle');
}
async function login(email) {
  await go('/login');
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  const signIn = () =>
    page.getByRole('button', { name: 'Sign in', exact: true }).click();
  const loginResponse = () =>
    page.waitForResponse(
      (r) =>
        r.url() === apiBase + '/api/auth/login' &&
        r.request().method() === 'POST',
    );
  let pending = loginResponse();
  await signIn();
  let response = await pending;
  if (response.status() === 429) {
    const seconds = Number(response.headers()['retry-after'] || 60);
    console.log(`Login rate limit reached; retrying after ${seconds} seconds`);
    await page.waitForTimeout((seconds + 1) * 1000);
    pending = loginResponse();
    await signIn();
    response = await pending;
  }
  assert.ok(response.ok(), `Login returned ${response.status()}`);
  await page.waitForURL((url) => url.pathname !== '/login');
  await page.waitForLoadState('networkidle');
}
async function logout() {
  await page.getByRole('button', { name: 'Log out' }).click();
  await page.waitForURL('**/login');
}
async function submitApi(endpoint, trigger, method = 'POST') {
  const responsePromise = page.waitForResponse(
    (r) => r.url() === apiBase + endpoint && r.request().method() === method,
  );
  await trigger();
  const response = await responsePromise;
  const body = await response.json();
  assert.ok(
    response.ok(),
    `${endpoint} returned ${response.status()}: ${JSON.stringify(body)}`,
  );
  return body.data;
}
async function createAccount(kind, email, name) {
  await go('/' + kind);
  await page
    .getByRole('button', {
      name: 'Create ' + (kind === 'drivers' ? 'driver' : 'operator'),
      exact: true,
    })
    .click();
  const modal = page.getByRole('dialog');
  await modal.getByLabel('Full name').fill(name);
  await modal.getByLabel('Email', { exact: true }).fill(email);
  await modal.getByLabel('Phone', { exact: true }).fill('+995555123456');
  await modal.getByLabel('Password', { exact: true }).fill(password);
  const account = await submitApi('/api/users', () =>
    modal.getByRole('button', { name: /^Save / }).click(),
  );
  await modal.waitFor({ state: 'detached' });
  return account;
}

try {
  await login('admin@example.com');
  await page.reload();
  await page.getByRole('heading', { name: 'Operations overview' }).waitFor();
  const driver = await createAccount('drivers', driverEmail, 'Browser Driver');
  await createAccount('operators', operatorEmail, 'Browser Operator');

  await go('/customers');
  await page.getByRole('button', { name: 'Create customer' }).click();
  await page.getByLabel('Customer name').fill(`Browser Customer ${stamp}`);
  await page.getByLabel('Phone', { exact: true }).fill('+995555654321');
  const customer = await submitApi('/api/customers', () =>
    page.getByRole('button', { name: 'Save customer' }).click(),
  );
  await page.getByRole('dialog').waitFor({ state: 'detached' });
  await go('/customers/' + customer.id);
  await page.getByRole('button', { name: 'Add address' }).click();
  await page.getByLabel('Label', { exact: true }).fill('Home');
  await page
    .getByRole('combobox', { name: 'Address', exact: true })
    .fill('12 Rustaveli Avenue');
  await page.getByLabel('City', { exact: true }).fill('Tbilisi');
  await page
    .getByLabel('Instructions', { exact: true })
    .fill('Call on arrival.');
  await page.getByLabel('Use as default address').check();
  await submitApi(`/api/customers/${customer.id}/addresses`, () =>
    page.getByRole('button', { name: 'Save address' }).click(),
  );
  await page.getByRole('dialog').waitFor({ state: 'detached' });

  const itemIds = [];
  for (const name of [
    `Browser Package ${stamp}`,
    `Browser Document ${stamp}`,
  ]) {
    await go('/items');
    await page.getByRole('button', { name: 'Create item' }).click();
    await page.getByLabel('Item name').fill(name);
    const item = await submitApi('/api/items', () =>
      page.getByRole('button', { name: 'Save item' }).click(),
    );
    itemIds.push(item.id);
    await page.getByRole('dialog').waitFor({ state: 'detached' });
  }

  await go('/orders/create');
  await page
    .getByLabel('Customer (optional)')
    .selectOption(String(customer.id));
  assert.equal(
    await page
      .getByRole('combobox', { name: 'Order address', exact: true })
      .inputValue(),
    '12 Rustaveli Avenue',
  );
  await page.getByLabel('Recipient name').fill(recipientA);
  await page
    .getByRole('combobox', { name: 'Order address', exact: true })
    .fill('24 Rustaveli Avenue');
  await page.getByLabel('Driver (optional)').selectOption(String(driver.id));
  for (let i = 0; i < 2; i++) {
    await page.getByRole('button', { name: 'Add item' }).click();
    await page
      .getByLabel('Item', { exact: true })
      .nth(i)
      .selectOption(String(itemIds[i]));
    await page
      .getByLabel('Quantity', { exact: true })
      .nth(i)
      .fill(String(i + 2));
  }
  const taskA = await submitApi('/api/orders', () =>
    page.getByRole('button', { name: 'Create order', exact: true }).click(),
  );
  await page.waitForURL('**/orders/' + taskA.id);
  assert.equal(taskA.items.length, 2);
  assert.equal(taskA.status, 'assigned');

  await go('/orders/create');
  await page.getByLabel('Recipient name').fill(recipientB);
  await page.getByLabel('Recipient phone').fill('+995555111222');
  await page
    .getByRole('combobox', { name: 'Order address', exact: true })
    .fill('8 Pekini Avenue');
  const taskB = await submitApi('/api/orders', () =>
    page.getByRole('button', { name: 'Create order', exact: true }).click(),
  );
  await page.waitForURL('**/orders/' + taskB.id);
  assert.equal(taskB.status, 'pending');

  await go('/rides');
  await page.getByRole('button', { name: 'Create ride' }).click();
  await page
    .getByRole('dialog')
    .getByLabel('Driver')
    .selectOption(String(driver.id));
  const ride = await submitApi('/api/rides', () =>
    page
      .getByRole('dialog')
      .getByRole('button', { name: 'Create ride' })
      .click(),
  );
  await page.waitForURL('**/rides/' + ride.id);
  await page.getByRole('button', { name: 'Add stops' }).click();
  await page.getByRole('dialog').getByText(recipientA, { exact: true }).click();
  await page.getByRole('dialog').getByText(recipientB, { exact: true }).click();
  await submitApi(`/api/rides/${ride.id}/tasks`, () =>
    page.getByRole('button', { name: 'Add 2 stops' }).click(),
  );
  await page.getByRole('dialog').waitFor({ state: 'detached' });
  const reordered = await submitApi(
    `/api/rides/${ride.id}/reorder`,
    () => page.getByRole('button', { name: 'Move stop up' }).nth(1).click(),
    'PUT',
  );
  assert.deepEqual(
    reordered.tasks.map((t) => t.id),
    [taskB.id, taskA.id],
  );
  await page.reload();
  await page.waitForLoadState('networkidle');
  assert.equal(
    await page.locator('article').first().getByRole('link').first().innerText(),
    recipientB,
  );
  console.log(
    'PASS admin accounts, customer/address, items, orders, ride membership and persistent order',
  );

  await logout();
  await page.setViewportSize({ width: 390, height: 844 });
  await login(driverEmail);
  await page.waitForURL('**/driver/today');
  await page.locator('article').first().waitFor();
  assert.equal(await page.locator('article').count(), 2);
  assert.equal(
    await page.getByRole('link', { name: 'Users', exact: true }).count(),
    0,
  );
  const forbidden = await page.evaluate(
    async (url) =>
      (
        await fetch(url, {
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
      ).status,
    apiBase + '/api/orders/1',
  );
  assert.equal(forbidden, 403);
  assert.ok(
    (
      await page
        .locator('article')
        .first()
        .getByRole('link', { name: 'Navigate', exact: true })
        .getAttribute('href')
    ).startsWith('https://www.google.com/maps/search/'),
  );
  assert.ok(
    (
      await page
        .locator('article')
        .first()
        .locator('a[href^="tel:"]')
        .getAttribute('href')
    ).startsWith('tel:'),
  );
  assert.equal(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
    true,
  );
  await page.screenshot({
    path: screenshotDir + '/driver-today.png',
    fullPage: true,
  });

  await go('/driver/rides/' + ride.id);
  await page.getByRole('button', { name: 'Start ride' }).click();
  await submitApi(`/api/rides/${ride.id}/status`, () =>
    page
      .getByRole('dialog')
      .getByRole('button', { name: 'Confirm', exact: true })
      .click(),
  );
  await page.getByRole('dialog').waitFor({ state: 'detached' });

  for (const task of [taskB, taskA]) {
    await go('/driver/orders/' + task.id);
    for (const [label, status] of [
      ['Start order', 'started'],
      ['Mark arrived', 'arrived'],
    ]) {
      const updated = await submitApi(`/api/orders/${task.id}/status`, () =>
        page.getByRole('button', { name: label, exact: true }).click(),
      );
      assert.equal(updated.status, status);
    }
    await page
      .getByRole('button', { name: 'Mark delivered', exact: true })
      .click();
    const delivered = await submitApi(`/api/orders/${task.id}/status`, () =>
      page
        .getByRole('dialog')
        .getByRole('button', { name: 'Confirm mark delivered' })
        .click(),
    );
    assert.equal(delivered.status, 'delivered');
    assert.ok(
      delivered.started_at && delivered.arrived_at && delivered.completed_at,
    );
    await page.getByRole('dialog').waitFor({ state: 'detached' });
  }
  await page
    .getByLabel('Notes for this order')
    .fill('Handed to the recipient.');
  await submitApi(
    `/api/orders/${taskA.id}/notes`,
    () => page.getByRole('button', { name: 'Save notes' }).click(),
    'PATCH',
  );
  await go('/driver/rides/' + ride.id);
  await page.getByRole('button', { name: 'Complete ride' }).click();
  await submitApi(`/api/rides/${ride.id}/status`, () =>
    page
      .getByRole('dialog')
      .getByRole('button', { name: 'Confirm', exact: true })
      .click(),
  );
  await page.getByRole('dialog').waitFor({ state: 'detached' });
  console.log(
    'PASS mobile layout, driver isolation, phone/maps links, order progress, notes, ride lifecycle',
  );

  await page.getByRole('button', { name: 'Open navigation' }).click();
  await logout();
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login('admin@example.com');
  await go('/orders/' + taskA.id);
  assert.equal(
    await page.getByLabel('Notes for this order').inputValue(),
    'Handed to the recipient.',
  );
  assert.ok(await page.getByText('Browser Driver', { exact: false }).count());
  await go('/customers/' + customer.id);
  assert.ok(
    await page.getByText('12 Rustaveli Avenue', { exact: true }).count(),
  );
  await go('/dashboard');
  await page.screenshot({
    path: screenshotDir + '/dashboard.png',
    fullPage: true,
  });
  console.log(
    'PASS administrator sees driver updates/history and unchanged saved address snapshot',
  );

  await logout();
  await login(operatorEmail);
  assert.equal(
    await page.getByRole('link', { name: 'Users', exact: true }).count(),
    0,
  );
  const denied = await page.evaluate(
    async (url) =>
      (
        await fetch(url, {
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
      ).status,
    apiBase + '/api/users',
  );
  assert.equal(denied, 403);
  assert.deepEqual(pageErrors, []);
  console.log('PASS operator access and no browser runtime errors');
} catch (error) {
  await page.screenshot({
    path: screenshotDir + '/failure.png',
    fullPage: true,
  });
  console.error('Failure URL:', page.url());
  throw error;
} finally {
  await browser.close();
}
