<template>
  <div class="settings-view">
    <div class="settings-header">
      <h1>Settings</h1>
      <p>Manage your account and communication preferences</p>
    </div>

    <!-- Settings Navigation -->
    <div class="settings-nav">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="['nav-tab', { active: activeTab === tab.key }]"
      >
        <span class="tab-icon">{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- Account Settings -->
    <div v-if="activeTab === 'account'" class="settings-content">
      <div class="settings-section">
        <h2>Account Information</h2>
        <form @submit.prevent="updateAccount" class="settings-form">
          <div class="form-group">
            <label for="name">Full Name</label>
            <input id="name" v-model="accountForm.name" type="text" required />
          </div>
          <div class="form-group">
            <label for="email">Email Address</label>
            <input id="email" v-model="accountForm.email" type="email" required />
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">Update Account</button>
        </form>
      </div>

      <div class="settings-section">
        <h2>Change Password</h2>
        <form @submit.prevent="changePassword" class="settings-form">
          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input
              id="current_password"
              v-model="passwordForm.current_password"
              type="password"
              required
            />
          </div>
          <div class="form-group">
            <label for="password">New Password</label>
            <input id="password" v-model="passwordForm.password" type="password" required />
          </div>
          <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input
              id="password_confirmation"
              v-model="passwordForm.password_confirmation"
              type="password"
              required
            />
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">Change Password</button>
        </form>
      </div>
    </div>

    <!-- Communication Settings -->
    <div v-if="activeTab === 'communication'" class="settings-content">
      <div class="settings-section">
        <h2>Communication Preferences</h2>
        <form @submit.prevent="updateCommunicationPreferences" class="settings-form">
          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input v-model="preferencesForm.sms_notifications" type="checkbox" />
              <span class="checkmark"></span>
              SMS Notifications
            </label>
          </div>
          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input v-model="preferencesForm.email_notifications" type="checkbox" />
              <span class="checkmark"></span>
              Email Notifications
            </label>
          </div>
          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input v-model="preferencesForm.webrtc_enabled" type="checkbox" />
              <span class="checkmark"></span>
              Enable WebRTC Calls
            </label>
          </div>
          <div class="form-group">
            <label for="call_forwarding">Call Forwarding Number</label>
            <input
              id="call_forwarding"
              v-model="preferencesForm.call_forwarding"
              type="tel"
              placeholder="Optional forwarding number"
            />
          </div>
          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input v-model="preferencesForm.voicemail" type="checkbox" />
              <span class="checkmark"></span>
              Enable Voicemail
            </label>
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">Update Preferences</button>
        </form>
      </div>
    </div>

    <!-- Notification Settings -->
    <div v-if="activeTab === 'notifications'" class="settings-content">
      <div class="settings-section">
        <h2>Notification Settings</h2>
        <div class="notification-settings">
          <div class="notification-item">
            <div class="notification-info">
              <h3>Call Notifications</h3>
              <p>Get notified when you receive incoming calls</p>
            </div>
            <label class="toggle">
              <input v-model="notificationSettings.call_notifications" type="checkbox" />
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="notification-item">
            <div class="notification-info">
              <h3>Message Notifications</h3>
              <p>Get notified when you receive new messages</p>
            </div>
            <label class="toggle">
              <input v-model="notificationSettings.message_notifications" type="checkbox" />
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="notification-item">
            <div class="notification-info">
              <h3>Email Notifications</h3>
              <p>Get notified when you receive new emails</p>
            </div>
            <label class="toggle">
              <input v-model="notificationSettings.email_notifications" type="checkbox" />
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="notification-item">
            <div class="notification-info">
              <h3>System Notifications</h3>
              <p>Get notified about system updates and maintenance</p>
            </div>
            <label class="toggle">
              <input v-model="notificationSettings.system_notifications" type="checkbox" />
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
        <button @click="updateNotificationSettings" class="btn-primary" :disabled="loading">
          Save Notification Settings
        </button>
      </div>
    </div>

    <!-- Security Settings -->
    <div v-if="activeTab === 'security'" class="settings-content">
      <div class="settings-section">
        <h2>Security Settings</h2>
        <div class="security-info">
          <div class="info-item">
            <h3>Two-Factor Authentication</h3>
            <p>Add an extra layer of security to your account</p>
            <button class="btn-secondary">Enable 2FA</button>
          </div>
          <div class="info-item">
            <h3>Active Sessions</h3>
            <p>Manage your active login sessions</p>
            <button class="btn-secondary">View Sessions</button>
          </div>
          <div class="info-item">
            <h3>API Keys</h3>
            <p>Manage API keys for integrations</p>
            <button class="btn-secondary">Manage Keys</button>
          </div>
        </div>
      </div>

      <div class="settings-section">
        <h2>Danger Zone</h2>
        <div class="danger-zone">
          <div class="danger-item">
            <div>
              <h3>Delete Account</h3>
              <p>Permanently delete your account and all associated data</p>
            </div>
            <button class="btn-danger">Delete Account</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading/Error States -->
    <div v-if="loading" class="loading-overlay">
      <div class="spinner"></div>
      <p>Updating settings...</p>
    </div>

    <div v-if="error" class="error-message">
      {{ error }}
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useCommunicationStore } from '@/stores/communicationStore'
import apiService from '@/services/apiService'

