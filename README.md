# 旅行信息分享平台（课程设计）

> 实践项目 4：集成前端、服务端及网络数据库设计开发旅行信息分享应用
>
> 技术栈：PHP 8.4 + MySQL 8.0 + Bootstrap 5（B/S 三层，服务端渲染）

---

## 1. 项目简介

面向旅行者的一站式信息分享平台，覆盖旅行前、旅行中、旅行后三个阶段：

- **旅行前**：攻略发布与筛选、标签检索、收藏；创建行程、组建队伍、入队申请与审核。
- **旅行中**：路线按版本云备份、队伍公告、队员管理、好友私聊与站内通知。
- **旅行后**：照片/视频/文档资源上传与浏览、评论回复、点赞收藏、好友互动。

课程设计重点在于 **前端页面 + PHP 服务端 + MySQL 数据库** 三层的完整打通，以及数据库设计中的实体关系、约束、索引、视图、存储过程、触发器与事务。

---

## 2. 技术方案

| 层次 | 选型 | 说明 |
| --- | --- | --- |
| 客户端 | 浏览器 + Bootstrap 5 | 响应式页面，不开发独立 App |
| 服务端 | PHP 8.4 原生 + PDO | 不依赖框架，便于课设展示与逐行注释 |
| 数据库 | MySQL 8.0.45 / InnoDB / utf8mb4 | 外键、CHECK、视图、存储过程、触发器 |
| 会话 | PHP Session | 登录态、角色权限（user/admin）、CSRF |
| 运行方式 | PHP 内置服务器 | `0.0.0.0:8080`，本机或局域网浏览器访问 |

### 本机环境

| 组件 | 位置/版本 |
| --- | --- |
| 工作目录 | `D:\database_project` |
| MySQL | `D:\MySQL\MySQL Server 8.0`（8.0.45，端口 3306） |
| MySQL 数据目录 | `D:\database_project\mysql_data` |
| PHP | `D:\database_project\tools\php`（8.4.28 NTS） |
| 应用数据库 | `travel_share` |
| 应用账号 | `travel_app` / `CHANGE_ME_APP_PASSWORD` |
| MySQL root | 本地开发密码不写入仓库，执行 SQL 时按提示输入 |

---

## 3. 目录结构

```text
D:\database_project\
├─ sql\                           # 数据库脚本（按顺序执行）
│  ├─ 00_init.sql                 # 建库、应用账号、最小权限
│  ├─ 01_schema.sql               # 18 张表、外键、CHECK、索引
│  ├─ 02_views.sql                # 3 个视图
│  ├─ 03_procedures.sql           # 2 个存储过程
│  ├─ 04_triggers.sql             # 6 个计数触发器
│  ├─ 05_seed.sql                 # 演示种子数据（会清空 18 表）
│  └─ 06_reset_business_data.sql  # 只清业务数据，保留账号和标签
├─ docs\
│  ├─ ER.md                       # ER 图、表结构、关键设计说明
│  ├─ 项目实现说明.md              # 逐文件、逐模块实现细节
│  └─ screenshots\                # 测试与报告截图目录
├─ app\                           # 公共代码
│  ├─ config.php                  # 数据库与站点配置
│  ├─ bootstrap.php               # 会话、时区、自动加载公共函数
│  ├─ db.php                      # PDO 连接与查询封装
│  ├─ auth.php                    # 登录、权限、未读数
│  ├─ helpers.php                 # 转义、URL、CSRF、格式化等
│  └─ views\
│     ├─ header.php               # 公共页头与导航
│     ├─ footer.php               # 公共页脚
│     └─ admin_nav.php            # 后台二级导航
├─ public\                        # Web 根目录
│  ├─ index.php                   # 首页
│  ├─ login.php / register.php / logout.php
│  ├─ guides.php / guide.php / guide_edit.php / guide_my.php
│  ├─ trips.php / trip.php / trip_edit.php / trip_my.php
│  ├─ teams.php / team.php / team_edit.php
│  ├─ route.php / route_edit.php / route_history.php
│  ├─ resources.php / resource.php / resource_upload.php
│  ├─ friends.php / messages.php / notifications.php / profile.php
│  ├─ admin\                      # 精简管理后台
│  ├─ uploads\                    # 用户上传与演示占位资源
│  └─ assets\                     # Bootstrap、style.css、app.js、默认图
├─ tools\                         # 本机 PHP、测试脚本、演示资源生成脚本
├─ mysql_data\                    # MySQL 数据目录（课设本机）
├─ logs\                          # PHP 错误日志
├─ storage\                       # 运行时会话与上传临时文件
├─ start.bat                      # 一键启动 MySQL + PHP
├─ stop.bat                       # 一键停止 PHP + MySQL
└─ README.md
```

