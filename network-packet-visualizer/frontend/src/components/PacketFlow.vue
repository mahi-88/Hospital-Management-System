<template>
  <div class="packet-flow-container bg-gray-800 rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-blue-400">Packet Flow Simulation</h2>
      
      <!-- Control Panel -->
      <div class="flex items-center space-x-4">
        <select
          v-model="selectedProtocol"
          class="bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white"
          @change="updateProtocol"
        >
          <option value="TCP">TCP</option>
          <option value="UDP">UDP</option>
          <option value="ICMP">ICMP</option>
          <option value="HTTP">HTTP</option>
          <option value="DNS">DNS</option>
        </select>
        
        <button
          @click="simulatePacket"
          :disabled="isSimulating"
          class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 px-4 py-2 rounded font-medium transition-colors"
        >
          {{ isSimulating ? 'Simulating...' : 'Send Packet' }}
        </button>
        
        <button
          @click="clearSimulation"
          class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded font-medium transition-colors"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Network Topology -->
    <div class="network-topology mb-8">
      <svg width="100%" height="300" class="bg-gray-900 rounded-lg">
        <!-- Source Device -->
        <g id="source" transform="translate(50, 150)">
          <rect x="-30" y="-20" width="60" height="40" rx="5" fill="#3b82f6" />
          <text x="0" y="5" text-anchor="middle" fill="white" font-size="12">Source</text>
          <text x="0" y="-35" text-anchor="middle" fill="#9ca3af" font-size="10">{{ sourceIP }}</text>
        </g>

        <!-- Router/Switch -->
        <g id="router" transform="translate(400, 150)">
          <polygon points="-25,-20 25,-20 30,0 25,20 -25,20 -30,0" fill="#10b981" />
          <text x="0" y="5" text-anchor="middle" fill="white" font-size="12">Router</text>
        </g>

        <!-- Destination Device -->
        <g id="destination" transform="translate(750, 150)">
          <rect x="-30" y="-20" width="60" height="40" rx="5" fill="#8b5cf6" />
          <text x="0" y="5" text-anchor="middle" fill="white" font-size="12">Dest</text>
          <text x="0" y="-35" text-anchor="middle" fill="#9ca3af" font-size="10">{{ destinationIP }}</text>
        </g>

        <!-- Connection Lines -->
        <line x1="80" y1="150" x2="370" y2="150" stroke="#6b7280" stroke-width="2" />
        <line x1="430" y1="150" x2="720" y2="150" stroke="#6b7280" stroke-width="2" />

        <!-- Animated Packet -->
        <g
          v-if="packetVisible"
          :transform="`translate(${packetX}, ${packetY})`"
          class="packet-animation"
        >
          <circle r="8" :fill="protocolColors[selectedProtocol]" />
          <text y="4" text-anchor="middle" fill="white" font-size="10">{{ selectedProtocol }}</text>
        </g>

        <!-- Packet Trail -->
        <g v-if="showTrail">
          <path
            v-for="(point, index) in packetTrail"
            :key="index"
            :d="`M ${point.x} ${point.y} L ${point.x + 2} ${point.y}`"
            :stroke="protocolColors[selectedProtocol]"
            :stroke-opacity="0.3 - (index * 0.05)"
            stroke-width="2"
          />
        </g>
      </svg>
    </div>

    <!-- Packet Details Panel -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Packet Headers -->
      <div class="packet-headers bg-gray-900 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-green-400 mb-3">Packet Headers</h3>
        
        <div v-if="currentPacket" class="space-y-3">
          <!-- Ethernet Header -->
          <div class="header-section">
            <h4 class="font-medium text-blue-300 mb-2">Ethernet Header (Layer 2)</h4>
            <div class="bg-gray-800 rounded p-3 font-mono text-sm">
              <div class="grid grid-cols-2 gap-2 text-xs">
                <span class="text-gray-400">Dest MAC:</span>
                <span class="text-green-400">{{ currentPacket.ethernet?.destMac }}</span>
                <span class="text-gray-400">Src MAC:</span>
                <span class="text-green-400">{{ currentPacket.ethernet?.srcMac }}</span>
                <span class="text-gray-400">EtherType:</span>
                <span class="text-green-400">{{ currentPacket.ethernet?.etherType }}</span>
              </div>
            </div>
          </div>

          <!-- IP Header -->
          <div class="header-section">
            <h4 class="font-medium text-blue-300 mb-2">IP Header (Layer 3)</h4>
            <div class="bg-gray-800 rounded p-3 font-mono text-sm">
              <div class="grid grid-cols-2 gap-2 text-xs">
                <span class="text-gray-400">Version:</span>
                <span class="text-green-400">{{ currentPacket.ip?.version }}</span>
                <span class="text-gray-400">Protocol:</span>
                <span class="text-green-400">{{ currentPacket.ip?.protocol }}</span>
                <span class="text-gray-400">Src IP:</span>
                <span class="text-green-400">{{ currentPacket.ip?.srcIP }}</span>
                <span class="text-gray-400">Dest IP:</span>
                <span class="text-green-400">{{ currentPacket.ip?.destIP }}</span>
                <span class="text-gray-400">TTL:</span>
                <span class="text-green-400">{{ currentPacket.ip?.ttl }}</span>
                <span class="text-gray-400">Checksum:</span>
                <span class="text-green-400">{{ currentPacket.ip?.checksum }}</span>
              </div>
            </div>
          </div>

          <!-- Transport Header -->
          <div class="header-section" v-if="currentPacket.transport">
            <h4 class="font-medium text-blue-300 mb-2">{{ selectedProtocol }} Header (Layer 4)</h4>
            <div class="bg-gray-800 rounded p-3 font-mono text-sm">
              <div class="grid grid-cols-2 gap-2 text-xs">
                <span class="text-gray-400">Src Port:</span>
                <span class="text-green-400">{{ currentPacket.transport?.srcPort }}</span>
                <span class="text-gray-400">Dest Port:</span>
                <span class="text-green-400">{{ currentPacket.transport?.destPort }}</span>
                <span v-if="selectedProtocol === 'TCP'" class="text-gray-400">Seq Num:</span>
                <span v-if="selectedProtocol === 'TCP'" class="text-green-400">{{ currentPacket.transport?.seqNum }}</span>
                <span v-if="selectedProtocol === 'TCP'" class="text-gray-400">Ack Num:</span>
                <span v-if="selectedProtocol === 'TCP'" class="text-green-400">{{ currentPacket.transport?.ackNum }}</span>
                <span class="text-gray-400">Checksum:</span>
                <span class="text-green-400">{{ currentPacket.transport?.checksum }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Simulation Log -->
      <div class="simulation-log bg-gray-900 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-yellow-400 mb-3">Simulation Log</h3>
        
        <div class="log-container h-64 overflow-y-auto space-y-2">
          <div
            v-for="(log, index) in simulationLogs"
            :key="index"
            :class="[
              'log-entry p-2 rounded text-sm',
              log.type === 'info' ? 'bg-blue-900/30 text-blue-300' : '',
              log.type === 'success' ? 'bg-green-900/30 text-green-300' : '',
              log.type === 'warning' ? 'bg-yellow-900/30 text-yellow-300' : '',
              log.type === 'error' ? 'bg-red-900/30 text-red-300' : ''
            ]"
          >
            <span class="text-gray-400 text-xs">{{ log.timestamp }}</span>
            <div>{{ log.message }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useNetworkStore } from '../store/network'

