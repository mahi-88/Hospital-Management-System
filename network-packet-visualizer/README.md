# 🌐 Network Packet Visualizer - OSI Layer Simulation & Packet Flow Tracker

A comprehensive web-based tool that simulates and visualizes how data packets move through a network, breaking them down by OSI layers, headers, checksums, and protocol behaviors. Built for educational purposes and network analysis.

![Network Visualizer](https://img.shields.io/badge/Status-Active-brightgreen) ![Vue.js](https://img.shields.io/badge/Vue.js-3.x-green) ![C%23](https://img.shields.io/badge/C%23-.NET-blue) ![SQL%20Server](https://img.shields.io/badge/Database-SQL%20Server-red) ![Networking](https://img.shields.io/badge/Domain-Networking-orange)

## 🌟 Live Demo

[View Network Packet Visualizer](https://mahi-88.github.io/network-packet-visualizer) *(Coming Soon)*

## 🧠 Project Goal

To build an interactive, educational, and developer-friendly tool that simulates and visualizes how data packets move through a network, providing deep insights into OSI layer operations, protocol behaviors, and network communication patterns.

## ✨ Features

### 🔄 OSI Layer Visualization
- **7-Layer Simulation**: Complete OSI model visualization (Physical to Application)
- **Layer-Specific Details**: 
  - **L1 (Physical)**: Signal transmission and encoding
  - **L2 (Data Link)**: MAC addresses, frame headers, error detection
  - **L3 (Network)**: IP headers, routing, fragmentation
  - **L4 (Transport)**: TCP/UDP ports, flags, flow control
  - **L5 (Session)**: Session management and establishment
  - **L6 (Presentation)**: Data encryption, compression, formatting
  - **L7 (Application)**: HTTP, DNS, FTP protocol details

### 📦 Advanced Packet Simulation
- **Protocol Support**: ICMP, TCP, UDP, HTTP, DNS, ARP, DHCP
- **Visual Flow**: Animated source → destination packet journey
- **Header Analysis**: Detailed field inspection with tooltips
- **Checksum Validation**: Real-time checksum calculation and verification
- **Flag Interpretation**: TCP flags, IP flags, and protocol-specific options

### 🎛️ Real-Time Frontend Dashboard (Vue.js)
- **Interactive Components**: Click-to-inspect packet headers
- **Dynamic Visualization**: Live packet flow animations
- **Connection Tracking**: Visualize handshakes, retransmissions, timeouts
- **Protocol Statistics**: Real-time metrics and performance data
- **Responsive Design**: Works on desktop, tablet, and mobile

### ⚙️ Backend Engine (C# .NET)
- **Packet Generation**: Simulate realistic network packets
- **Field Calculation**: Automatic checksum, length, and header computation
- **Protocol Stack**: Complete implementation of major protocols
- **PCAP Integration**: Parse and analyze real network captures
- **Performance Optimization**: Efficient packet processing algorithms

### 🗄️ SQL Server Integration
- **Packet Storage**: Store simulated packet data with timestamps
- **Query Engine**: Advanced packet flow analysis and filtering
- **Error Logging**: Track simulation errors and network anomalies
- **Historical Data**: Maintain packet history for trend analysis
- **Performance Metrics**: Database optimization for high-volume data

### 📊 Comprehensive Metrics Dashboard
- **Traffic Analytics**: Total packets, protocols, error rates
- **Performance Monitoring**: Latency, throughput, packet loss
- **Protocol Distribution**: Visual breakdown of network traffic
- **Error Analysis**: Dropped packets, retransmissions, timeouts
- **Real-time Alerts**: Network anomaly detection and notifications

### 🔄 CI/CD Integration
- **GitHub Actions**: Automated testing and deployment pipeline
- **Unit Testing**: Comprehensive test coverage for packet integrity
- **Integration Tests**: End-to-end simulation validation
- **Code Quality**: Automated linting, security scanning
- **Deployment**: Automated staging and production deployments

### 📈 ELK Stack Integration (Bonus)
- **Elasticsearch**: Advanced packet data indexing and search
- **Logstash**: Real-time log processing and transformation
- **Kibana**: Interactive dashboards and network visualization
- **Alerting**: Custom alerts for network patterns and anomalies

## 🛠️ Technology Stack

### Frontend
- **Vue.js 3**: Modern reactive framework with Composition API
- **TypeScript**: Type-safe development
- **Tailwind CSS**: Utility-first styling
- **D3.js**: Advanced data visualization
- **Chart.js**: Interactive charts and graphs
- **Socket.io**: Real-time communication

### Backend
- **C# .NET 6**: High-performance web API
- **ASP.NET Core**: RESTful API framework
- **Entity Framework**: ORM for database operations
- **SignalR**: Real-time web functionality
- **AutoMapper**: Object-to-object mapping
- **Serilog**: Structured logging

### Database
- **SQL Server**: Primary data storage
- **Redis**: Caching and session management
- **Entity Framework Core**: Database migrations and queries

### DevOps & Tools
- **Docker**: Containerization
- **GitHub Actions**: CI/CD pipeline
- **Postman**: API testing and documentation
- **SonarQube**: Code quality analysis
- **NGINX**: Reverse proxy and load balancing

### Optional Tools
- **PyShark**: PCAP file analysis
- **Scapy**: Packet manipulation and analysis
- **Wireshark**: Network protocol analyzer
- **ELK Stack**: Elasticsearch, Logstash, Kibana

## 📁 Project Structure

```
network-packet-visualizer/
├── frontend/                    # Vue.js application
│   ├── src/
│   │   ├── components/         # Vue components
│   │   │   ├── OSILayer.vue    # OSI layer visualization
│   │   │   ├── PacketFlow.vue  # Packet animation
│   │   │   ├── Dashboard.vue   # Metrics dashboard
│   │   │   └── Inspector.vue   # Packet header inspector
│   │   ├── views/              # Application pages
│   │   ├── services/           # API service calls
│   │   ├── store/              # Vuex state management
│   │   └── utils/              # Utility functions
│   ├── public/                 # Static assets
│   └── package.json            # Frontend dependencies
├── backend/                    # C# .NET API
│   ├── Controllers/            # API controllers
│   ├── Services/               # Business logic
│   │   ├── PacketService.cs    # Packet simulation
│   │   ├── OSIService.cs       # OSI layer processing
│   │   └── AnalyticsService.cs # Metrics calculation
│   ├── Models/                 # Data models
│   ├── Data/                   # Database context
│   ├── Hubs/                   # SignalR hubs
│   └── Program.cs              # Application entry point
├── database/                   # Database scripts
│   ├── schema.sql              # Database schema
│   ├── seed-data.sql           # Sample data
│   └── migrations/             # EF migrations
├── .github/workflows/          # CI/CD pipelines
│   ├── build.yml               # Build and test
│   ├── deploy.yml              # Deployment
│   └── security.yml            # Security scanning
├── docs/                       # Documentation
│   ├── OSI-Guide.md            # OSI layer explanations
│   ├── API-Documentation.md    # API reference
│   ├── screenshots/            # UI screenshots
│   └── demo.gif                # Packet flow animation
├── tests/                      # Test suites
│   ├── unit/                   # Unit tests
│   ├── integration/            # Integration tests
│   └── e2e/                    # End-to-end tests
├── docker-compose.yml          # Container orchestration
├── .gitignore                  # Git ignore rules
├── LICENSE                     # MIT License
└── README.md                   # Project documentation
```

## 🚀 Quick Start

### Prerequisites
- Node.js (v16 or higher)
- .NET 6 SDK
- SQL Server (local or cloud)
- Docker (optional)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/mahi-88/network-packet-visualizer.git
cd network-packet-visualizer
```

2. **Setup Backend**
```bash
cd backend
dotnet restore
dotnet ef database update
dotnet run
```

3. **Setup Frontend**
```bash
cd ../frontend
npm install
npm run dev
```

4. **Setup Database**
```bash
# Run SQL scripts in database/ folder
sqlcmd -S localhost -d NetworkVisualizer -i database/schema.sql
```

5. **Access Application**
- Frontend: http://localhost:3000
- Backend API: http://localhost:5000
- API Documentation: http://localhost:5000/swagger

## 🎯 Key Features for Recruiters

### Technical Skills Demonstrated
- **Network Programming**: Deep understanding of OSI model and protocols
- **Full-Stack Development**: Vue.js frontend with C# .NET backend
- **Database Design**: SQL Server optimization and query performance
- **Real-time Systems**: SignalR for live packet visualization
- **API Development**: RESTful services with comprehensive documentation
- **DevOps**: CI/CD pipelines, containerization, automated testing
- **Data Visualization**: Interactive charts and network diagrams

### Advanced Concepts
- **Network Protocol Analysis**: TCP/IP, UDP, HTTP, DNS implementation
- **Packet Processing**: Header parsing, checksum validation
- **Performance Optimization**: Efficient algorithms for high-volume data
- **Security**: Network security concepts and vulnerability analysis
- **System Architecture**: Scalable, maintainable application design

## 🧪 Testing

### Backend Tests
```bash
cd backend
dotnet test
```

### Frontend Tests
```bash
cd frontend
npm run test
```

### Integration Tests
```bash
docker-compose -f docker-compose.test.yml up
```

## 👥 Author

**Mahi** - Network Engineer & Full Stack Developer
- GitHub: [@mahi-88](https://github.com/mahi-88)
- Email: settipallimahi888@gmail.com

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

**⭐ Star this repository if you found it helpful!**

**🔗 [Live Demo](https://mahi-88.github.io/network-packet-visualizer) | [Report Bug](https://github.com/mahi-88/network-packet-visualizer/issues) | [Request Feature](https://github.com/mahi-88/network-packet-visualizer/issues)**
