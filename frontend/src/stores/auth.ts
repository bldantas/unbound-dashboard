import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('token'))
  const role = ref<string | null>(localStorage.getItem('role'))
  const username = ref<string | null>(localStorage.getItem('username'))

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => role.value === 'admin')

  async function login(user: string, password: string) {
    const { data } = await authApi.login(user, password)
    token.value = data.access_token
    role.value = data.role
    localStorage.setItem('token', data.access_token)
    localStorage.setItem('role', data.role)

    // Busca o username via /me
    const me = await authApi.me()
    username.value = me.data.username
    localStorage.setItem('username', me.data.username)
  }

  function logout() {
    token.value = null
    role.value = null
    username.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('role')
    localStorage.removeItem('username')
  }

  return { token, role, username, isAuthenticated, isAdmin, login, logout }
})