interface AccountForm {
  name: string
  email: string
}

interface PasswordForm {
  current_password: string
  password: string
  password_confirmation: string
}

interface PreferencesForm {
  sms_notifications: boolean
  email_notifications: boolean
  webrtc_enabled: boolean
  call_forwarding: string
  voicemail: boolean
}

interface NotificationSettings {
  call_notifications: boolean
  message_notifications: boolean
  email_notifications: boolean
  system_notifications: boolean
}

export default defineComponent({
  name: 'SettingsView',
  data() {
    return {
      activeTab: 'account',
      loading: false,
      error: '',
      tabs: [
        { key: 'account', label: 'Account', icon: '👤' },
        { key: 'communication', label: 'Communication', icon: '📱' },
        { key: 'notifications', label: 'Notifications', icon: '🔔' },
        { key: 'security', label: 'Security', icon: '🔒' },
      ],
      accountForm: {
        name: '',
        email: '',
      } as AccountForm,
      passwordForm: {
        current_password: '',
        password: '',
        password_confirmation: '',
      } as PasswordForm,
      preferencesForm: {
        sms_notifications: true,
        email_notifications: true,
        webrtc_enabled: true,
        call_forwarding: '',
        voicemail: true,
      } as PreferencesForm,
      notificationSettings: {
        call_notifications: true,
        message_notifications: true,
        email_notifications: true,
        system_notifications: false,
      } as NotificationSettings,
    }
  },
  computed: {
    user() {
      return useAuthStore().user
    },
  },
  async mounted() {
    await this.loadSettings()
  },
  methods: {
    async loadSettings() {
      this.loading = true
      try {
        // Load user data
        if (this.user) {
          this.accountForm.name = this.user.name
          this.accountForm.email = this.user.email
        }

        // Load communication preferences
        const communicationStore = useCommunicationStore()
        await communicationStore.fetchPreferences()
        const preferences = communicationStore.preferences
        if (preferences) {
          this.preferencesForm = {
            sms_notifications: preferences.sms_notifications || true,
            email_notifications: preferences.email_notifications || true,
            webrtc_enabled: preferences.webrtc_enabled || true,
            call_forwarding: preferences.call_forwarding || '',
            voicemail: preferences.voicemail || true,
          }
        }
      } catch (error) {
        console.error('Failed to load settings:', error)
        this.error = 'Failed to load settings'
      } finally {
        this.loading = false
      }
    },

    async updateAccount() {
      this.loading = true
      this.error = ''
      try {
        // In a real implementation, you'd call an API to update account info
        // For now, just show success
        await new Promise((resolve) => setTimeout(resolve, 1000)) // Simulate API call
        this.error = '' // Clear any errors
        alert('Account updated successfully!')
      } catch (error) {
        this.error = 'Failed to update account'
      } finally {
        this.loading = false
      }
    },

    async changePassword() {
      if (this.passwordForm.password !== this.passwordForm.password_confirmation) {
        this.error = 'Passwords do not match'
        return
      }

      this.loading = true
      this.error = ''
      try {
        // In a real implementation, you'd call an API to change password
        await new Promise((resolve) => setTimeout(resolve, 1000)) // Simulate API call
        this.passwordForm = {
          current_password: '',
          password: '',
          password_confirmation: '',
        }
        alert('Password changed successfully!')
      } catch (error) {
        this.error = 'Failed to change password'
      } finally {
        this.loading = false
      }
    },

    async updateCommunicationPreferences() {
      this.loading = true
      this.error = ''
      try {
        const communicationStore = useCommunicationStore()
        await communicationStore.updatePreferences(this.preferencesForm)
        alert('Communication preferences updated successfully!')
      } catch (error) {
        this.error = 'Failed to update communication preferences'
      } finally {
        this.loading = false
      }
    },

    async updateNotificationSettings() {
      this.loading = true
      this.error = ''
      try {
        // In a real implementation, you'd call an API to update notification settings
        await new Promise((resolve) => setTimeout(resolve, 1000)) // Simulate API call
        alert('Notification settings updated successfully!')
      } catch (error) {
        this.error = 'Failed to update notification settings'
      } finally {
        this.loading = false
      }
    },
  },
})
</script>

