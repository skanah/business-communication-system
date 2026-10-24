<template>
  <div class="communication-view">
    <div class="communication-header">
      <h1>Communication Hub</h1>
      <p>Manage all your communication services in one place</p>
    </div>

    <!-- Service Status Overview -->
    <div class="service-overview">
      <h2>Service Status</h2>
      <div class="service-grid">
        <div
          v-for="service in services"
          :key="service.key"
          class="service-card"
          :class="{
            'service-active': service.status === 'active',
            'service-inactive': service.status !== 'active',
          }"
        >
          <div class="service-icon">{{ service.icon }}</div>
          <div class="service-info">
            <h3>{{ service.name }}</h3>
            <p>{{ service.description }}</p>
            <span class="status-badge" :class="service.status">
              {{ service.status }}
            </span>
          </div>
          <div class="service-actions">
            <button
              v-if="service.status === 'active'"
              @click="configureService(service.key)"
              class="btn-configure"
            >
              Configure
            </button>
            <button v-else @click="activateService(service.key)" class="btn-activate">
              Activate
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h2>Quick Actions</h2>
      <div class="action-buttons">
        <router-link to="/communication/services" class="action-btn">
          <span class="action-icon">⚙️</span>
          <span>Manage Services</span>
        </router-link>
        <router-link to="/communication/preferences" class="action-btn">
          <span class="action-icon">🔧</span>
          <span>Communication Preferences</span>
        </router-link>
        <router-link to="/communication/unified-inbox" class="action-btn">
          <span class="action-icon">📨</span>
          <span>Unified Inbox</span>
        </router-link>
        <button @click="makeCall" class="action-btn">
          <span class="action-icon">📞</span>
          <span>Make a Call</span>
        </button>
        <button @click="sendMessage" class="action-btn">
          <span class="action-icon">💬</span>
          <span>Send Message</span>
        </button>
        <button @click="composeEmail" class="action-btn">
          <span class="action-icon">✉️</span>
          <span>Compose Email</span>
        </button>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity">
      <h2>Recent Communication Activity</h2>
      <div class="activity-list">
        <div class="activity-item" v-if="recentActivity.length === 0">
          <p class="no-activity">No recent communication activity</p>
        </div>
        <div v-for="activity in recentActivity" :key="activity.id" class="activity-item">
          <div class="activity-icon">{{ activity.icon }}</div>
          <div class="activity-content">
            <p class="activity-title">{{ activity.title }}</p>
            <p class="activity-time">{{ formatTime(activity.timestamp) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Communication Stats -->
    <div class="communication-stats">
      <h2>Communication Statistics</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📞</div>
          <div class="stat-content">
            <h3>{{ stats.calls || 0 }}</h3>
            <p>Total Calls</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">💬</div>
          <div class="stat-content">
            <h3>{{ stats.messages || 0 }}</h3>
            <p>Total Messages</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📧</div>
          <div class="stat-content">
            <h3>{{ stats.emails || 0 }}</h3>
            <p>Total Emails</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📨</div>
          <div class="stat-content">
            <h3>{{ stats.unread || 0 }}</h3>
            <p>Unread Messages</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Sub-view Content -->
    <div class="sub-view-container">
      <router-view />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useCommunicationStore } from '@/stores/communicationStore'
import apiService from '@/services/apiService'

interface Service {
  key: string
  name: string
  description: string
  icon: string
  status: string
}

interface Activity {
  id: number
  title: string
  icon: string
  timestamp: string
}

interface Stats {
  calls: number
  messages: number
  emails: number
  unread: number
}

export default defineComponent({
  name: 'CommunicationView',
  data() {
    return {
      services: [] as Service[],
      recentActivity: [] as Activity[],
      stats: {
        calls: 0,
        messages: 0,
        emails: 0,
        unread: 0,
      } as Stats,
      loading: false,
    }
  },
  async mounted() {
    await this.loadCommunicationData()
  },
  methods: {
    async loadCommunicationData() {
      this.loading = true
      try {
        const communicationStore = useCommunicationStore()

        // Load services status
        await communicationStore.fetchServices()
        const servicesData = communicationStore.services

        if (servicesData) {
          this.services = [
            {
              key: 'sip',
              name: 'SIP Voice Calls',
              description: 'Make and receive high-quality voice calls',
              icon: '📞',
              status: servicesData.sip?.status || 'inactive',
            },
            {
              key: 'webrtc',
              name: 'WebRTC Calls',
              description: 'Real-time video and audio calling',
              icon: '🌐',
              status: servicesData.webrtc?.status || 'inactive',
            },
            {
              key: 'sms',
              name: 'SMS Messaging',
              description: 'Send and receive text messages',
              icon: '💬',
              status: servicesData.sms?.status || 'inactive',
            },
            {
              key: 'email',
              name: 'Email Services',
              description: 'Send and receive emails with attachments',
              icon: '📧',
              status: servicesData.email?.status || 'inactive',
            },
            {
              key: 'unified_inbox',
              name: 'Unified Inbox',
              description: 'View all communications in one place',
              icon: '📨',
              status: servicesData.unified_inbox?.status || 'inactive',
            },
          ]
        }

        // Load recent activity (placeholder data - would come from API)
        this.recentActivity = [
          // This would be populated from actual API calls
        ]

        // Load stats (placeholder - would come from API)
        this.stats = {
          calls: 0,
          messages: 0,
          emails: 0,
          unread: 0,
        }
      } catch (error) {
        console.error('Failed to load communication data:', error)
      } finally {
        this.loading = false
      }
    },

    async configureService(serviceKey: string) {
      // Navigate to service configuration
      this.$router.push(`/communication/services#${serviceKey}`)
    },

    async activateService(serviceKey: string) {
      // This would call an API to activate the service
      alert(`Activating ${serviceKey} service...`)
    },

    makeCall() {
      // This would open a call dialog or navigate to call interface
      alert('Call functionality would be implemented here')
    },

    sendMessage() {
      // This would open a message dialog
      alert('Message functionality would be implemented here')
    },

    composeEmail() {
      // This would open an email composer
      alert('Email composer would be implemented here')
    },

    formatTime(timestamp: string) {
      const date = new Date(timestamp)
      const now = new Date()
      const diffMs = now.getTime() - date.getTime()
      const diffMins = Math.floor(diffMs / 60000)

      if (diffMins < 1) return 'Just now'
      if (diffMins < 60) return `${diffMins}m ago`
      if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`
      return date.toLocaleDateString()
    },
  },
})
</script>

<style scoped>
.communication-view {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.communication-header {
  text-align: center;
  margin-bottom: 40px;
}

.communication-header h1 {
  color: #333;
  margin-bottom: 10px;
}

.communication-header p {
  color: #666;
  font-size: 16px;
}

.service-overview h2,
.quick-actions h2,
.recent-activity h2,
.communication-stats h2 {
  color: #333;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #f0f0f0;
}

.service-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.service-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 15px;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.service-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.service-active {
  border-left: 4px solid #28a745;
}

.service-inactive {
  border-left: 4px solid #dc3545;
}

.service-icon {
  font-size: 32px;
  min-width: 32px;
}

.service-info {
  flex: 1;
}

.service-info h3 {
  margin: 0 0 5px 0;
  color: #333;
}

.service-info p {
  margin: 0 0 10px 0;
  color: #666;
  font-size: 14px;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
}

.service-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.btn-configure,
.btn-activate {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-configure {
  background: #007bff;
  color: white;
}

.btn-configure:hover {
  background: #0056b3;
}

.btn-activate {
  background: #28a745;
  color: white;
}

.btn-activate:hover {
  background: #1e7e34;
}

.quick-actions {
  margin-bottom: 40px;
}

.action-buttons {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px 20px;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  text-decoration: none;
  color: #333;
  font-size: 14px;
  transition: all 0.2s ease;
}

.action-btn:hover {
  background: #e9ecef;
  border-color: #adb5bd;
  transform: translateY(-1px);
}

.action-icon {
  font-size: 18px;
}

.recent-activity {
  margin-bottom: 40px;
}

.activity-list {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px 20px;
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
  margin: 0 0 5px 0;
  color: #333;
  font-size: 14px;
}

.activity-time {
  margin: 0;
  color: #999;
  font-size: 12px;
}

.no-activity {
  text-align: center;
  color: #999;
  font-style: italic;
  padding: 40px 20px;
  margin: 0;
}

.communication-stats {
  margin-bottom: 40px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 15px;
  text-align: center;
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

.sub-view-container {
  margin-top: 40px;
}

@media (max-width: 768px) {
  .communication-view {
    padding: 15px;
  }

  .service-grid {
    grid-template-columns: 1fr;
  }

  .action-buttons {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .service-card {
    flex-direction: column;
    text-align: center;
    gap: 10px;
  }

  .activity-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
}
</style>