export default {
  name: 'PacketFlow',
  setup() {
    const networkStore = useNetworkStore()
    
    const selectedProtocol = ref('TCP')
    const isSimulating = ref(false)
    const packetVisible = ref(false)
    const packetX = ref(80)
    const packetY = ref(150)
    const packetTrail = ref([])
    const showTrail = ref(false)
    const simulationLogs = ref([])
    
    const sourceIP = ref('192.168.1.100')
    const destinationIP = ref('192.168.1.200')
    
    const protocolColors = {
      TCP: '#3b82f6',
      UDP: '#10b981',
      ICMP: '#f59e0b',
      HTTP: '#8b5cf6',
      DNS: '#ef4444'
    }

    const currentPacket = computed(() => networkStore.currentPacket)

    const addLog = (message, type = 'info') => {
      const timestamp = new Date().toLocaleTimeString()
      simulationLogs.value.unshift({
        timestamp,
        message,
        type
      })
      
      // Keep only last 50 logs
      if (simulationLogs.value.length > 50) {
        simulationLogs.value = simulationLogs.value.slice(0, 50)
      }
    }

    const updateProtocol = () => {
      addLog(`Protocol changed to ${selectedProtocol.value}`, 'info')
    }

    const simulatePacket = async () => {
      if (isSimulating.value) return
      
      isSimulating.value = true
      packetVisible.value = true
      showTrail.value = true
      packetTrail.value = []
      
      addLog(`Starting ${selectedProtocol.value} packet simulation`, 'info')
      addLog(`Source: ${sourceIP.value} → Destination: ${destinationIP.value}`, 'info')
      
      try {
        // Generate packet data
        const packetData = await networkStore.generatePacket({
          protocol: selectedProtocol.value,
          sourceIP: sourceIP.value,
          destinationIP: destinationIP.value,
          sourcePort: Math.floor(Math.random() * 65535),
          destinationPort: selectedProtocol.value === 'HTTP' ? 80 : Math.floor(Math.random() * 65535)
        })
        
        addLog('Packet generated successfully', 'success')
        
        // Animate packet movement
        await animatePacketFlow()
        
        addLog('Packet delivered successfully', 'success')
        
      } catch (error) {
        addLog(`Simulation error: ${error.message}`, 'error')
      } finally {
        isSimulating.value = false
        setTimeout(() => {
          packetVisible.value = false
          showTrail.value = false
        }, 1000)
      }
    }

    const animatePacketFlow = () => {
      return new Promise((resolve) => {
        const startX = 80
        const routerX = 400
        const endX = 720
        const y = 150
        
        let currentX = startX
        const speed = 3
        
        const animate = () => {
          // Add to trail
          packetTrail.value.push({ x: currentX, y })
          if (packetTrail.value.length > 20) {
            packetTrail.value.shift()
          }
          
          if (currentX < routerX) {
            // Moving to router
            currentX += speed
            packetX.value = currentX
            
            if (currentX >= routerX - 10) {
              addLog('Packet reached router - processing...', 'warning')
            }
          } else if (currentX < endX) {
            // Moving to destination
            currentX += speed
            packetX.value = currentX
            
            if (currentX >= endX - 10) {
              addLog('Packet reached destination', 'success')
            }
          } else {
            // Animation complete
            resolve()
            return
          }
          
          requestAnimationFrame(animate)
        }
        
        animate()
      })
    }

    const clearSimulation = () => {
      simulationLogs.value = []
      packetVisible.value = false
      showTrail.value = false
      packetTrail.value = []
      packetX.value = 80
      addLog('Simulation cleared', 'info')
    }

    onMounted(() => {
      addLog('Packet Flow Simulator initialized', 'success')
    })

    return {
      selectedProtocol,
      isSimulating,
      packetVisible,
      packetX,
      packetY,
      packetTrail,
      showTrail,
      simulationLogs,
      sourceIP,
      destinationIP,
      protocolColors,
      currentPacket,
      updateProtocol,
      simulatePacket,
      clearSimulation
    }
  }
}
</script>

<style scoped>
.packet-animation {
  transition: transform 0.1s linear;
}

.log-container {
  scrollbar-width: thin;
  scrollbar-color: #4b5563 #1f2937;
}

.log-container::-webkit-scrollbar {
  width: 6px;
}

.log-container::-webkit-scrollbar-track {
  background: #1f2937;
}

.log-container::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 3px;
}

.header-section {
  border-left: 3px solid #3b82f6;
  padding-left: 12px;
}
</style>