---

## 4. 数据库设计概要

共 **18 张表**，分为四组：

1. **用户与社交**：`user`、`friendship`、`message`、`notification`
2. **攻略与互动**：`guide`、`tag`、`guide_tag`、`guide_collect`、`comment`、`like_record`
3. **行程、队伍与路线**：`trip`、`team`、`team_member`、`team_notice`、`route`、`route_version`、`route_item`
4. **行后资源**：`resource`

核心关系与设计：

- 一个行程最多一支队伍（`team.trip_id UNIQUE`），一个行程最多一条路线（`route.trip_id UNIQUE`）；
- 路线每次保存新增 `route_version`，旧版本保留，`route.current_version` 指向当前版本，支持查看与恢复历史版本；
- 评论/点赞通过 `guide_id`/`resource_id` 二选一 + `CHECK` 统一到一张表，并保留真实外键；
- 攻略/资源的点赞、评论、收藏计数由 6 个触发器维护；
- 用户、攻略、资源采用状态字段软删除/软隐藏，不物理删除；
- `comment`、`like_record` 的外键采用 `RESTRICT`，避免级联删除时触发器更新正在删除的父行。

详细 ER 图与表结构见 [`docs/ER.md`](docs/ER.md)。

---

## 5. 启动与运行

### 5.1 一键启动/停止（推荐）

项目根目录提供两个 BAT 脚本，适合日常演示：

| 文件 | 作用 |
| --- | --- |
| `start.bat` | 检查 3306/8080 端口；未运行则先启动 MySQL，再启动 PHP，最后打开 <http://127.0.0.1:8080> |
| `stop.bat` | 先按 8080 端口结束 PHP，再用 `mysqladmin` 正常关闭 MySQL |

双击运行即可；如果提示权限不足，右键选择“以管理员身份运行”。两个服务分别运行在最小化窗口中，关闭对应窗口也会停止该服务。数据目录 `D:\database_project\mysql_data` 不要删除。

### 5.2 启动 MySQL

若 3306 端口尚未监听，在项目根目录执行：

```powershell
& "D:\MySQL\MySQL Server 8.0\bin\mysqld.exe" `
  --datadir="D:\database_project\mysql_data" `
  --port=3306 `
  --console
```

> 本机课设数据目录为 `D:\database_project\mysql_data`，未改动 MySQL 安装目录下的 Data。手动启动时日志输出在 MySQL 控制台窗口；用 `start.bat` 启动时窗口会最小化，可还原查看。

### 5.3 初始化数据库

首次运行先复制本地配置并填写数据库密码（该文件不会被 Git 跟踪）：

```powershell
Copy-Item app\config.local.example.php app\config.local.php
# 编辑 app\config.local.php，把 db.pass 改成自己的 travel_app 密码
```

首次运行或需要重置演示数据时，按顺序执行 `00` → `05`：

```powershell
$mysql = "D:\MySQL\MySQL Server 8.0\bin\mysql.exe"
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/00_init.sql"
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/01_schema.sql"
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/02_views.sql"
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/03_procedures.sql"
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/04_triggers.sql"
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/05_seed.sql"
```

`05_seed.sql` 会先清空 18 张表再写入演示数据，重复执行前请确认无需保留用户数据。

只想清空自己录入的攻略、行程、队伍、路线、资源、好友、私聊，保留 5 个测试账号和标签字典：

```powershell
& $mysql -h 127.0.0.1 -P 3306 -u root -p --default-character-set=utf8mb4 --execute="source D:/database_project/sql/06_reset_business_data.sql"
```

### 5.4 启动 Web 服务

```powershell
& "D:\database_project\tools\php\php.exe" -S 0.0.0.0:8080 -t "D:\database_project\public"
```

浏览器访问：

- 本机：<http://127.0.0.1:8080>
- 局域网手机：`http://电脑局域网IP:8080`

