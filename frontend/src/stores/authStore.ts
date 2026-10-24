import { defineStore } from 'pinia'
import apiService from '@/services/apiService'

interface User {
  id: number
  name: string
  email: string
  created_at: string
  updated_at: string
}

interface AuthState {
  user: User | null
  isAuthenticated: boolean
  loading: boolean
  error: string | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    isAuthenticated: false,
    loading: false,
    error: null,
  }),

  getters: {
    getUser: (state) => state.user,
    isAuth: (state) => state.isAuthenticated,
    getError: (state) => state.error,
    isLoading: (state) => state.loading,
  },

  actions: {
    async initialize() {
      const token = apiService.getToken()
      if (token) {
        await this.checkAuth()
      }
    },

    async register(name: string, email: string, password: string, password_confirmation: string) {
      this.loading = true
      this.error = null

      try {
        const response = await apiService.register(name, email, password, password_confirmation)
        await this.login(email, password)
        return response
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Registration failed'
        throw error
      } finally {
        this.loading = false
      }
    },

    async login(email: string, password: string) {
      this.loading = true
      this.error = null

      try {
        const response = await apiService.login(email, password, 'web')
        await this.checkAuth()
        return response
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Login failed'
        throw error
      } finally {
        this.loading = false
      }
    },

    async checkAuth() {
      this.loading = true
      this.error = null

      try {
        const response = await apiService.getCurrentUser()
        this.user = response.data
        this.isAuthenticated = true
      } catch (error) {
        apiService.clearToken()
        this.user = null
        this.isAuthenticated = false
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      this.error = null

      try {
        await apiService.logout()
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        apiService.clearToken()
        this.user = null
        this.isAuthenticated = false
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    },
  },
})