<style scoped>
.settings-view {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.settings-header {
  margin-bottom: 30px;
  text-align: center;
}

.settings-header h1 {
  color: #333;
  margin-bottom: 5px;
}

.settings-header p {
  color: #666;
}

.settings-nav {
  display: flex;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 20px;
  overflow: hidden;
}

.nav-tab {
  flex: 1;
  padding: 15px 20px;
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
  font-size: 14px;
  font-weight: 500;
}

.nav-tab:hover {
  background: #f8f9fa;
}

.nav-tab.active {
  background: #667eea;
  color: white;
}

.tab-icon {
  font-size: 16px;
}

.settings-content {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  padding: 30px;
}

.settings-section {
  margin-bottom: 40px;
}

.settings-section:last-child {
  margin-bottom: 0;
}

.settings-section h2 {
  color: #333;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #f0f0f0;
}

.settings-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-group label {
  font-weight: 500;
  color: #333;
}

.form-group input {
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}

.checkbox-group {
  margin-bottom: 15px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-weight: normal;
}

.checkbox-label input[type='checkbox'] {
  margin-right: 10px;
  width: 18px;
  height: 18px;
}

.btn-primary,
.btn-secondary,
.btn-danger {
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  align-self: flex-start;
}

.btn-primary {
  background: #667eea;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #5a67d8;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover {
  background: #c82333;
}

.notification-settings {
  margin-bottom: 30px;
}

.notification-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 0;
  border-bottom: 1px solid #f0f0f0;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-info h3 {
  margin: 0 0 5px 0;
  color: #333;
}

.notification-info p {
  margin: 0;
  color: #666;
  font-size: 14px;
}

.toggle {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: 0.4s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: '';
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
}

.toggle input:checked + .toggle-slider {
  background-color: #667eea;
}

.toggle input:checked + .toggle-slider:before {
  transform: translateX(26px);
}

.security-info {
  margin-bottom: 40px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 0;
  border-bottom: 1px solid #f0f0f0;
}

.info-item:last-child {
  border-bottom: none;
}

.info-item h3 {
  margin: 0 0 5px 0;
  color: #333;
}

.info-item p {
  margin: 0;
  color: #666;
  font-size: 14px;
}

.danger-zone {
  border: 2px solid #dc3545;
  border-radius: 8px;
  padding: 20px;
}

.danger-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.danger-item h3 {
  margin: 0 0 5px 0;
  color: #dc3545;
}

.danger-item p {
  margin: 0;
  color: #666;
  font-size: 14px;
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.error-message {
  background: #f8d7da;
  color: #721c24;
  padding: 12px;
  border-radius: 6px;
  margin-top: 20px;
  border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
  .settings-view {
    padding: 15px;
  }

  .settings-nav {
    flex-direction: column;
  }

  .settings-content {
    padding: 20px;
  }

  .notification-item,
  .info-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .danger-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
}
</style>