### 5.5 测试账号

| 账号 | 密码 | 角色 | 演示用途 |
| --- | --- | --- | --- |
| `alice` | `123456` | user | 攻略作者、行程发起人、队长 |
| `bob` | `123456` | user | 队员、入队申请人、评论者 |
| `carol` | `123456` | user | 好友、私聊对象 |
| `dave` | `123456` | user | 资源上传者 |
| `admin` | `123456` | admin | 精简后台管理 |

> 所有密码哈希均由 PHP `password_hash('123456', PASSWORD_DEFAULT)` 生成，已写入 `05_seed.sql`。

---

## 6. 功能清单

### 6.1 前台

- 首页统计卡片、最新攻略、热门队伍、热门攻略、最新资源；
- 攻略列表：关键词、目的地、季节、标签、排序（最新/最热/收藏/评论）、分页；
- 攻略详情：浏览计数、点赞/收藏切换、评论与回复、作者信息、标签；
- 攻略发布/编辑：草稿、待审、管理员直发；标签多选；待审通知管理员；封面直接选择本地图片上传；
- 我的攻略、我的行程；
- 行程列表与详情：可见性控制、路线时间线、队伍侧栏、申请/取消/退出队伍；
- 行程发布/编辑、组建队伍、队伍详情、公告、成员移除、入队审核（存储过程）；封面直接选择本地图片上传；
- 路线编辑：按天/序号/类型/时间/费用编辑，保存时生成新版本；每条路线的开始时间不能晚于结束时间；
- 路线历史：查看旧版本、恢复旧版本为新版本；
- 资源列表/详情/上传：照片、视频、PDF；点赞、评论、软隐藏；
- 图片上传：攻略封面、行程封面、用户头像、资源视频/文档封面均可直接选择本地图片，保存到 `public/uploads/`，自动清理旧图；
- 好友：申请、接受、拒绝、移除、拉黑、用户搜索；
- 私聊：仅好友可发，未读计数、已读状态、会话列表；
- 通知：列表、单条/全部标记已读、旧链接兼容映射；
- 个人主页与资料编辑。

### 6.2 后台（精简版）

- 概览：活跃用户、已发布攻略/行程/资源、待审数量、未读通知/私聊；
- 用户管理：搜索、状态筛选、启用/禁用（保护自己和最后一个管理员）；
- 攻略审核：通过/拒绝/隐藏/重新发布，通知作者；
- 资源审核：通过/拒绝/隐藏/重新发布，通知上传者。

---

## 7. 数据库对象清单

| 对象 | 数量 | 说明 |
| --- | --- | --- |
| 表 | 18 | 用户社交、攻略互动、行程队伍路线、资源 |
| 视图 | 3 | `v_guide_card`、`v_team_card`、`v_route_current` |
| 存储过程 | 2 | `sp_search_guide`、`sp_review_join` |
| 触发器 | 6 | 点赞/评论/收藏计数维护 |
| 索引 | 多个 | 用户名/邮箱唯一、标签唯一、队伍成员唯一、消息未读索引等 |

关键 SQL：

- `sql/01_schema.sql`：18 表 DDL、外键、CHECK、索引、中文注释；
- `sql/02_views.sql`：攻略卡片、队伍卡片、当前路线视图；
- `sql/03_procedures.sql`：攻略筛选分页、入队审核事务；
- `sql/04_triggers.sql`：计数触发器；
- `sql/05_seed.sql`：5 用户、5 攻略、4 行程、4 队伍、5 资源及互动数据。

