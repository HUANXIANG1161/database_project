-- ============================================================
-- 旅行信息分享平台 · 数据库建表脚本
-- 文件: sql/01_schema.sql
-- 数据库: travel_share
-- 环境: MySQL 8.0.45 / InnoDB / utf8mb4
-- 运行身份: MySQL root
-- 运行示例:
--   "D:\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 < sql\01_schema.sql
--
-- 注意:
--   1. 本脚本用于开发/课设环境，会先删除旧表再重建；
--      如已有数据，请先用 mysqldump 备份。
--   2. 建议执行顺序:
--      00_init.sql -> 01_schema.sql -> 02_views.sql ->
--      03_procedures.sql -> 04_triggers.sql
--   3. 所有表使用 InnoDB，支持事务和外键。
-- ============================================================

CREATE DATABASE IF NOT EXISTS `travel_share`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_0900_ai_ci;
USE `travel_share`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 按外键依赖的反序删除，保证脚本可重复执行
DROP TABLE IF EXISTS `like_record`;
DROP TABLE IF EXISTS `comment`;
DROP TABLE IF EXISTS `resource`;
DROP TABLE IF EXISTS `route_item`;
DROP TABLE IF EXISTS `route_version`;
DROP TABLE IF EXISTS `route`;
DROP TABLE IF EXISTS `team_notice`;
DROP TABLE IF EXISTS `team_member`;
DROP TABLE IF EXISTS `team`;
DROP TABLE IF EXISTS `trip`;
DROP TABLE IF EXISTS `guide_collect`;
DROP TABLE IF EXISTS `guide_tag`;
DROP TABLE IF EXISTS `guide`;
DROP TABLE IF EXISTS `tag`;
DROP TABLE IF EXISTS `notification`;
DROP TABLE IF EXISTS `message`;
DROP TABLE IF EXISTS `friendship`;
DROP TABLE IF EXISTS `user`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 一、用户与社交
-- ============================================================

