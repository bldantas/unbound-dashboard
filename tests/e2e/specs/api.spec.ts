import { test, expect, type Page } from '@playwright/test'

const API = process.env.API_URL || 'http://localhost:8000'
const E2E_USER = process.env.E2E_USER || 'admin'
const E2E_PASS = process.env.E2E_PASS || 'admin123'

async function getAdminToken(request: any): Promise<string> {
  const res = await request.post(`${API}/api/v2/auth/login`, {
    data: { username: E2E_USER, password: E2E_PASS },
  })
  if (!res.ok()) return ''
  const body = await res.json()
  return body.access_token || ''
}

test.describe('API — stats', () => {
  test('GET /api/v2/stats/live requer autenticação', async ({ request }) => {
    const res = await request.get(`${API}/api/v2/stats/live`)
    expect([401, 403]).toContain(res.status())
  })

  test('GET /api/v2/stats/live retorna dados com token válido', async ({ request }) => {
    const token = await getAdminToken(request)
    test.skip(!token, 'Credenciais E2E não configuradas')

    const res = await request.get(`${API}/api/v2/stats/live`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status()).toBe(200)
    const body = await res.json()
    expect(typeof body.total_24h).toBe('number')
  })
})

test.describe('API — alerts', () => {
  test('GET /api/v2/alerts/count requer autenticação', async ({ request }) => {
    const res = await request.get(`${API}/api/v2/alerts/count`)
    expect([401, 403]).toContain(res.status())
  })

  test('GET /api/v2/alerts retorna lista com token válido', async ({ request }) => {
    const token = await getAdminToken(request)
    test.skip(!token, 'Credenciais E2E não configuradas')

    const res = await request.get(`${API}/api/v2/alerts`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status()).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body)).toBe(true)
  })
})

test.describe('API — blocklist (admin)', () => {
  test('GET /api/v2/blocklist/sources requer autenticação', async ({ request }) => {
    const res = await request.get(`${API}/api/v2/blocklist/sources`)
    expect([401, 403]).toContain(res.status())
  })
})
