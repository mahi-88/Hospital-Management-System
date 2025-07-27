# 🌐 Network Packet Visualizer - OSI Layer Simulation & Packet Flow Tracker

> **Educational Network Visualization Tool** - Built to help students and professionals understand how data flows through the OSI model in real time, with realistic packet headers and live network simulations. Perfect for learning networking concepts, protocol analysis, and system architecture.

A comprehensive web-based tool that simulates and visualizes how data packets move through a network, breaking them down by OSI layers, headers, checksums, and protocol behaviors.

## 📊 Project Status & Metrics

![Build Status](https://img.shields.io/badge/Build-Passing-brightgreen)
![License](https://img.shields.io/badge/License-MIT-blue)
![Version](https://img.shields.io/badge/Version-1.0.0-orange)
![GitHub Stars](https://img.shields.io/github/stars/mahi-88/network-packet-visualizer?style=social)
![GitHub Forks](https://img.shields.io/github/forks/mahi-88/network-packet-visualizer?style=social)

### Technology Stack
![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![.NET](https://img.shields.io/badge/.NET-6.0-512BD4?style=for-the-badge&logo=dotnet&logoColor=white)
![SQL Server](https://img.shields.io/badge/SQL%20Server-2019-CC2927?style=for-the-badge&logo=microsoft-sql-server&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=for-the-badge&logo=typescript&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)

## 🌟 Live Demo & Links

🔗 **[Live Application](https://network-visualizer-demo.azurewebsites.net)** *(Coming Soon)*  
📖 **[API Documentation](https://network-visualizer-demo.azurewebsites.net/api/docs)**  
🎥 **[Demo Video](https://youtu.be/demo-link)** *(2-minute walkthrough)*  
📱 **[Mobile Demo](https://network-visualizer-demo.azurewebsites.net/mobile)**

## 📸 Visual Previews

### OSI Layer Visualization
![OSI Layer Demo](docs/screenshots/osi-layers-demo.gif)
*Interactive 7-layer OSI model with real-time packet processing*

### Packet Flow Animation
![Packet Flow Demo](docs/screenshots/packet-flow-demo.gif)
*Real-time packet animation from source to destination*

### Network Topology
![Network Topology](docs/screenshots/network-topology.png)
*Interactive network diagram with device visualization*

### Metrics Dashboard
![Dashboard](docs/screenshots/metrics-dashboard.png)
*Comprehensive analytics and performance monitoring*

## 🎯 Project Motivation

This project addresses the challenge of understanding complex networking concepts by providing:

- **Visual Learning**: Transform abstract OSI layer concepts into interactive visualizations
- **Real-Time Simulation**: See how packets actually move through network infrastructure
- **Educational Tool**: Perfect for students, educators, and professionals learning networking
- **Practical Application**: Understand protocol behaviors and network troubleshooting

## ✨ Features Implemented

### 🔄 OSI Layer Visualization
- **Complete 7-layer OSI model simulation** with interactive processing
- **Real-time packet flow** through each layer with detailed breakdowns
- **Protocol-specific analysis** for TCP, UDP, ICMP, HTTP, DNS
- **Header inspection** with field-by-field examination

### 📦 Advanced Packet Simulation
- **Multi-protocol support**: TCP, UDP, ICMP, HTTP, DNS packet generation
- **Realistic packet structure** with proper headers and checksums
- **Network topology visualization** with animated packet flow
- **Checksum validation** and integrity verification

### 🎛️ Real-Time Dashboard (Vue.js)
- **Interactive packet flow animations** with SVG-based network topology
- **Live metrics dashboard** with real-time statistics
- **Click-to-inspect headers** with detailed packet analysis
- **Connection tracking** and performance monitoring

### ⚙️ Backend Engine (C# .NET)
- **High-performance packet simulation** with realistic data generation
- **SQL Server integration** for persistent data storage
- **SignalR real-time communication** for live updates
- **RESTful API** with comprehensive Swagger documentation

### 📊 Analytics & Monitoring
- **Network traffic statistics** with protocol distribution
- **Performance metrics tracking** (latency, throughput, packet loss)
- **Error logging and analysis** with detailed diagnostics
- **Historical data analysis** with trend visualization

## 🛠️ Technology Stack

### Frontend
- **Vue.js 3** with Composition API and TypeScript support
- **Tailwind CSS** for modern, responsive styling
- **D3.js** for advanced data visualization
- **Chart.js** for interactive charts and graphs
- **SignalR Client** for real-time communication

### Backend
- **C# .NET 6** with ASP.NET Core Web API
- **Entity Framework Core** for database operations
- **SignalR** for real-time bidirectional communication
- **AutoMapper** for object-to-object mapping
- **Serilog** for structured logging

### Database
- **SQL Server** with optimized schemas and indexes
- **Entity Framework migrations** for version control
- **Stored procedures** for complex queries
- **Performance optimization** with proper indexing

### DevOps & Tools
- **Docker** containerization for easy deployment
- **GitHub Actions** for CI/CD automation
- **SonarCloud** for code quality analysis
- **Swagger/OpenAPI** for API documentation

## 📁 Project Structure

```
network-packet-visualizer/
├── frontend/                    # Vue.js application
│   ├── src/
│   │   ├── components/         # Vue components
│   │   │   ├── OSILayer.vue    # OSI layer visualization
│   │   │   ├── PacketFlow.vue  # Packet animation
│   │   │   └── Dashboard.vue   # Metrics dashboard
│   │   ├── views/              # Application pages
│   │   ├── services/           # API services
│   │   └── store/              # State management
├── backend/                    # C# .NET API
│   ├── Controllers/            # API controllers
│   ├── Services/               # Business logic
│   ├── Models/                 # Data models
│   ├── Data/                   # Database context
│   └── Hubs/                   # SignalR hubs
├── database/                   # Database scripts
│   └── schema.sql              # Database schema
├── .github/workflows/          # CI/CD pipelines
└── docs/                       # Documentation & screenshots
    ├── screenshots/            # UI screenshots
    ├── demo-videos/           # Demo recordings
    └── setup-guides/          # Installation guides
```

## 🚀 Quick Start

### Prerequisites
- Node.js (v18 or higher)
- .NET 6 SDK
- SQL Server (local or cloud)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/mahi-88/network-packet-visualizer.git
cd network-packet-visualizer
```

2. **Setup Database**
```bash
sqlcmd -S localhost -d master -i database/schema.sql
```

3. **Configure Backend**
```bash
cd backend
# Update appsettings.json with your SQL Server connection string
dotnet restore
dotnet run
```

4. **Setup Frontend**
```bash
cd frontend
npm install
npm run dev
```

5. **Access Application**
- Frontend: http://localhost:3000
- Backend API: http://localhost:5000
- API Documentation: http://localhost:5000/api/docs

## 🎯 Key Achievements

### Technical Excellence
✅ **Advanced Networking Knowledge**: Complete OSI model implementation  
✅ **Full-Stack Architecture**: Vue.js + C# .NET with real-time features  
✅ **Database Design**: Optimized SQL Server schemas with proper indexing  
✅ **Real-Time Communication**: SignalR for live packet visualization  
✅ **Professional API**: RESTful design with comprehensive documentation  

### Software Engineering
✅ **CI/CD Pipeline**: Automated testing, security scanning, deployment  
✅ **Code Quality**: SonarCloud integration with quality gates  
✅ **Testing Coverage**: Unit tests, integration tests, E2E testing  
✅ **Security**: Vulnerability scanning and secure coding practices  
✅ **Documentation**: Complete setup guides and API documentation  

## 🎓 Educational Value

Perfect for:
- **Computer Science Students** learning networking fundamentals
- **Network Engineers** understanding protocol behaviors
- **Software Developers** building network-aware applications
- **Educators** teaching OSI model and networking concepts
- **IT Professionals** troubleshooting network issues

## 🔗 Additional Resources

- **[Setup Guide](docs/setup-guides/INSTALLATION.md)** - Detailed installation instructions
- **[API Reference](docs/API_REFERENCE.md)** - Complete API documentation
- **[Architecture Guide](docs/ARCHITECTURE.md)** - System design and architecture
- **[Contributing](CONTRIBUTING.md)** - How to contribute to the project
- **[Changelog](CHANGELOG.md)** - Version history and updates

## 👥 Author

**Mahi** - Network Engineer & Full Stack Developer
- GitHub: [@mahi-88](https://github.com/mahi-88)
- Email: settipallimahi888@gmail.com
- LinkedIn: [Connect with me](https://linkedin.com/in/mahi-88)
- Portfolio: [View my projects](https://mahi-88.github.io)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Network protocol specifications and RFCs
- Open source community contributions
- Educational resources on network engineering
- Modern web development best practices

## ⭐ Support

If you find this project helpful, please consider:
- ⭐ **Starring** the repository
- 🍴 **Forking** for your own experiments
- 🐛 **Reporting issues** or suggesting improvements
- 📢 **Sharing** with others who might benefit

---

**🔗 [View Repository](https://github.com/mahi-88/network-packet-visualizer) | [Report Issues](https://github.com/mahi-88/network-packet-visualizer/issues) | [Request Features](https://github.com/mahi-88/network-packet-visualizer/issues)**

**⭐ This project showcases advanced networking knowledge, full-stack development expertise, and professional software engineering practices. Perfect for demonstrating technical skills to recruiters and potential employers.**
