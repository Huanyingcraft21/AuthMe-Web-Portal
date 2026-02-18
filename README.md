<div id="cn"></div>

# 🌠 流星AWP (Meteor AWP) - v1.7.5

[English](#en) | [中文说明](#cn)

**新一代 Minecraft AuthMe 网页端用户中心** *支持多服务器群组 | RCON 奖励系统 | 极简与美学的完美融合*

![Version](https://img.shields.io/badge/Version-1.7.5-blue.svg) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg) ![License](https://img.shields.io/badge/License-MIT-green.svg)

流星AWP 是一个专为 Minecraft 服务器打造的现代化网页门户。它不仅支持 AuthMe 注册，更是一个完整的玩家生态中心。v1.7.5 版本带来了全新的**多服务器架构支持**，完美适配 BungeeCord / Velocity 群组服环境。

---

## ✨ 核心特性

### 🌍 多服务器架构 (New)
* **群组服支持**：后台可配置无限个子服务器（如生存服、空岛服、登录服）。
* **独立状态显示**：支持配置**公开展示 IP** (Proxy/IP) 与 **后端 RCON IP** 分离，完美解决内网/Docker 部署的状态查询问题。
* **RCON 控制台**：后台内置 Web RCON 终端，可切换服务器发送指令。

### 🎁 奖励与生态系统
* **每日签到**：支持跨服奖励同步。玩家签到一次，后台配置的多个服务器可同时发放奖励。
* **CDK 兑换中心**：生成礼包码，支持指定“全服通用”或“仅限特定服务器”使用。
* **注册奖励**：新用户注册成功，自动下发新手礼包。
* **邮件通知**：注册成功欢迎邮件 & 管理员新用户通知。

### 🎨 极致 UI/UX
* **Glassmorphism**：全站采用现代化毛玻璃拟态设计。
* **响应式布局**：完美适配手机、平板、PC 端。
* **自定义背景**：后台一键修改全站背景图。

### 🚀 双版本发行
* **Standard 标准版**：逻辑分离，带完整后台管理，支持 **GitHub OTA 一键自动更新**。
* **Lite 单文件版**：极致压缩，单文件集成了注册、登录、签到、CDK、多服支持等所有核心功能。

---

## 📦 快速部署 (推荐)

我们引入了全新的 **云端安装程序**，您无需手动上传大量文件。

1.  下载仓库中的 `install.php` 文件。
2.  将其上传到您的网站根目录。
3.  访问 `http://您的域名/install.php`。
4.  **选择版本**：
    * **标准版 (推荐)**：自动下载后台、核心库、前台，适合正式运营。
    * **Lite版**：自动部署为单文件模式，适合极简主义者。
5.  填写数据库信息，安装程序将自动完成环境配置。

---

## ⚙️ 配置说明 (config.php)

v1.7.5 采用了全新的配置结构，以下是关键配置详解：

```php
return [
    // 1. 站点基础
    'site' => [
        'title' => '流星群组服',
        'ver'   => '1.7.5',
        'bg'    => '[https://example.com/bg.jpg](https://example.com/bg.jpg)', // 自定义背景图
    ],

    // 2. [关键] 前端状态显示 (代理端/公开地址)
    // 这是前台页面顶部显示的“在线人数”和“MOTD”查询地址
    // 如果您是群组服，请填写 BC/Velocity 的公开 IP
    'display' => [
        'ip'   => 'play.example.com',
        'port' => '25565'
    ],

    // 3. [关键] 后端服务器列表 (RCON 连接用)
    // 用于发送奖励指令。数组下标 0, 1, 2 代表服务器 ID
    'servers' => [
        // ID: 0 - 通常设为大厅或主生存服
        [
            'name'      => '生存一区',
            'ip'        => '127.0.0.1', // 后端内网IP
            'port'      => '25565',     // 游戏端口
            'rcon_port' => '25575',     // RCON端口
            'rcon_pass' => 'password'   // RCON密码
        ],
        // ID: 1
        [
            'name'      => '空岛二区',
            'ip'        => '127.0.0.1',
            'port'      => '25566',
            'rcon_port' => '25576',
            'rcon_pass' => 'password'
        ]
    ],

    // 4. 奖励策略
    'rewards' => [
        'reg_cmd'         => 'mg give %player% starter_kit 1', // 注册奖励 (默认发往 ID:0 服务器)
        'daily_cmd'       => 'mg give %player% point 10',      // 签到奖励指令
        'sign_in_servers' => [0, 1]                            // 签到奖励发往哪些服务器？(填写ID)
    ],

    // ... 数据库与 SMTP 配置 ...
];

```

### 🛠️ 后台管理指南 (Standard版)

访问 `/admin.php` 进入后台：

1. **检查更新**：点击左侧侧边栏的 **【检查更新】**，如有新版本，系统将自动从 GitHub 拉取代码并智能合并配置，无损升级。
2. **RCON 终端**：在“网页控制台”页面，您可以选择任意一个已配置的服务器，直接发送后台指令。
3. **CDK 管理**：生成 CDK 时，您可以指定该 CDK 是 **“全服通用”** 还是 **“仅限特定服务器”** 使用。

---

### ⚡ 关于 Lite 版 (Extreme Edition)

v1.7.5 的 Lite 版是一个技术奇迹。

* **体积**：单文件，代码经过极致压缩。
* **功能**：完全保留了标准版的所有前台功能（多服签到、选服CDK、状态显示、Glassmorphism UI）。
* **使用**：它依赖 `install.php` 生成的 `config.php`。但后台入口改成了?a=admin

---

### 📝 常见问题

**Q: 为什么前台状态条不显示？**
A: 请检查后台设置中的 **“公开展示地址”**。前端必须能通过公网 API 访问到该 IP。如果您的服务器开启了防火墙，请确保 API 接口未被拦截。

**Q: 签到奖励没有到账？**
A: 1. 请确保后台 **RCON 密码** 正确。 2. 请确保在“奖励配置”中填写了 `sign_in_servers` (如 `0` 或 `0,1`)，否则系统不知道该往哪个服发奖。

**Q: 如何配合 MetorGive 插件？**
A: 本程序专为 MetorGive 优化，利用其离线发奖功能。请在服务器安装插件，并在后台将指令设置为 `/mg give %player% <物品> <数量>`。

---

### 📄 开源协议

本项目遵循 **MIT License** 开源协议。您可以自由修改、分发或用于商业用途。

<div id="en"></div>

# 🌠 Meteor AWP - v1.7.5

[English](#en) | [中文说明](#cn)

**Next-Gen Minecraft AuthMe Web Portal** *Multi-Server Support | RCON Reward System | Aesthetic Glassmorphism UI*

![Version](https://img.shields.io/badge/Version-1.7.5-blue.svg) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg) ![License](https://img.shields.io/badge/License-MIT-green.svg)

Meteor AWP is a modern web portal designed specifically for Minecraft servers. It goes beyond simple AuthMe registration to become a complete player ecosystem center. **v1.7.5** introduces a brand new **Multi-Server Architecture**, making it perfectly compatible with BungeeCord / Velocity networks.

---

## ✨ Core Features

### 🌍 Multi-Server Architecture (New)
* **Network Support**: Configure unlimited sub-servers (e.g., Survival, Skyblock, Lobby) in the admin panel.
* **Independent Status Display**: Separate the **Public Display IP** (Proxy/IP) from the **Backend RCON IP**. Perfect for internal networks or Docker environments.
* **Web RCON Console**: Built-in web terminal to switch between servers and send commands directly.

### 🎁 Rewards & Ecosystem
* **Daily Sign-in**: Supports cross-server reward synchronization. Players sign in once, and the system executes commands on multiple configured servers simultaneously.
* **CDK Redemption Center**: Generate gift codes (CDK). Supports "Global" codes or "Server-Specific" codes.
* **Registration Rewards**: Automatically issue starter kits to new users upon registration.
* **Notifications**: Welcome emails for registration & New User alerts for admins.

### 🎨 Ultimate UI/UX
* **Glassmorphism**: A modern, frosted-glass design language.
* **Responsive**: Perfectly adapted for Mobile, Tablet, and PC.
* **Customization**: Change the global background image with one click in the admin panel.

### 🚀 Dual Editions
* **Standard Edition**: Logic separation (Core/View/Admin), complete Admin Panel, supports **One-Click OTA Updates via GitHub**.
* **Lite Edition**: Extreme compression. A single file integrating Registration, Login, Sign-in, CDK, and Multi-server support.

---

## 📦 Quick Deployment (Recommended)

We utilize a **Cloud Installer**, so you don't need to upload dozens of files manually.

1.  Download the `install.php` file from the repository.
2.  Upload it to your web root directory.
3.  Visit `http://your-domain.com/install.php`.
4.  **Select Edition**:
    * **Standard (Recommended)**: Downloads Admin Panel, Core, and Frontend. Best for production.
    * **Lite**: Deploys as a single-file mode. Best for minimalists.
5.  Fill in your database information, and the installer will handle the environment configuration automatically.

---

## ⚙️ Configuration (config.php)

v1.7.5 uses a new configuration structure. Here are the key details:

```php
return [
    // 1. Site Basics
    'site' => [
        'title' => 'Meteor Network',
        'ver'   => '1.7.5',
        'bg'    => '[https://example.com/bg.jpg](https://example.com/bg.jpg)', // Custom background URL
    ],

    // 2. [Critical] Frontend Status Display (Proxy/Public Address)
    // This controls the "Online Players" and "MOTD" shown at the top of the frontend.
    // If you run a Network, enter your BC/Velocity Public IP here.
    'display' => [
        'ip'   => 'play.example.com',
        'port' => '25565'
    ],

    // 3. [Critical] Backend Server List (For RCON Connection)
    // Used for sending reward commands. 0, 1, 2 represents the Server ID.
    'servers' => [
        // ID: 0 - Usually the Lobby or Main Survival Server
        [
            'name'      => 'Survival #1',
            'ip'        => '127.0.0.1', // Backend Internal IP
            'port'      => '25565',     // Game Port
            'rcon_port' => '25575',     // RCON Port
            'rcon_pass' => 'password'   // RCON Password
        ],
        // ID: 1
        [
            'name'      => 'Skyblock #2',
            'ip'        => '127.0.0.1',
            'port'      => '25566',
            'rcon_port' => '25576',
            'rcon_pass' => 'password'
        ]
    ],

    // 4. Reward Strategy
    'rewards' => [
        'reg_cmd'         => 'mg give %player% starter_kit 1', // Reg Reward (Sent to ID:0 by default)
        'daily_cmd'       => 'mg give %player% point 10',      // Daily Sign-in Command
        'sign_in_servers' => [0, 1]                            // Which servers receive the sign-in command? (IDs)
    ],

    // ... Database & SMTP configurations ...
];

```

### 🛠️ Admin Management Guide (Standard Version)

Visit `/admin.php` to access the admin panel:

1. **Check Updates**: Click **[Check Updates]** in the left sidebar. If a new version is available, the system will automatically pull code from GitHub and intelligently merge configurations for a seamless upgrade.
2. **RCON Terminal**: On the "Web Console" page, you can select any configured server and send backend commands directly.
3. **CDK Management**: When generating a CDK, you can specify whether it is for **"Global Use"** or **"Specific Server Only"**.

---

### ⚡ About Lite Version (Extreme Edition)

The v1.7.5 Lite version is a technical marvel.

* **Size**: Single file, with extremely compressed code.
* **Features**: Retains all frontend features of the Standard version (Multi-server Sign-in, Server Selector CDK, Status Display, Glassmorphism UI).
* **Usage**: It relies on the `config.php` generated by `install.php`. But the entry point has been changed to /?a=admin
---

### 📝 FAQ

**Q: Why is the frontend status bar not showing?**
A: Please check the **"Public Display Address"** in the admin settings. The frontend must be able to access this IP via the public API. If your server has a firewall enabled, ensure the API port is not blocked.

**Q: Sign-in rewards not received?**
A: 1. Ensure the **RCON Password** in the backend is correct. 2. Ensure `sign_in_servers` is filled in the "Reward Configuration" (e.g., `0` or `0,1`); otherwise, the system won't know which server to send rewards to.

**Q: How to work with the MetorGive plugin?**
A: This program is optimized for MetorGive, utilizing its offline delivery feature. Please install the plugin on your server and set the command in the backend to `/mg give %player% <item> <amount>`.

---

### 📄 Open Source License

This project is licensed under the **MIT License**. You are free to modify, distribute, or use it for commercial purposes.

