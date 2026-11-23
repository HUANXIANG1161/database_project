-- ============================================================
-- 06_reset_business_data.sql
-- 清空课程设计演示业务数据，保留：
--   1. user 表：alice / bob / carol / dave / admin，密码均为 123456
--   2. tag 表：攻略标签字典
-- 清空后可以自己注册/登录并录入攻略、行程、队伍、路线、资源、好友、私聊等数据。
-- 注意：这是不可逆操作；如需恢复演示数据，重新执行 05_seed.sql。
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 社交
DELETE FROM `friendship`;
DELETE FROM `message`;
DELETE FROM `notification`;

-- 攻略与互动（先删子表，再删 guide）
DELETE FROM `comment`;
DELETE FROM `like_record`;
DELETE FROM `guide_collect`;
DELETE FROM `guide_tag`;
DELETE FROM `guide`;

-- 行程、队伍、路线（先删子表，再删 trip）
DELETE FROM `team_member`;
DELETE FROM `team_notice`;
DELETE FROM `team`;
DELETE FROM `route_item`;
DELETE FROM `route_version`;
DELETE FROM `route`;
DELETE FROM `trip`;

-- 资源
DELETE FROM `resource`;

SET FOREIGN_KEY_CHECKS = 1;

-- 重置自增，方便自己测试时从 1 开始编号
ALTER TABLE `friendship` AUTO_INCREMENT = 1;
ALTER TABLE `message` AUTO_INCREMENT = 1;
ALTER TABLE `notification` AUTO_INCREMENT = 1;
ALTER TABLE `comment` AUTO_INCREMENT = 1;
ALTER TABLE `like_record` AUTO_INCREMENT = 1;
ALTER TABLE `guide_collect` AUTO_INCREMENT = 1;
ALTER TABLE `guide_tag` AUTO_INCREMENT = 1;
ALTER TABLE `guide` AUTO_INCREMENT = 1;
ALTER TABLE `team_member` AUTO_INCREMENT = 1;
ALTER TABLE `team_notice` AUTO_INCREMENT = 1;
ALTER TABLE `team` AUTO_INCREMENT = 1;
ALTER TABLE `route_item` AUTO_INCREMENT = 1;
ALTER TABLE `route_version` AUTO_INCREMENT = 1;
ALTER TABLE `route` AUTO_INCREMENT = 1;
ALTER TABLE `trip` AUTO_INCREMENT = 1;
ALTER TABLE `resource` AUTO_INCREMENT = 1;

-- 保留的用户与标签数量
SELECT 'user' AS table_name, COUNT(*) AS row_count FROM `user`
UNION ALL
SELECT 'tag', COUNT(*) FROM `tag`;
