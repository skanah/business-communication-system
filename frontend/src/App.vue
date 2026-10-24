<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()
const isMenuOpen = ref(false)

const toggleMenu = () => {
  isMenuOpen.value = !isMenuOpen.value
}

const logout = async () => {
  try {
    await authStore.logout()
    router.push('/login')
  } catch (error) {
    console.error('Logout error:', error)
  }
}

onMounted(() => {
  console.log('BCSYSTEM App component mounted!')
})
</script>

<template>
  <div id="app">
    <!-- Navigation Header -->
    <header class="app-header">
      <nav class="navbar">
        <div class="nav-brand">
          <router-link to="/" class="brand-link">
            <h2>BCSYSTEM</h2>
          </router-link>
        </div>

        <div class="nav-menu" :class="{ 'is-active': isMenuOpen }">
          <!-- Public Navigation -->
          <div v-if="!authStore.isAuthenticated" class="nav-links">
            <router-link to="/login" class="nav-link" @click="isMenuOpen = false"
              >Login</router-link
            >
            <router-link to="/register" class="nav-link" @click="isMenuOpen = false"
              >Register</router-link
            >
          </div>

          <!-- Authenticated Navigation -->
          <div v-else class="nav-links">
            <router-link to="/" class="nav-link" @click="isMenuOpen = false">Home</router-link>
            <router-link to="/dashboard" class="nav-link" @click="isMenuOpen = false"
              >Dashboard</router-link
            >
            <router-link to="/communication" class="nav-link" @click="isMenuOpen = false"
              >Communication</router-link
            >
            <router-link to="/settings" class="nav-link" @click="isMenuOpen = false"
              >Settings</router-link
            >
            <a href="#" class="nav-link" @click="logout">Logout</a>
          </div>
        </div>

        <div class="nav-toggle" @click="toggleMenu">
          <span class="bar"></span>
          <span class="bar"></span>
          <span class="bar"></span>
        </div>
      </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="app-footer">
      <div class="footer-content">
        <p>&copy; 2026 BCSYSTEM - Business Communication System</p>
        <p>SMS | Email | VoIP | Unified Inbox</p>
      </div>
    </footer>
  </div>
</template>

<style>
/* Global Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  line-height: 1.6;
  color: #333;
  background-color: #f8fafc;
}

#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* Navigation Styles */
.app-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.navbar {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 70px;
}

.nav-brand h2 {
  color: white;
  font-size: 1.8rem;
  font-weight: bold;
}

.brand-link {
  text-decoration: none;
  color: inherit;
}

.nav-links {
  display: flex;
  gap: 30px;
  align-items: center;
}

.nav-link {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  font-weight: 500;
  padding: 8px 16px;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.nav-link:hover,
.nav-link.router-link-active {
  color: white;
  background-color: rgba(255, 255, 255, 0.1);
}

.nav-toggle {
  display: none;
  flex-direction: column;
  cursor: pointer;
  gap: 4px;
}

.bar {
  width: 25px;
  height: 3px;
  background-color: white;
  border-radius: 2px;
  transition: 0.3s;
}

/* Main Content */
.main-content {
  flex: 1;
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px;
  width: 100%;
}

/* Footer */
.app-footer {
  background-color: #2d3748;
  color: #a0aec0;
  padding: 30px 20px;
  margin-top: auto;
}

.footer-content {
  max-width: 1200px;
  margin: 0 auto;
  text-align: center;
}

.footer-content p {
  margin-bottom: 8px;
}

/* Responsive Design */
@media (max-width: 768px) {
  .nav-links {
    position: absolute;
    top: 70px;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    flex-direction: column;
    padding: 20px;
    gap: 15px;
    transform: translateY(-100%);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
  }

  .nav-links.is-active {
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
  }

  .nav-toggle {
    display: flex;
  }

  .nav-toggle.is-active .bar:nth-child(1) {
    transform: rotate(-45deg) translate(-5px, 6px);
  }

  .nav-toggle.is-active .bar:nth-child(2) {
    opacity: 0;
  }

  .nav-toggle.is-active .bar:nth-child(3) {
    transform: rotate(45deg) translate(-5px, -6px);
  }

  .main-content {
    padding: 20px 15px;
  }

  .footer-content p {
    font-size: 14px;
  }
}

@media (max-width: 480px) {
  .navbar {
    padding: 0 15px;
  }

  .nav-brand h2 {
    font-size: 1.5rem;
  }

  .main-content {
    padding: 15px 10px;
  }
}
</style>
