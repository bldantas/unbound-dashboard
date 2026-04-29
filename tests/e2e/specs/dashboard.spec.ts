import { test, expect, type Page } from '@playwright/test'

const E2E_USER = process.env.E2E_USER || 'admin'
const E2E_PASS = process.env.E2E_PASS || 'admin123'

async function login(page: Page) {
  await page.goto('/login')
  await page.fill('input[name="username"], input[type="text"]', E2E_USER)
  await page.fill('input[type="password"]', E2E_PASS)
  await page.click('button[type="submit"]')
  await page.waitForURL(/\/(dashboard)?$/, { timeout: 10_000 })
}

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('exibe cards de estatísticas', async ({ page }) => {
    await expect(page.locator('[data-testid="stat-card"], .stat-card, canvas')).toBeVisible({
      timeout: 10_000,
    })
  })

  test('sidebar exibe links de navegação', async ({ page }) => {
    await expect(page.locator('nav a[href="/logs"], nav a[href*="log"]')).toBeVisible()
    await expect(page.locator('nav a[href="/alerts"], nav a[href*="alert"]')).toBeVisible()
  })

  test('navega para a página de logs', async ({ page }) => {
    await page.click('nav a[href="/logs"], nav a[href*="log"]')
    await expect(page).toHaveURL(/\/logs/)
    // Tabela deve estar presente
    await expect(page.locator('table')).toBeVisible({ timeout: 8_000 })
  })

  test('navega para a página de alertas', async ({ page }) => {
    await page.click('nav a[href="/alerts"], nav a[href*="alert"]')
    await expect(page).toHaveURL(/\/alerts/)
  })

  test('navega para a página de saúde', async ({ page }) => {
    await page.click('nav a[href="/health"], nav a[href*="health"]')
    await expect(page).toHaveURL(/\/health/)
  })

  test('logout funciona', async ({ page }) => {
    await page.click('button:has-text("Sair"), a:has-text("Sair")')
    await expect(page).toHaveURL(/\/login/)
  })
})

test.describe('Proteção de rotas', () => {
  test('rota /alerts sem login redireciona para /login', async ({ page }) => {
    await page.goto('/alerts')
    await expect(page).toHaveURL(/\/login/)
  })

  test('rota /config sem login redireciona para /login', async ({ page }) => {
    await page.goto('/config')
    await expect(page).toHaveURL(/\/login/)
  })
})
