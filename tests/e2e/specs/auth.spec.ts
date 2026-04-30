import { test, expect } from '@playwright/test'

const API = process.env.API_URL || 'http://localhost:8000'

test.describe('Autenticação', () => {
  test('GET /healthz responde 200', async ({ request }) => {
    const res = await request.get(`${API}/healthz`)
    expect(res.status()).toBe(200)
    const body = await res.json()
    expect(body.status).toBe('ok')
  })

  test('login com credenciais inválidas retorna 401', async ({ request }) => {
    const res = await request.post(`${API}/api/v2/auth/login`, {
      data: { username: 'nobody', password: 'wrong' },
    })
    expect(res.status()).toBe(401)
  })

  test('página de login é exibida', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('input[name="username"], input[type="text"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
  })

  test('login com credenciais corretas redireciona para dashboard', async ({ page }) => {
    const user = process.env.E2E_USER || 'admin'
    const pass = process.env.E2E_PASS || 'admin123'

    await page.goto('/login')
    await page.fill('input[name="username"], input[type="text"]', user)
    await page.fill('input[type="password"]', pass)
    await page.click('button[type="submit"]')

    await expect(page).toHaveURL(/\/(dashboard)?$/, { timeout: 10_000 })
  })

  test('5 tentativas inválidas exibem mensagem de bloqueio', async ({ page }) => {
    await page.goto('/login')
    for (let i = 0; i < 5; i++) {
      await page.fill('input[name="username"], input[type="text"]', 'admin')
      await page.fill('input[type="password"]', `wrong${i}`)
      await page.click('button[type="submit"]')
      await page.waitForTimeout(300)
    }
    // A mensagem de erro deve aparecer após o lockout
    const errorMsg = page.locator('[role="alert"], .error, .text-red-600')
    await expect(errorMsg).toBeVisible({ timeout: 5_000 })
  })
})
