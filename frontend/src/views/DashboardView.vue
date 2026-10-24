<template>
  <div class="dashboard-view">
    <div class="dashboard-header">
      <h1>Dashboard</h1>
      <p class="welcome-message">Welcome back, {{ user?.name }}!</p>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">📧</div>
        <div class="stat-content">
          <h3>{{ inboxStats?.total || 0 }}</h3>
          <p>Total Messages</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📞</div>
        <div class="stat-content">
          <h3>{{ callStats?.total || 0 }}</h3>
          <p>Total Calls</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-content">
          <h3>${{ walletBalance || '0.00' }}</h3>
          <p>Wallet Balance</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⚙️</div>
        <div class="stat-content">
          <h3>{{ activeServices }}</h3>
          <p>Active Services</p>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity">
      <h2>Recent Activity</h2>
      <div class="activity-list">
        <div class="activity-item" v-if="recentMessages.length === 0 && recentCalls.length === 0">
          <p class="no-activity">No recent activity</p>
        </div>
        <div v-for="message in recentMessages.slice(0, 3)" :key="message.id" class="activity-item">
          <div class="activity-icon">📧</div>
          <div class="activity-content">
            <p class="activity-title">New message from {{ message.sender }}</p>
            <p class="activity-time">{{ formatTime(message.created_at) }}</p>
          </div>
        </div>
        <div v-for="call in recentCalls.slice(0, 3)" :key="call.id" class="activity-item">
          <div class="activity-icon">📞</div>
          <div class="activity-content">
            <p class="activity-title">
              {{ call.direction === 'inbound' ? 'Incoming' : 'Outgoing' }} call
            </p>
            <p class="activity-time">{{ formatTime(call.created_at) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h2>Quick Actions</h2>
      <div class="action-buttons">
        <router-link to="/communication" class="action-btn">
          <span class="action-icon">📱</span>
          <span>Communication Hub</span>
        </router-link>
        <router-link to="/settings" class="action-btn">
          <span class="action-icon">⚙️</span>
          <span>Settings</span>
        </router-link>
        <button @click="refreshData" class="action-btn" :disabled="loading">
          <span class="action-icon">🔄</span>
          <span>Refresh</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useCommunicationStore } from '@/stores/communicationStore'
import apiService from '@/services/apiService'

interface InboxStats {
  total: number
  unread: number
}

interface CallStats {
  total: number
  inbound: number
  outbound: number
}

interface Message {
  id: number
  sender: string
  created_at: string
}

interface Call {
  id: number
  direction: string
  created_at: string
}

export default defineComponent({
  name: 'DashboardView',
  data() {
    return {
      inboxStats: null as InboxStats | null,
      callStats: null as CallStats | null,
      walletBalance: '0.00',
      recentMessages: [] as Message[],
      recentCalls: [] as Call[],
      loading: false,
    }
  },
  computed: {
    user() {
      return useAuthStore().user
    },
    activeServices() {
      const services = useCommunicationStore().services
      if (!services) return 0
      return Object.values(services).filter((service) => service.status === 'active').length
    },
  },
  async mounted() {
    await this.loadDashboardData()
  },
  methods: {
    async loadDashboardData() {
      this.loading = true
      try {
        // Load inbox stats
        const inboxResponse = await apiService.get('/inbox/stats')
        this.inboxStats = inboxResponse.data as InboxStats

        // Load call stats (assuming SIP calls for now)
        const callResponse = await apiService.get('/sip/stats')
        this.callStats = callResponse.data as CallStats

        // Load wallet balance (placeholder - would need wallet endpoint)
        // this.walletBalance = await apiService.get('/wallet/balance')

        // Load recent messages
        const messagesResponse = await apiService.get('/inbox/messages?limit=3')
        this.recentMessages = (messagesResponse.data as { data?: Message[] }).data || []

        // Load recent calls
        const callsResponse = await apiService.get('/sip/calls?limit=3')
        this.recentCalls = (callsResponse.data as { data?: Call[] }).data || []
      } catch (error) {
        console.error('Failed to load dashboard data:', error)
      } finally {
        this.loading = false
      }
    },
    formatTime(dateString: string) {
      const date = new Date(dateString)
      const now = new Date()
      const diffMs = now.getTime() - date.getTime()
      const diffMins = Math.floor(diffMs / 60000)

      if (diffMins < 1) return 'Just now'
      if (diffMins < 60) return `${diffMins}m ago`
      if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`
      return date.toLocaleDateString()
    },
    async refreshData() {
      await this.loadDashboardData()
    },
  },
})
</script>

<style scoped>
.dashboard-view {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.dashboard-header {
  margin-bottom: 30px;
}

.dashboard-header h1 {
  color: #333;
  margin-bottom: 5px;
}

.welcome-message {
  color: #666;
  font-size: 16px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  font-size: 32px;
}

.stat-content h3 {
  margin: 0;
  font-size: 24px;
  font-weight: bold;
  color: #333;
}

.stat-content p {
  margin: 5px 0 0 0;
  color: #666;
  font-size: 14px;
}

.recent-activity,
.quick-actions {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 20px;
}

.recent-activity h2,
.quick-actions h2 {
  margin-top: 0;
  color: #333;
  border-bottom: 2px solid #f0f0f0;
  padding-bottom: 10px;
}

.activity-list {
  margin-top: 15px;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-icon {
  font-size: 20px;
  min-width: 20px;
}

.activity-content {
  flex: 1;
}

.activity-title {
  margin: 0;
  color: #333;
  font-size: 14px;
}

.activity-time {
  margin: 2px 0 0 0;
  color: #999;
  font-size: 12px;
}

.no-activity {
  text-align: center;
  color: #999;
  font-style: italic;
  margin: 20px 0;
}

.action-buttons {
  display: flex;
  gap: 15px;
  margin-top: 15px;
  flex-wrap: wrap;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  text-decoration: none;
  color: #333;
  font-size: 14px;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #e9ecef;
  border-color: #adb5bd;
}

.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.action-icon {
  font-size: 16px;
}

@media (max-width: 768px) {
  .dashboard-view {
    padding: 15px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .action-buttons {
    flex-direction: column;
  }

  .action-btn {
    justify-content: center;
  }
}
</style>
