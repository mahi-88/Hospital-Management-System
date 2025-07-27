<template>
  <div class="osi-layer-container bg-gray-800 rounded-lg p-6">
    <h2 class="text-2xl font-bold text-blue-400 mb-6">OSI Layer Visualization</h2>
    
    <!-- OSI Layers Stack -->
    <div class="osi-stack space-y-2">
      <div
        v-for="(layer, index) in osiLayers"
        :key="layer.number"
        :class="[
          'layer-card p-4 rounded-lg border-2 transition-all duration-300 cursor-pointer',
          selectedLayer === layer.number ? 'border-blue-500 bg-blue-900/30' : 'border-gray-600 bg-gray-700',
          activeLayer === layer.number ? 'animate-pulse border-green-500' : ''
        ]"
        @click="selectLayer(layer.number)"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div class="layer-number bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center font-bold">
              {{ layer.number }}
            </div>
            <div>
              <h3 class="font-semibold text-lg">{{ layer.name }}</h3>
              <p class="text-gray-400 text-sm">{{ layer.description }}</p>
            </div>
          </div>
          
          <!-- Layer Status Indicator -->
          <div class="flex items-center space-x-2">
            <div
              v-if="activeLayer === layer.number"
              class="w-3 h-3 bg-green-500 rounded-full animate-pulse"
            ></div>
            <ChevronRightIcon 
              v-if="selectedLayer === layer.number"
              class="w-5 h-5 text-blue-400 transform rotate-90 transition-transform"
            />
            <ChevronRightIcon 
              v-else
              class="w-5 h-5 text-gray-400 transition-transform"
            />
          </div>
        </div>
        
        <!-- Expanded Layer Details -->
        <div v-if="selectedLayer === layer.number" class="mt-4 pt-4 border-t border-gray-600">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h4 class="font-semibold text-blue-300 mb-2">Functions:</h4>
              <ul class="text-sm text-gray-300 space-y-1">
                <li v-for="func in layer.functions" :key="func" class="flex items-center">
                  <CheckIcon class="w-4 h-4 text-green-500 mr-2" />
                  {{ func }}
                </li>
              </ul>
            </div>
            
            <div>
              <h4 class="font-semibold text-blue-300 mb-2">Protocols:</h4>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="protocol in layer.protocols"
                  :key="protocol"
                  class="px-2 py-1 bg-gray-600 rounded text-xs font-medium"
                >
                  {{ protocol }}
                </span>
              </div>
            </div>
          </div>
          
          <!-- Current Packet Data -->
          <div v-if="currentPacket && currentPacket.layers[layer.number]" class="mt-4">
            <h4 class="font-semibold text-green-300 mb-2">Current Packet Data:</h4>
            <div class="bg-gray-900 rounded p-3 font-mono text-sm">
              <pre class="text-green-400">{{ formatPacketData(currentPacket.layers[layer.number]) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Packet Flow Animation -->
    <div class="mt-6">
      <h3 class="text-lg font-semibold text-blue-300 mb-3">Packet Flow</h3>
      <div class="packet-flow-container relative h-20 bg-gray-900 rounded-lg overflow-hidden">
        <div
          v-if="packetFlowing"
          class="packet-animation absolute top-1/2 transform -translate-y-1/2 w-4 h-4 bg-blue-500 rounded-full"
          :style="{ left: packetPosition + '%' }"
        ></div>
        
        <!-- Flow Direction Indicators -->
        <div class="absolute inset-0 flex items-center justify-between px-4">
          <span class="text-xs text-gray-500">Source</span>
          <ArrowRightIcon class="w-6 h-6 text-gray-600" />
          <span class="text-xs text-gray-500">Destination</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { ChevronRightIcon, CheckIcon, ArrowRightIcon } from '@heroicons/vue/24/outline'
import { useNetworkStore } from '../store/network'

export default {
  name: 'OSILayer',
  components: {
    ChevronRightIcon,
    CheckIcon,
    ArrowRightIcon
  },
  setup() {
    const networkStore = useNetworkStore()
    const selectedLayer = ref(7)
    const activeLayer = ref(null)
    const packetFlowing = ref(false)
    const packetPosition = ref(0)
    
    const osiLayers = ref([
      {
        number: 7,
        name: 'Application Layer',
        description: 'Network services to applications',
        functions: ['HTTP/HTTPS', 'FTP', 'SMTP', 'DNS', 'DHCP'],
        protocols: ['HTTP', 'HTTPS', 'FTP', 'SMTP', 'DNS', 'DHCP', 'SSH']
      },
      {
        number: 6,
        name: 'Presentation Layer',
        description: 'Data encryption, compression, translation',
        functions: ['Encryption/Decryption', 'Compression', 'Data Translation'],
        protocols: ['SSL/TLS', 'JPEG', 'GIF', 'MPEG']
      },
      {
        number: 5,
        name: 'Session Layer',
        description: 'Session management and control',
        functions: ['Session Establishment', 'Session Maintenance', 'Session Termination'],
        protocols: ['NetBIOS', 'RPC', 'SQL']
      },
      {
        number: 4,
        name: 'Transport Layer',
        description: 'Reliable data transfer, flow control',
        functions: ['Segmentation', 'Flow Control', 'Error Recovery', 'Port Addressing'],
        protocols: ['TCP', 'UDP', 'SCTP']
      },
      {
        number: 3,
        name: 'Network Layer',
        description: 'Routing and logical addressing',
        functions: ['Routing', 'Logical Addressing', 'Path Determination'],
        protocols: ['IP', 'ICMP', 'ARP', 'OSPF', 'BGP']
      },
      {
        number: 2,
        name: 'Data Link Layer',
        description: 'Frame formatting, error detection',
        functions: ['Framing', 'Physical Addressing', 'Error Detection', 'Flow Control'],
        protocols: ['Ethernet', 'Wi-Fi', 'PPP', 'Frame Relay']
      },
      {
        number: 1,
        name: 'Physical Layer',
        description: 'Physical transmission of raw bits',
        functions: ['Bit Transmission', 'Physical Topology', 'Signal Encoding'],
        protocols: ['Ethernet Cable', 'Fiber Optic', 'Radio Waves']
      }
    ])

    const currentPacket = computed(() => networkStore.currentPacket)

    const selectLayer = (layerNumber) => {
      selectedLayer.value = layerNumber
      networkStore.selectOSILayer(layerNumber)
    }

    const formatPacketData = (layerData) => {
      return JSON.stringify(layerData, null, 2)
    }

    const animatePacketFlow = () => {
      packetFlowing.value = true
      packetPosition.value = 0
      
      const animation = setInterval(() => {
        packetPosition.value += 2
        
        // Simulate layer processing
        const currentLayerIndex = Math.floor(packetPosition.value / 14.28) // 100/7 layers
        if (currentLayerIndex < 7) {
          activeLayer.value = 7 - currentLayerIndex
        }
        
        if (packetPosition.value >= 100) {
          clearInterval(animation)
          packetFlowing.value = false
          activeLayer.value = null
          packetPosition.value = 0
        }
      }, 100)
    }

    onMounted(() => {
      // Listen for packet simulation events
      networkStore.onPacketSimulated(() => {
        animatePacketFlow()
      })
    })

    return {
      selectedLayer,
      activeLayer,
      packetFlowing,
      packetPosition,
      osiLayers,
      currentPacket,
      selectLayer,
      formatPacketData
    }
  }
}
</script>

<style scoped>
.osi-stack {
  max-height: 600px;
  overflow-y: auto;
}

.layer-card {
  transition: all 0.3s ease;
}

.layer-card:hover {
  transform: translateX(4px);
}

.packet-animation {
  transition: left 0.1s linear;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}
</style>