-- 1. 用户表：只做禁用（status=disabled），不物理删除，保留历史内容
CREATE TABLE `user` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username`      VARCHAR(50)  NOT NULL                COMMENT '登录名，唯一',
  `password_hash` VARCHAR(255) NOT NULL                COMMENT 'password_hash() 生成的密码散列',
  `nickname`      VARCHAR(50)  NOT NULL                COMMENT '昵称',
  `avatar`        VARCHAR(500) DEFAULT NULL             COMMENT '头像URL',
  `gender`        ENUM('unknown','male','female','other') NOT NULL DEFAULT 'unknown' COMMENT '性别',
  `email`         VARCHAR(100) DEFAULT NULL             COMMENT '邮箱，唯一（可为空）',
  `phone`         VARCHAR(20)  DEFAULT NULL             COMMENT '手机号',
  `intro`         VARCHAR(500) DEFAULT NULL             COMMENT '个人简介',
  `role`          ENUM('user','admin') NOT NULL DEFAULT 'user'   COMMENT '角色',
  `status`        ENUM('active','disabled') NOT NULL DEFAULT 'active' COMMENT '账号状态',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_username` (`username`),
  UNIQUE KEY `uk_user_email` (`email`),
  KEY `idx_user_role_status` (`role`,`status`),
  KEY `idx_user_nickname` (`nickname`),
  CONSTRAINT `chk_user_username_len` CHECK (CHAR_LENGTH(`username`) BETWEEN 3 AND 50),
  CONSTRAINT `chk_user_nickname_len` CHECK (CHAR_LENGTH(`nickname`) BETWEEN 1 AND 50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户表';

-- 2. 好友关系：A->B 只存一条；反向重复由应用层校验
CREATE TABLE `friendship` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL COMMENT '发起方',
  `friend_id`  INT UNSIGNED NOT NULL COMMENT '接收方',
  `status`     ENUM('pending','accepted','blocked') NOT NULL DEFAULT 'pending' COMMENT '待确认/已接受/已拉黑',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_friendship_pair` (`user_id`,`friend_id`),
  KEY `idx_friendship_friend` (`friend_id`,`status`),
  -- MySQL 不允许 CHECK 用到的列同时带 CASCADE/SET NULL 引用动作；
  -- 用户采用软删除，这里用 RESTRICT 保证好友关系不会被级联误删。
  CONSTRAINT `fk_friendship_user`   FOREIGN KEY (`user_id`)   REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_friendship_friend` FOREIGN KEY (`friend_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_friendship_not_self` CHECK (`user_id` <> `friend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='好友关系表';

-- 3. 私聊消息
CREATE TABLE `message` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_id`    INT UNSIGNED NOT NULL COMMENT '发送者',
  `to_id`      INT UNSIGNED NOT NULL COMMENT '接收者',
  `content`    TEXT NOT NULL COMMENT '文本内容或图片URL',
  `msg_type`   ENUM('text','image') NOT NULL DEFAULT 'text',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_message_to_read` (`to_id`,`is_read`,`created_at`),
  KEY `idx_message_dialog` (`from_id`,`to_id`,`created_at`),
  CONSTRAINT `fk_message_from` FOREIGN KEY (`from_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_message_to`   FOREIGN KEY (`to_id`)   REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_message_content` CHECK (CHAR_LENGTH(`content`) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='私聊消息表';

-- 4. 站内通知：好友申请、入队审核、评论/点赞等
CREATE TABLE `notification` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL COMMENT '接收者',
  `type`       VARCHAR(30)  NOT NULL COMMENT '通知类型，如 team_approve/team_reject',
  `title`      VARCHAR(100) NOT NULL,
  `content`    VARCHAR(500) DEFAULT NULL,
  `link`       VARCHAR(500) DEFAULT NULL COMMENT '前端跳转链接',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notification_user` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='站内通知表';

-- ============================================================
-- 二、攻略与互动
-- ============================================================

-- 5. 标签字典
CREATE TABLE `tag` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(30) NOT NULL,
  `type` ENUM('theme','season','style','other') NOT NULL DEFAULT 'theme',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tag_name_type` (`name`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='标签字典表';

-- 6. 旅行攻略主表
CREATE TABLE `guide` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_id`     INT UNSIGNED NOT NULL COMMENT '作者',
  `title`         VARCHAR(100) NOT NULL,
  `destination`   VARCHAR(100) NOT NULL COMMENT '目的地',
  `cover`         VARCHAR(500) DEFAULT NULL COMMENT '封面图URL',
  `summary`       VARCHAR(500) DEFAULT NULL COMMENT '摘要',
  `content`       MEDIUMTEXT NOT NULL COMMENT '正文（富文本/HTML）',
  `day_count`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '行程天数',
  `cost_avg`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '人均花费',
  `season`        ENUM('spring','summer','autumn','winter','all') NOT NULL DEFAULT 'all',
  `view_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `like_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `collect_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `comment_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`        ENUM('draft','pending','published','rejected','hidden') NOT NULL DEFAULT 'draft' COMMENT '草稿/待审/已发布/驳回/隐藏',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '仅内容编辑时由应用更新',
  PRIMARY KEY (`id`),
  KEY `idx_guide_author` (`author_id`,`status`),
  KEY `idx_guide_status_time` (`status`,`created_at`),
  KEY `idx_guide_destination` (`destination`),
  KEY `idx_guide_season` (`season`),
  KEY `idx_guide_status_like` (`status`,`like_count`),
  CONSTRAINT `fk_guide_author` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_guide_day`  CHECK (`day_count` BETWEEN 1 AND 60),
  CONSTRAINT `chk_guide_cost` CHECK (`cost_avg` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='旅行攻略表';

-- 7. 攻略-标签 多对多
CREATE TABLE `guide_tag` (
  `guide_id` INT UNSIGNED NOT NULL,
  `tag_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`guide_id`,`tag_id`),
  KEY `idx_guide_tag_tag` (`tag_id`),
  CONSTRAINT `fk_guide_tag_guide` FOREIGN KEY (`guide_id`) REFERENCES `guide` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_guide_tag_tag`   FOREIGN KEY (`tag_id`)   REFERENCES `tag` (`id`)   ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='攻略标签关联表';

-- 8. 攻略收藏：复合主键天然防重复
CREATE TABLE `guide_collect` (
  `user_id`    INT UNSIGNED NOT NULL,
  `guide_id`   INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`guide_id`),
  KEY `idx_guide_collect_guide` (`guide_id`),
  CONSTRAINT `fk_guide_collect_user`  FOREIGN KEY (`user_id`)  REFERENCES `user` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_guide_collect_guide` FOREIGN KEY (`guide_id`) REFERENCES `guide` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='攻略收藏表';

-- ============================================================
-- 三、行程、队伍与路线
-- ============================================================

-- 9. 行程
CREATE TABLE `trip` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id`    INT UNSIGNED NOT NULL COMMENT '行程所有者',
  `title`       VARCHAR(100) NOT NULL,
  `destination` VARCHAR(100) NOT NULL,
  `start_date`  DATE NOT NULL,
  `end_date`    DATE NOT NULL,
  `budget`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cover`       VARCHAR(500) DEFAULT NULL,
  `description` VARCHAR(1000) DEFAULT NULL,
  `visibility`  ENUM('private','team','public') NOT NULL DEFAULT 'private',
  `status`      ENUM('planning','ongoing','finished') NOT NULL DEFAULT 'planning',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trip_owner` (`owner_id`,`status`),
  KEY `idx_trip_destination` (`destination`),
  KEY `idx_trip_start` (`start_date`),
  CONSTRAINT `fk_trip_owner` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_trip_dates`  CHECK (`end_date` >= `start_date`),
  CONSTRAINT `chk_trip_budget` CHECK (`budget` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='旅行行程表';

-- 10. 队伍：一个行程最多一个队伍（trip_id 唯一）
CREATE TABLE `team` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trip_id`         INT UNSIGNED NOT NULL,
  `leader_id`       INT UNSIGNED NOT NULL COMMENT '队长',
  `name`            VARCHAR(100) NOT NULL,
  `max_members`     TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `current_members` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '含队长',
  `description`     VARCHAR(500) DEFAULT NULL,
  `status`          ENUM('recruiting','full','ongoing','closed') NOT NULL DEFAULT 'recruiting',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_team_trip` (`trip_id`),
  KEY `idx_team_status` (`status`,`created_at`),
  KEY `idx_team_leader` (`leader_id`),
  CONSTRAINT `fk_team_trip`   FOREIGN KEY (`trip_id`)   REFERENCES `trip` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_team_leader` FOREIGN KEY (`leader_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_team_max` CHECK (`max_members` BETWEEN 2 AND 50),
  CONSTRAINT `chk_team_current` CHECK (`current_members` >= 1 AND `current_members` <= `max_members`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='旅行队伍表';

-- 11. 队伍成员/申请
CREATE TABLE `team_member` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id`      INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `status`       ENUM('pending','approved','rejected','quit','removed') NOT NULL DEFAULT 'pending',
  `apply_reason` VARCHAR(255) DEFAULT NULL,
  `joined_at`    DATETIME DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_team_member` (`team_id`,`user_id`),
  KEY `idx_team_member_user` (`user_id`,`status`),
  KEY `idx_team_member_team_status` (`team_id`,`status`),
  CONSTRAINT `fk_team_member_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_team_member_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='队伍成员与入队申请表';

-- 12. 队伍公告
CREATE TABLE `team_notice` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id`      INT UNSIGNED NOT NULL,
  `publisher_id` INT UNSIGNED NOT NULL,
  `title`        VARCHAR(100) NOT NULL,
  `content`      VARCHAR(1000) NOT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_notice_team` (`team_id`,`created_at`),
  CONSTRAINT `fk_team_notice_team`      FOREIGN KEY (`team_id`)      REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_team_notice_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='队伍公告表';

-- 13. 路线主表：一个行程一条路线，current_version 指向当前版本
CREATE TABLE `route` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trip_id`         INT UNSIGNED NOT NULL,
  `title`           VARCHAR(100) NOT NULL DEFAULT '行程路线',
  `current_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '当前版本号，乐观锁',
  `updated_by`      INT UNSIGNED NOT NULL COMMENT '最后更新人',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_route_trip` (`trip_id`),
  KEY `idx_route_updated_by` (`updated_by`),
  CONSTRAINT `fk_route_trip`       FOREIGN KEY (`trip_id`)    REFERENCES `trip` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_route_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_route_version` CHECK (`current_version` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='路线主表';

-- 14. 路线版本：每次保存新增一个版本，旧版本保留可回滚
CREATE TABLE `route_version` (
  `route_id`   INT UNSIGNED NOT NULL,
  `version_no` INT UNSIGNED NOT NULL,
  `editor_id`  INT UNSIGNED NOT NULL,
  `remark`     VARCHAR(255) DEFAULT NULL COMMENT '保存备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`route_id`,`version_no`),
  KEY `idx_route_version_editor` (`editor_id`),
  CONSTRAINT `fk_route_version_route`  FOREIGN KEY (`route_id`)  REFERENCES `route` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_route_version_editor` FOREIGN KEY (`editor_id`) REFERENCES `user` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_route_version_no` CHECK (`version_no` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='路线版本表';

-- 15. 路线条目：属于某个版本，按天和顺序排列
CREATE TABLE `route_item` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_id`   INT UNSIGNED NOT NULL,
  `version_no` INT UNSIGNED NOT NULL,
  `day_no`     TINYINT UNSIGNED NOT NULL COMMENT '第几天',
  `seq`        SMALLINT UNSIGNED NOT NULL COMMENT '当天顺序',
  `item_type`  ENUM('spot','food','hotel','traffic','other') NOT NULL DEFAULT 'spot',
  `title`      VARCHAR(100) NOT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_time`   TIME DEFAULT NULL,
  `cost`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `remark`     VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_route_item_seq` (`route_id`,`version_no`,`day_no`,`seq`),
  KEY `idx_route_item_version` (`route_id`,`version_no`,`day_no`),
  CONSTRAINT `fk_route_item_version` FOREIGN KEY (`route_id`,`version_no`)
    REFERENCES `route_version` (`route_id`,`version_no`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_route_item_day`  CHECK (`day_no` >= 1),
  CONSTRAINT `chk_route_item_seq`  CHECK (`seq` >= 1),
  CONSTRAINT `chk_route_item_cost` CHECK (`cost` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='路线条目表';

-- ============================================================
-- 四、行后资源
-- ============================================================

-- 16. 资源：照片/视频/文档，可关联一次行程
CREATE TABLE `resource` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uploader_id`   INT UNSIGNED NOT NULL,
  `trip_id`       INT UNSIGNED DEFAULT NULL COMMENT '关联行程，可为空',
  `type`          ENUM('photo','video','doc') NOT NULL,
  `title`         VARCHAR(100) NOT NULL,
  `description`   VARCHAR(500) DEFAULT NULL,
  `file_url`      VARCHAR(500) NOT NULL,
  `cover`         VARCHAR(500) DEFAULT NULL,
  `location`      VARCHAR(100) DEFAULT NULL COMMENT '拍摄/关联地点',
  `view_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `like_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `comment_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`        ENUM('pending','published','rejected','hidden') NOT NULL DEFAULT 'pending',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '仅内容编辑时由应用更新',
  PRIMARY KEY (`id`),
  KEY `idx_resource_uploader` (`uploader_id`,`status`),
  KEY `idx_resource_status_time` (`status`,`created_at`),
  KEY `idx_resource_trip` (`trip_id`),
  KEY `idx_resource_type` (`type`),
  CONSTRAINT `fk_resource_uploader` FOREIGN KEY (`uploader_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_resource_trip`     FOREIGN KEY (`trip_id`)     REFERENCES `trip` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='行后资源表';

-- ============================================================
-- 五、攻略/资源互动
-- ============================================================

-- 17. 统一评论表：guide_id/resource_id 二选一
CREATE TABLE `comment` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guide_id`    INT UNSIGNED DEFAULT NULL COMMENT '攻略评论时填写',
  `resource_id` INT UNSIGNED DEFAULT NULL COMMENT '资源评论时填写',
  `user_id`     INT UNSIGNED NOT NULL COMMENT '评论者',
  `parent_id`   BIGINT UNSIGNED DEFAULT NULL COMMENT '回复的上级评论',
  `content`     TEXT NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_guide` (`guide_id`,`created_at`),
  KEY `idx_comment_resource` (`resource_id`,`created_at`),
  KEY `idx_comment_user` (`user_id`),
  KEY `idx_comment_parent` (`parent_id`),
  -- 软删除策略下，评论存在时不允许物理删除攻略/资源，故用 RESTRICT；
  -- 同时 MySQL 要求 CHECK 用到的列不能带 CASCADE/SET NULL 引用动作。
  CONSTRAINT `fk_comment_guide`    FOREIGN KEY (`guide_id`)    REFERENCES `guide` (`id`)    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_comment_resource` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_comment_user`     FOREIGN KEY (`user_id`)     REFERENCES `user` (`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comment_parent`   FOREIGN KEY (`parent_id`)   REFERENCES `comment` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_comment_target` CHECK (
    (`guide_id` IS NOT NULL AND `resource_id` IS NULL)
    OR (`guide_id` IS NULL AND `resource_id` IS NOT NULL)
  ),
  CONSTRAINT `chk_comment_content` CHECK (CHAR_LENGTH(`content`) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='统一评论表（攻略/资源）';

-- 18. 统一点赞表：guide_id/resource_id 二选一
--     两个复合唯一键分别防止重复点赞：
--     (user_id, guide_id) 对 guide_id 为 NULL 的行不生效，
--     (user_id, resource_id) 对 resource_id 为 NULL 的行不生效，
--     正好对应两种目标。
CREATE TABLE `like_record` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `guide_id`    INT UNSIGNED DEFAULT NULL,
  `resource_id` INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_like_guide` (`user_id`,`guide_id`),
  UNIQUE KEY `uk_like_resource` (`user_id`,`resource_id`),
  KEY `idx_like_guide` (`guide_id`),
  KEY `idx_like_resource` (`resource_id`),
  CONSTRAINT `fk_like_user`     FOREIGN KEY (`user_id`)     REFERENCES `user` (`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
  -- 与 comment 同理：CHECK 列不能带 CASCADE，点赞记录随攻略/资源软删除保留。
  CONSTRAINT `fk_like_guide`    FOREIGN KEY (`guide_id`)    REFERENCES `guide` (`id`)    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_like_resource` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_like_target` CHECK (
    (`guide_id` IS NOT NULL AND `resource_id` IS NULL)
    OR (`guide_id` IS NULL AND `resource_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='统一点赞表（攻略/资源）';

-- ============================================================
-- 完成提示
-- ============================================================
SELECT '01_schema.sql 执行完成：18 张表、外键、CHECK 与索引已创建' AS `result`;
