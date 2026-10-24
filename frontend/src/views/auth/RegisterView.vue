<template>
  <div class="register-view">
    <h1>Register</h1>
    <form @submit.prevent="handleSubmit">
      <div>
        <label for="name">Name:</label>
        <input type="text" id="name" v-model="name" required />
      </div>
      <div>
        <label for="email">Email:</label>
        <input type="email" id="email" v-model="email" required />
      </div>
      <div>
        <label for="password">Password:</label>
        <input type="password" id="password" v-model="password" required />
      </div>
      <div>
        <label for="password_confirmation">Confirm Password:</label>
        <input type="password" id="password_confirmation" v-model="passwordConfirmation" required />
      </div>
      <button type="submit">Register</button>
    </form>
  </div>
</template>

<script lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

export default {
  name: 'RegisterView',
  setup() {
    const name = ref('')
    const email = ref('')
    const password = ref('')
    const passwordConfirmation = ref('')
    const router = useRouter()
    const authStore = useAuthStore()

    const handleSubmit = async () => {
      try {
        await authStore.register(
          name.value,
          email.value,
          password.value,
          passwordConfirmation.value,
        )
        router.push({ name: 'dashboard' })
      } catch (error: any) {
        console.error('Registration failed:', error)

        // Display validation errors
        if (error.response?.status === 422 && error.response?.data?.errors) {
          const errors = error.response.data.errors
          let errorMessage = 'Validation failed:\n'

          Object.keys(errors).forEach((field) => {
            errorMessage += `${field}: ${errors[field].join(', ')}\n`
          })

          alert(errorMessage)
        } else if (error.response?.data?.message) {
          alert(`Registration failed: ${error.response.data.message}`)
        } else {
          alert('Registration failed. Please check console for details.')
        }
      }
    }

    return {
      name,
      email,
      password,
      passwordConfirmation,
      handleSubmit,
    }
  },
}
</script>

<style scoped>
.register-view {
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
