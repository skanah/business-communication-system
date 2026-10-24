<template>
  <div class="not-found-view">
    <h1>404 - Page Not Found</h1>
    <p>The page you are looking for does not exist.</p>
    <router-link to="/">Go back to home</router-link>
  </div>
</template>

<script lang="ts">
export default {
  name: 'NotFoundView',
}
</script>

<style scoped>
.not-found-view {
  padding: 20px;
  text-align: center;
}
</style>
