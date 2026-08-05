import { expect, type APIResponse, type Page } from '@playwright/test';
import { URLS } from './credenciais';

const TENANT_HOST = new URL(URLS.colag).hostname;

/**
 * Chromium rejeita Set-Cookie com Domain=.localhost (TLD reservado).
 * O PHP envia SESSION_DOMAIN=.localhost; injetamos PHPSESSID no host do tenant.
 */
async function aplicarSessaoDaResposta(page: Page, response: APIResponse) {
  const setCookie = response.headers()['set-cookie'] ?? '';
  const match = setCookie.match(/PHPSESSID=([^;]+)/);
  expect(match, `Login sem PHPSESSID. Set-Cookie: ${setCookie}`).toBeTruthy();

  await page.context().addCookies([
    {
      name: 'PHPSESSID',
      value: match![1],
      domain: TENANT_HOST,
      path: '/',
      httpOnly: true,
      sameSite: 'Lax',
    },
  ]);
}

export async function aceitarConsentimentoSeNecessario(page: Page) {
  const overlay = page.locator('#consentOverlay');
  if (!(await overlay.isVisible().catch(() => false))) {
    return;
  }
  await page.locator('#consentCheckbox').check();
  await page.locator('#consentConfirm').click();
  await overlay.waitFor({ state: 'hidden', timeout: 15_000 });
}

export async function loginTenant(
  page: Page,
  opts: {
    loginPage: string;
    form: Record<string, string>;
    destino: RegExp;
  },
) {
  await page.goto(opts.loginPage);
  const response = await page.request.post('/login', {
    form: { ...opts.form, site_url: '' },
    maxRedirects: 0,
    timeout: 60_000,
  });
  expect(response.status(), await response.text()).toBe(302);
  await aplicarSessaoDaResposta(page, response);
  await page.goto(response.headers().location ?? opts.loginPage);
  await expect(page).toHaveURL(opts.destino);
  await aceitarConsentimentoSeNecessario(page);
}

export async function logoutTenant(page: Page) {
  await page.goto('/logout');
  await page.context().clearCookies();
}
