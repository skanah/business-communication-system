// API Configuration for Laravel Backend
const API_BASE_URL = 'http://localhost:8000/api'

export const apiConfig = {
  baseUrl: API_BASE_URL,
  endpoints: {
    auth: {
      register: '/register',
      login: '/login',
      logout: '/logout',
      me: '/me',
    },
    communication: {
      services: '/communication/services',
      preferences: '/communication/preferences',
    },
    inbox: {
      messages: '/inbox/messages',
      stats: '/inbox/stats',
    },
  },
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
}

export default apiConfig
