<template>
  <div class="login-view">
    <h1 style="color: red; font-size: 2em">LOGIN PAGE - This should be visible!</h1>
    <p style="color: blue; font-size: 1.2em">Please login to access the application</p>
    <form @submit.prevent="handleSubmit">
      <div>
        <label for="email">Email:</label>
        <input type="email" id="email" v-model="email" required />
      </div>
      <div>
        <label for="password">Password:</label>
        <input type="password" id="password" v-model="password" required />
      </div>
      <button
        type="submit"
        style="background-color: red; color: white; padding: 12px 20px; font-size: 1.1em"
      >
        Login
      </button>
    </form>
    <p style="margin-top: 20px; color: green">
      If you don't have an account, go to
      <router-link to="/register" style="color: purple; font-weight: bold">Register</router-link>
    </p>
  </div>
</template>

<script lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

export default {
  name: 'LoginView',
  setup() {
    const email = ref('')
    const password = ref('')
    const router = useRouter()
    const authStore = useAuthStore()

    const handleSubmit = async () => {
      try {
        await authStore.login(email.value, password.value)
        router.push({ name: 'dashboard' })
      } catch (error) {
        console.error('Login failed:', error)
      }
    }

    return {
      email,
      password,
      handleSubmit,
    }
  },
}
</script>

<style scoped>
.login-view {
  padding: 20px;
  max-width: 400px;
  margin: 0 auto;
}

form div {
  margin-bottom: 15px;
}

label {
  display: block;
  margin-bottom: 5px;
}

input {
  width: 100%;
  padding: 8px;
  box-sizing: border-box;
}

button {
  padding: 10px 15px;
  background-color: #42b983;
  color: white;
  border: none;
  cursor: pointer;
}
</style>
