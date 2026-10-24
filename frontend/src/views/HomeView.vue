<template>
  <div class="home-view">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="hero-content">
        <h1>Welcome to BC System</h1>
        <p>Your complete business communication platform</p>
        <div class="hero-actions">
          <router-link to="/dashboard" class="btn-primary"> Go to Dashboard </router-link>
          <router-link to="/communication" class="btn-secondary">
            Explore Communication
          </router-link>
        </div>
      </div>
      <div class="hero-image">
        <div class="communication-icons">
          <div class="icon-item">
            <span class="icon">📞</span>
            <span>Voice Calls</span>
          </div>
          <div class="icon-item">
            <span class="icon">💬</span>
            <span>Messaging</span>
          </div>
          <div class="icon-item">
            <span class="icon">📧</span>
            <span>Email</span>
          </div>
          <div class="icon-item">
            <span class="icon">📱</span>
            <span>WebRTC</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
      <div class="container">
        <h2>Powerful Communication Features</h2>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">📞</div>
            <h3>SIP Voice Calls</h3>
            <p>
              Make and receive high-quality voice calls with our SIP integration. Support for
              inbound and outbound calling with detailed call logs.
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">💬</div>
            <h3>SMS Messaging</h3>
            <p>
              Send and receive SMS messages with delivery tracking and message history. Perfect for
              business notifications and customer communication.
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">📧</div>
            <h3>Email Services</h3>
            <p>
              Send emails with attachment support and track delivery status. Integrated with your
              unified inbox for seamless communication management.
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🌐</div>
            <h3>WebRTC Calls</h3>
            <p>
              Real-time video and audio calling directly from your browser. No additional plugins
              required for modern web-based communication.
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">📨</div>
            <h3>Unified Inbox</h3>
            <p>
              View all your communications in one place. Messages, calls, and emails organized by
              date and priority for efficient management.
            </p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">💰</div>
            <h3>Wallet & Billing</h3>
            <p>
              Track your usage and manage billing with our integrated wallet system. Transparent
              pricing and detailed transaction history.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section" v-if="user">
      <div class="container">
        <h2>Your Communication Overview</h2>
        <div class="stats-overview">
          <div class="stat-item">
            <div class="stat-number">{{ totalMessages }}</div>
            <div class="stat-label">Total Messages</div>
          </div>
          <div class="stat-item">
            <div class="stat-number">{{ totalCalls }}</div>
            <div class="stat-label">Total Calls</div>
          </div>
          <div class="stat-item">
            <div class="stat-number">{{ activeServices }}</div>
            <div class="stat-label">Active Services</div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <h2>Ready to Get Started?</h2>
        <p>Explore your dashboard to manage all your communication needs.</p>
        <router-link to="/dashboard" class="btn-primary-large"> Access Your Dashboard </router-link>
      </div>
    </section>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useCommunicationStore } from '@/stores/communicationStore'

export default defineComponent({
  name: 'HomeView',
  data() {
    return {
      totalMessages: 0,
      totalCalls: 0,
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
    // Load basic stats if user is authenticated
    if (this.user) {
      await this.loadStats()
    }
  },
  methods: {
    async loadStats() {
      try {
        // This would normally load from API, but for now using placeholder data
        // In a real implementation, you'd call the API endpoints here
        this.totalMessages = 0 // Placeholder
        this.totalCalls = 0 // Placeholder
      } catch (error) {
        console.error('Failed to load stats:', error)
      }
    },
  },
})
</script>

<style scoped>
.home-view {
  min-height: 100vh;
}

.hero-section {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 60px 20px;
  display: flex;
  align-items: center;
  min-height: 60vh;
}

.hero-content {
  flex: 1;
  max-width: 600px;
}

.hero-content h1 {
  font-size: 3rem;
  margin-bottom: 1rem;
  font-weight: bold;
}

.hero-content p {
  font-size: 1.25rem;
  margin-bottom: 2rem;
  opacity: 0.9;
}

.hero-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn-primary,
.btn-secondary,
.btn-primary-large {
  padding: 12px 24px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 500;
  transition: all 0.3s ease;
  display: inline-block;
}

.btn-primary,
.btn-primary-large {
  background: white;
  color: #667eea;
  border: 2px solid white;
}

.btn-primary:hover,
.btn-primary-large:hover {
  background: transparent;
  color: white;
}

.btn-secondary {
  background: transparent;
  color: white;
  border: 2px solid white;
}

.btn-secondary:hover {
  background: white;
  color: #667eea;
}

.btn-primary-large {
  padding: 16px 32px;
  font-size: 1.1rem;
}

.hero-image {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
}

.communication-icons {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 2rem;
  max-width: 300px;
}

.icon-item {
  text-align: center;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  backdrop-filter: blur(10px);
}

.icon-item .icon {
  font-size: 2rem;
  display: block;
  margin-bottom: 0.5rem;
}

.icon-item span:last-child {
  font-size: 0.9rem;
  opacity: 0.9;
}

.features-section {
  padding: 80px 20px;
  background: #f8f9fa;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.features-section h2 {
  text-align: center;
  font-size: 2.5rem;
  margin-bottom: 3rem;
  color: #333;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 2rem;
}

.feature-card {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  text-align: center;
  transition: transform 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-5px);
}

.feature-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.feature-card h3 {
  font-size: 1.5rem;
  margin-bottom: 1rem;
  color: #333;
}

.feature-card p {
  color: #666;
  line-height: 1.6;
}

.stats-section {
  padding: 60px 20px;
  background: white;
}

.stats-section h2 {
  text-align: center;
  font-size: 2rem;
  margin-bottom: 3rem;
  color: #333;
}

.stats-overview {
  display: flex;
  justify-content: center;
  gap: 4rem;
  flex-wrap: wrap;
}

.stat-item {
  text-align: center;
}

.stat-number {
  font-size: 3rem;
  font-weight: bold;
  color: #667eea;
  margin-bottom: 0.5rem;
}

.stat-label {
  font-size: 1.1rem;
  color: #666;
  font-weight: 500;
}

.cta-section {
  padding: 60px 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  text-align: center;
}

.cta-section h2 {
  font-size: 2.5rem;
  margin-bottom: 1rem;
}

.cta-section p {
  font-size: 1.2rem;
  margin-bottom: 2rem;
  opacity: 0.9;
}

@media (max-width: 768px) {
  .hero-section {
    flex-direction: column;
    text-align: center;
    padding: 40px 20px;
  }

  .hero-content h1 {
    font-size: 2rem;
  }

  .communication-icons {
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-top: 2rem;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }

  .stats-overview {
    gap: 2rem;
  }

  .hero-actions,
  .stats-overview {
    flex-direction: column;
    align-items: center;
  }

  .btn-primary,
  .btn-secondary {
    width: 100%;
    text-align: center;
  }
}
</style>
