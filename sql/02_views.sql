-- ============================================================
-- 旅行信息分享平台 · 视图
-- 文件: sql/02_views.sql
-- 运行身份: MySQL root（先执行 01_schema.sql）
-- 运行示例:
--   "D:\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 < sql\02_views.sql
--
-- 说明:
--   1. 三个视图分别服务攻略卡片、队伍卡片、当前路线展示。
--   2. 使用 SQL SECURITY INVOKER，视图按调用者权限执行；
--      travel_app 已拥有基础表的 SELECT 权限。
-- ============================================================

USE `travel_share`;

-- 攻略卡片：作者信息 + 标签聚合 + 互动计数
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_guide_card` AS
SELECT
  g.`id`            AS `id`,
  g.`author_id`     AS `author_id`,
  u.`nickname`      AS `author_nickname`,
  u.`avatar`        AS `author_avatar`,
  g.`title`         AS `title`,
  g.`destination`   AS `destination`,
  g.`cover`         AS `cover`,
  g.`summary`       AS `summary`,
  g.`day_count`     AS `day_count`,
  g.`cost_avg`      AS `cost_avg`,
  g.`season`        AS `season`,
  g.`view_count`    AS `view_count`,
  g.`like_count`    AS `like_count`,
  g.`collect_count` AS `collect_count`,
  g.`comment_count` AS `comment_count`,
  g.`status`        AS `status`,
  g.`created_at`    AS `created_at`,
  (
    SELECT GROUP_CONCAT(t.`name` ORDER BY t.`name` SEPARATOR ',')
    FROM `guide_tag` gt
    JOIN `tag` t ON t.`id` = gt.`tag_id`
    WHERE gt.`guide_id` = g.`id`
  ) AS `tags`
FROM `guide` g
JOIN `user` u ON u.`id` = g.`author_id`;

-- 队伍卡片：行程信息 + 队长信息 + 人数
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_team_card` AS
SELECT
  t.`id`              AS `id`,
  t.`trip_id`         AS `trip_id`,
  t.`name`            AS `name`,
  t.`max_members`     AS `max_members`,
  t.`current_members` AS `current_members`,
  t.`description`     AS `description`,
  t.`status`          AS `status`,
  t.`created_at`      AS `created_at`,
  tr.`title`          AS `trip_title`,
  tr.`destination`    AS `destination`,
  tr.`start_date`     AS `start_date`,
  tr.`end_date`       AS `end_date`,
  tr.`owner_id`       AS `trip_owner_id`,
  u.`id`              AS `leader_id`,
  u.`nickname`        AS `leader_nickname`,
  u.`avatar`          AS `leader_avatar`
FROM `team` t
JOIN `trip` tr ON tr.`id` = t.`trip_id`
JOIN `user` u ON u.`id` = t.`leader_id`;

-- 当前路线：路线主表 + 当前版本 + 条目数
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_route_current` AS
SELECT
  r.`id`              AS `id`,
  r.`trip_id`         AS `trip_id`,
  r.`title`           AS `title`,
  r.`current_version` AS `current_version`,
  r.`updated_by`      AS `updated_by`,
  rv.`editor_id`      AS `editor_id`,
  rv.`remark`         AS `remark`,
  rv.`created_at`     AS `version_created_at`,
  u.`nickname`        AS `editor_nickname`,
  tr.`title`          AS `trip_title`,
  (
    SELECT COUNT(*)
    FROM `route_item` ri
    WHERE ri.`route_id` = r.`id`
      AND ri.`version_no` = r.`current_version`
  ) AS `item_count`
FROM `route` r
JOIN `route_version` rv
  ON rv.`route_id` = r.`id`
 AND rv.`version_no` = r.`current_version`
JOIN `user` u ON u.`id` = rv.`editor_id`
JOIN `trip` tr ON tr.`id` = r.`trip_id`;

SELECT '02_views.sql 执行完成：v_guide_card / v_team_card / v_route_current' AS `result`;
