<div id="cn"></div>

# AuthMe账号管理面板 / AuthMe Web Portal

[English](#en) | [中文说明](#cn)

![License](https://img.shields.io/badge/license-MIT-blue.svg) ![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg) ![Version](https://img.shields.io/badge/version-v1.5-green.svg)

**流星MCS 账号管理器** 是一个专为 Minecraft 服务器设计的轻量级 Web 用户中心。它允许玩家通过网页注册账号、找回密码，并提供强大的后台管理功能。

本项目专为配合 [AuthMeReloaded](https://github.com/AuthMe/AuthMeReloaded) 插件使用，支持 MySQL 数据库同步。

---

## <a name="中文说明"></a>✨ 功能特性 (Features)

* **双版本选择**：提供 **标准版 (Standard)** 和 **单文件版 (Lite)** 以适应不同需求。
* **玩家注册**：简洁现代的 UI，集成图形验证码，防止机器注册。
* **密码找回**：内置轻量级 SMTP 客户端，支持邮件发送验证码重置密码。
* **后台管理**：
    * 可视化修改系统设置（数据库、SMTP、管理员账号）。
    * 查看和搜索注册玩家信息。
    * 一键发送测试邮件。
* **安全防护**：
    * **防暴力破解**：同一 IP 连续 3 次密码错误自动封禁 1 小时。
    * **安装锁**：检测到配置文件后自动禁用安装程序。
* **零依赖**：无需 Composer，无需复杂框架，上传即用。

---

## 📦 版本对比 (Editions)

| 特性 | 标准版 (Standard v1.5) | Lite 单文件版 (Lite v0.150) |
| :--- | :--- | :--- |
| **文件结构** | 分离式 (`index.php`, `admin.php`, `install.php`) | 单文件 (`index.php`) |
| **安全性** | ⭐⭐⭐⭐⭐ (后台入口可隐藏/改名) | ⭐⭐⭐ (入口固定) |
| **维护性** | 高 (逻辑清晰，易于二次开发) | 中 (便携为主) |
| **适用场景** | 正式运营服务器、长期项目 | 测试服、好友联机、临时部署 |

---

## 🚀 快速开始 (Quick Start)

### 环境要求
* PHP 7.4 或更高版本
* MySQL / MariaDB 数据库
* Web 服务器 (Nginx/Apache/IIS)
* Minecraft 服务器安装了 AuthMeReloaded 插件

### 🛠️ 部署步骤

#### 方案 A：部署标准版 (推荐)
1.  下载本项目中的 `Standard` 文件夹内容。
2.  将 `index.php`, `install.php`, `admin.php` 上传至网站根目录。
3.  访问 `http://你的域名/install.php` 进行初始化安装。
4.  **安全建议**：安装完成后，请删除 `install.php`，并将 `admin.php` 重命名为只有你知道的名字（如 `manager_888.php`）。

#### 方案 B：部署 Lite 版
1.  下载本项目中的 `Lite` 文件夹内容。
2.  将 `index.php` (原名 lite.php) 上传至网站根目录。
3.  访问 `http://你的域名/index.php`，系统会自动引导进入安装界面。

---

### 🔌 AuthMe 插件配置

为了让网页注册的账号能在游戏里登录，请务必修改服务器端 `plugins/AuthMe/config.yml`：

```yaml
DataSource:
  backend: 'MYSQL'
  mySQLHost: '127.0.0.1' # 数据库地址
  mySQLPort: '3306'
  mySQLUsername: '你的数据库用户名'
  mySQLPassword: '你的数据库密码'
  mySQLDatabase: '你的数据库名(默认authme)'
  mySQLColumnName: 'username'
  mySQLColumnPassword: 'password'
  mySQLColumnIp: 'ip'
  mySQLColumnLastLogin: 'lastlogin'
  mySQLColumnEmail: 'email'
  
security:
  # 必须与网页端加密方式一致
  passwordHash: 'SHA256'

```

修改完成后，在控制台输入 `/authme reload` 重载配置。

---

## 🛡️ 安全机制说明

为了保护服务器安全，本程序内置了以下防御机制：

1.  **后台防爆破 (Anti-Brute Force)：**
    * 后台登录接口会实时监测 **IP 行为**。
    * 如果同一个 IP 在 1 小时内连续输错 3 次密码，系统将**自动锁定该 IP**，期间无法访问后台。
    * **解锁方法：** 如果你是管理员且不小心被锁，请通过 FTP 或宝塔面板的文件管理器，删除网站根目录下的 `login_limit.json` 文件，即可立即解除锁定。

2.  **安装程序自锁：**
    * `install.php` 在检测到配置文件 `config.php` 存在时，会**自动拒绝运行**，防止被他人恶意重置。

---

## 📄 开源协议

本项目遵循 [MIT License](https://opensource.org/licenses/MIT) 协议。  
你可以自由地使用、修改和分发本项目，但请保留原作者版权声明。

<div id="en"></div>

## 📖 English Description

**AuthMe Web Portal** is a lightweight, secure Web User Center designed for Minecraft servers. It allows players to register accounts via a web interface, reset passwords via email, and provides a powerful admin dashboard for server owners.

This project is built to integrate seamlessly with the [AuthMeReloaded](https://github.com/AuthMe/AuthMeReloaded) plugin using MySQL.

### ✨ Features

* **Two Editions**: Available in **Standard (v1.5)** and **Lite (v0.150)** to suit different needs.
* **User Registration**: Modern UI with built-in Captcha protection.
* **Password Reset**: Integrated lightweight SMTP client for sending verification codes via email.
* **Admin Dashboard**:
    * Visual configuration for Database, SMTP, and Admin credentials.
    * Manage and search registered players.
    * One-click email configuration testing.
* **Security**:
    * **Brute-force Protection**: IP is locked for 1 hour after 3 failed login attempts.
    * **Install Lock**: The installer is automatically disabled after configuration is generated.
* **Zero Dependencies**: No Composer required, no complex frameworks. Just upload and run.

### 📦 Editions

| Feature | Standard Edition (v1.5) | Lite Edition (v0.150) |
| :--- | :--- | :--- |
| **Structure** | Separated Files (`index.php`, `admin.php`, `install.php`) | Single File (`index.php`) |
| **Security** | ⭐⭐⭐⭐⭐ (Admin URL can be hidden/renamed) | ⭐⭐⭐ (Fixed URL) |
| **Maintainability** | High (Clear logic separation) | Medium (Portable focused) |
| **Best For** | Production Servers, Long-term use | Test Servers, Private SMPs |

### 🚀 Quick Start

#### Prerequisites
* PHP 7.4 or higher
* MySQL / MariaDB
* Web Server (Nginx/Apache/IIS)
* Minecraft Server with AuthMeReloaded plugin installed

#### 🛠️ Installation

**Option A: Standard Edition (Recommended)**
1.  Download files from the `Standard` folder.
2.  Upload `index.php`, `install.php`, and `admin.php` to your web root.
3.  Navigate to `http://yourdomain.com/install.php` to run the setup wizard.
4.  **Security Tip**: After installation, DELETE `install.php` and RENAME `admin.php` to something secret (e.g., `super_admin.php`) to hide your dashboard.

**Option B: Lite Edition**
1.  Download the file from the `Lite` folder.
2.  Upload `index.php` to your web root.
3.  Navigate to `http://yourdomain.com/index.php`. It will automatically redirect you to the installation setup.

### 🔌 AuthMe Configuration

To ensure web-registered accounts work in-game, verify your `plugins/AuthMe/config.yml` settings:

```yaml
DataSource:
  backend: 'MYSQL'
  mySQLHost: '127.0.0.1' # Database Host
  # ... enter your credentials
  
security:
  # MUST match the web system's hashing algorithm
  passwordHash: 'SHA256'

```

After the modification is complete, enter `/authme reload` in the console to reload the configuration.

---

## 🛡️ Security Mechanisms

To ensure server security, this program includes the following built-in defense mechanisms:

1.  **Anti-Brute Force Protection:**
    * The admin login interface monitors **IP behavior** in real-time.
    * If the same IP enters the wrong password **3 consecutive times** within 1 hour, the system will **automatically lock the IP**, preventing further access to the admin dashboard.
    * **How to Unlock:** If you are the administrator and get locked out accidentally, use FTP or a file manager (like BT Panel) to delete the `login_limit.json` file in the website's root directory to immediately restore access.

2.  **Installer Auto-Lock:**
    * `install.php` will **automatically refuse to run** if it detects that the `config.php` file already exists, preventing unauthorized resets.

---

## 📄 Open Source License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).  
You are free to use, modify, and distribute this project, provided that the original author's copyright notice is retained.
