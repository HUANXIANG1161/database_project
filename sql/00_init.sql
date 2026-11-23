-- ============================================================
-- 旅行信息分享平台 · 数据库与账号初始化
-- 文件: sql/00_init.sql
-- 运行身份: MySQL root
-- 运行示例:
--   "D:\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p < sql\00_init.sql
-- 说明:
--   1. 创建数据库 travel_share（utf8mb4）。
--   2. 创建应用账号 travel_app，密码 CHANGE_ME_APP_PASSWORD。
--   3. 只授予业务所需的 SELECT/INSERT/UPDATE/DELETE/EXECUTE，
--      不授予 DROP/ALTER/CREATE 等 DDL 权限（最小权限原则）。
-- ============================================================

CREATE DATABASE IF NOT EXISTS `travel_share`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_0900_ai_ci;

-- PHP 用 localhost（socket）或 127.0.0.1（TCP）连接时都能匹配到账号
CREATE USER IF NOT EXISTS 'travel_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_APP_PASSWORD';
CREATE USER IF NOT EXISTS 'travel_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_APP_PASSWORD';

ALTER USER 'travel_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_APP_PASSWORD';
ALTER USER 'travel_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_APP_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE
  ON `travel_share`.* TO 'travel_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE
  ON `travel_share`.* TO 'travel_app'@'127.0.0.1';

FLUSH PRIVILEGES;

-- 查看结果（可选）
-- SHOW DATABASES LIKE 'travel_share';
-- SHOW GRANTS FOR 'travel_app'@'127.0.0.1';
