import { defineStore } from 'pinia'
import apiService from '@/services/apiService'

interface CommunicationService {
  status: string
  description: string
}

interface CommunicationServices {
  sip: CommunicationService
  webrtc: CommunicationService
  sms: CommunicationService
  email: CommunicationService
  unified_inbox: CommunicationService
}

interface CommunicationPreferences {
  call_forwarding: string | null
  voicemail: boolean | null
  sms_notifications: boolean
  email_notifications: boolean
  webrtc_enabled: boolean
}

interface CommunicationState {
  services: CommunicationServices | null
  preferences: CommunicationPreferences | null
  loading: boolean
  error: string | null
}

export const useCommunicationStore = defineStore('communication', {
  state: (): CommunicationState => ({
    services: null,
    preferences: null,
    loading: false,
    error: null,
  }),

  getters: {
    getServices: (state) => state.services,
    getPreferences: (state) => state.preferences,
    getServiceStatus: (state) => (serviceName: keyof CommunicationServices) => {
      return state.services?.[serviceName]?.status || 'unknown'
    },
    isLoading: (state) => state.loading,
    getError: (state) => state.error,
  },

  actions: {
    async fetchServices() {
      this.loading = true
      this.error = null

      try {
        const response = await apiService.getCommunicationServices()
        this.services = response.data.data || response.data
        return response.data
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch communication services'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchPreferences() {
      this.loading = true
      this.error = null

      try {
        const response = await apiService.getCommunicationPreferences()
        this.preferences = response.data.data || response.data
        return response.data
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch communication preferences'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updatePreferences(preferences: Partial<CommunicationPreferences>) {
      this.loading = true
      this.error = null

      try {
        const response = await apiService.updateCommunicationPreferences(preferences)
        if (this.preferences) {
          this.preferences = { ...this.preferences, ...preferences }
        }
        return response.data
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to update communication preferences'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    },
  },
})
