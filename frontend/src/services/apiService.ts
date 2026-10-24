import apiConfig from '@/config/api'
import axios, {
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type AxiosError,
} from 'axios'

class ApiService {
  private axiosInstance: AxiosInstance
  private token: string | null = null

  constructor() {
    this.axiosInstance = axios.create({
      baseURL: apiConfig.baseUrl,
      headers: apiConfig.headers,
    })

    // Add request interceptor for authentication
    this.axiosInstance.interceptors.request.use(
      (config) => {
        if (this.token) {
          config.headers.Authorization = `Bearer ${this.token}`
        }
        return config
      },
      (error) => {
        return Promise.reject(error)
      },
    )

    // Add response interceptor for error handling
    this.axiosInstance.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Handle unauthorized access
          this.clearToken()
          window.location.href = '/login'
        }
        return Promise.reject(error)
      },
    )
  }

  public setToken(token: string): void {
    this.token = token
    localStorage.setItem('authToken', token)
  }

  public getToken(): string | null {
    return this.token || localStorage.getItem('authToken')
  }

  public clearToken(): void {
    this.token = null
    localStorage.removeItem('authToken')
  }

  public async register(
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
  ): Promise<AxiosResponse> {
    return this.axiosInstance.post(apiConfig.endpoints.auth.register, {
      name,
      email,
      password,
      password_confirmation,
    })
  }

  public async login(
    email: string,
    password: string,
    device_name: string = 'web',
  ): Promise<AxiosResponse> {
    const response = await this.axiosInstance.post(apiConfig.endpoints.auth.login, {
      email,
      password,
      device_name,
    })

    if (response.data.access_token) {
      this.setToken(response.data.access_token)
    }

    return response
  }

  public async logout(): Promise<AxiosResponse> {
    const response = await this.axiosInstance.post(apiConfig.endpoints.auth.logout)
    this.clearToken()
    return response
  }

  public async getCurrentUser(): Promise<AxiosResponse> {
    return this.axiosInstance.get(apiConfig.endpoints.auth.me)
  }

  public async getCommunicationServices(): Promise<AxiosResponse> {
    return this.axiosInstance.get(apiConfig.endpoints.communication.services)
  }

  public async getCommunicationPreferences(): Promise<AxiosResponse> {
    return this.axiosInstance.get(apiConfig.endpoints.communication.preferences)
  }

  public async updateCommunicationPreferences(
    preferences: Record<string, unknown>,
  ): Promise<AxiosResponse> {
    return this.axiosInstance.post(apiConfig.endpoints.communication.preferences, preferences)
  }

  public async get<T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.axiosInstance.get<T>(url, config)
  }

  public async post<T>(
    url: string,
    data?: Record<string, unknown>,
    config?: AxiosRequestConfig,
  ): Promise<AxiosResponse<T>> {
    return this.axiosInstance.post<T>(url, data, config)
  }

  public async put<T>(
    url: string,
    data?: Record<string, unknown>,
    config?: AxiosRequestConfig,
  ): Promise<AxiosResponse<T>> {
    return this.axiosInstance.put<T>(url, data, config)
  }

  public async delete<T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.axiosInstance.delete<T>(url, config)
  }
}

export default new ApiService()