---

## 8. 测试与验证

### 8.1 已完成的自动检查

- PHP lint：`app/` 与 `public/` 共 37 个 PHP 文件，0 语法错误；
- HTTP 冒烟：未登录、alice、admin 三种会话，35 个页面/路由全部返回预期状态，无 PHP 错误文本；
- 端到端功能测试：
  - 发布待审攻略 → 作者可见 → 管理员审核通过 → 游客可见；
  - 攻略点赞、评论；
  - 创建行程 → 组建队伍 → bob 申请 → 队长通过（`sp_review_join`）；
  - 路线保存 v1/v2 → 历史版本可查；
  - 资源上传（图片）→ 详情页可见；
  - bob 加 carol 好友 → carol 接受 → 私聊发送/读取；
  - 后台审核攻略。

### 8.2 手工测试建议

1. 用 alice 登录，发布一篇攻略并选择“待审核”；
2. 用 admin 登录，在后台通过该攻略；
3. 用 alice 创建行程并组建队伍；
4. 用 bob 登录，在“队伍”页找到该队并申请；
5. 用 alice 在队伍页通过申请；
6. 用 alice 编辑路线并保存两次，查看版本历史；
7. 用 dave 上传一张照片/一个 PDF；
8. 用 bob 与 carol 互加好友并私聊；
9. 用 admin 禁用/启用测试用户、审核资源。

### 8.3 多客户端测试

同一台电脑同时开多个“客户端”：

1. 不同浏览器（Chrome / Edge / Firefox）各登录一个账号；
2. 同一浏览器开多个 Profile；
3. 普通窗口 + 无痕窗口；
4. 手机与电脑连同一 Wi-Fi，手机访问 `http://电脑局域网IP:8080`。

> Cookie 按 host 隔离、不按端口隔离，因此 `localhost:8080` 与 `127.0.0.1:8080` 可视为两个独立的登录域，适合快速模拟两个用户。

---

## 9. 课程设计报告素材

开发过程中同步积累：

- 需求分析、用例图、模块图；
- ER 图、数据字典、建表 SQL；
- 系统架构图、页面截图、核心代码；
- 关键业务流程图（登录、发布攻略、组队审核、路线保存、资源分享）；
- 测试用例、测试数据与测试结果截图；
- 开发日志、遇到的问题与解决措施；
- 心得体会、参考文献。

当前已有：

- [`docs/ER.md`](docs/ER.md)：ER 图、表结构摘要、关键设计；
- [`docs/项目实现说明.md`](docs/项目实现说明.md)：逐文件功能、关键代码、请求流程、上传实现与常见问题；
- `docs/screenshots/`：页面截图目录；
- 图片上传已用真实浏览器验证：攻略封面、行程封面、头像、PDF 资源及封面均能落盘并在页面显示；
- `tools/smoke_test.js`、`tools/integration_test.js`：HTTP 冒烟与端到端测试脚本；
- `sql/`：完整建库、建表、视图、过程、触发器、种子数据。

---

## 10. 已知限制

- 本项目为课程设计用途，root 密码、应用账号密码均写在文档中，仅用于本机开发环境，不用于生产；
- PHP 内置服务器适合课设演示，不适合生产并发；
- 上传文件已做 MIME 白名单与大小限制（图片 5MB、资源主文件 20MB），但生产环境还应增加病毒扫描、对象存储与更严格的权限控制；
- 视频演示文件为公开示例视频，仅用于验证播放组件，不代表真实行程素材；
- 管理后台为精简版，未实现完整 RBAC、操作审计与批量处理。

---

## 11. 参考与说明

- PHP 官方文档：<https://www.php.net/manual/zh/>
- MySQL 8.0 Reference Manual：<https://dev.mysql.com/doc/refman/8.0/en/>
- Bootstrap 5 文档：<https://getbootstrap.com/docs/5.3/>
- 数据库设计中的视图、存储过程、触发器与事务均可在 `sql/` 目录中逐行查看。
