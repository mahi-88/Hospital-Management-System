<template>
  <div id="app" class="min-h-screen bg-gray-900 text-white">
    <!-- Navigation Header -->
    <nav class="bg-gray-800 shadow-lg">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <h1 class="text-xl font-bold text-blue-400">
                🌐 Network Packet Visualizer
              </h1>
            </div>
            <div class="hidden md:block ml-10">
              <div class="flex items-baseline space-x-4">
                <router-link
                  to="/"
                  class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700 transition-colors"
                  :class="{ 'bg-gray-700': $route.path === '/' }"
                >
                  Dashboard
                </router-link>
                <router-link
                  to="/simulator"
                  class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700 transition-colors"
                  :class="{ 'bg-gray-700': $route.path === '/simulator' }"
                >
                  Packet Simulator
                </router-link>
                <router-link
                  to="/osi-layers"
                  class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700 transition-colors"
                  :class="{ 'bg-gray-700': $route.path === '/osi-layers' }"
                >
                  OSI Layers
                </router-link>
                <router-link
                  to="/analytics"
                  class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700 transition-colors"
                  :class="{ 'bg-gray-700': $route.path === '/analytics' }"
                >
                  Analytics
                </router-link>
              </div>
            </div>
          </div>
          
          <!-- Connection Status -->
          <div class="flex items-center">
            <div class="flex items-center space-x-2">
              <div 
                :class="connectionStatus === 'connected' ? 'bg-green-500' : 'bg-red-500'"
                class="w-3 h-3 rounded-full animate-pulse"
              ></div>
              <span class="text-sm">
                {{ connectionStatus === 'connected' ? 'Connected' : 'Disconnected' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 mt-12">
      <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
        <div class="text-center text-gray-400 text-sm">
          <p>&copy; 2024 Network Packet Visualizer. Built by Mahi for educational purposes.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useNetworkStore } from './store/network'

export default {
  name: 'App',
  setup() {
    const networkStore = useNetworkStore()
    const connectionStatus = ref('disconnected')

    onMounted(() => {
      // Initialize SignalR connection
      networkStore.initializeConnection()
      
      // Monitor connection status
      setInterval(() => {
        connectionStatus.value = networkStore.isConnected ? 'connected' : 'disconnected'
      }, 1000)
    })

    return {
      connectionStatus
    }
  }
}
</script>

<style>
#app {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #1f2937;
}

::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

/* Animations */
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Transitions */
.transition-colors {
  transition-property: color, background-color, border-color;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}
</style>
