-- ============================================================
-- 旅行信息分享平台 · 触发器
-- 文件: sql/04_triggers.sql
-- 运行身份: MySQL root（先执行 01_schema.sql）
-- 运行示例:
--   "D:\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 < sql\04_triggers.sql
--
-- 说明:
--   用 6 个触发器维护攻略/资源的冗余计数：
--     like_record    增删 -> like_count
--     comment        增删 -> comment_count
--     guide_collect  增删 -> collect_count
--   删除时用 IF(count > 0, count - 1, 0) 防止无符号列减到下溢。
-- ============================================================

USE `travel_share`;

DELIMITER $$

-- ---------- 点赞计数 ----------
DROP TRIGGER IF EXISTS `trg_like_ai`$$
CREATE TRIGGER `trg_like_ai`
AFTER INSERT ON `like_record`
FOR EACH ROW
BEGIN
  IF NEW.`guide_id` IS NOT NULL THEN
    UPDATE `guide`
    SET `like_count` = `like_count` + 1
    WHERE `id` = NEW.`guide_id`;
  ELSE
    UPDATE `resource`
    SET `like_count` = `like_count` + 1
    WHERE `id` = NEW.`resource_id`;
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_like_ad`$$
CREATE TRIGGER `trg_like_ad`
AFTER DELETE ON `like_record`
FOR EACH ROW
BEGIN
  IF OLD.`guide_id` IS NOT NULL THEN
    UPDATE `guide`
    SET `like_count` = IF(`like_count` > 0, `like_count` - 1, 0)
    WHERE `id` = OLD.`guide_id`;
  ELSE
    UPDATE `resource`
    SET `like_count` = IF(`like_count` > 0, `like_count` - 1, 0)
    WHERE `id` = OLD.`resource_id`;
  END IF;
END$$

-- ---------- 评论计数 ----------
DROP TRIGGER IF EXISTS `trg_comment_ai`$$
CREATE TRIGGER `trg_comment_ai`
AFTER INSERT ON `comment`
FOR EACH ROW
BEGIN
  IF NEW.`guide_id` IS NOT NULL THEN
    UPDATE `guide`
    SET `comment_count` = `comment_count` + 1
    WHERE `id` = NEW.`guide_id`;
  ELSE
    UPDATE `resource`
    SET `comment_count` = `comment_count` + 1
    WHERE `id` = NEW.`resource_id`;
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_comment_ad`$$
CREATE TRIGGER `trg_comment_ad`
AFTER DELETE ON `comment`
FOR EACH ROW
BEGIN
  IF OLD.`guide_id` IS NOT NULL THEN
    UPDATE `guide`
    SET `comment_count` = IF(`comment_count` > 0, `comment_count` - 1, 0)
    WHERE `id` = OLD.`guide_id`;
  ELSE
    UPDATE `resource`
    SET `comment_count` = IF(`comment_count` > 0, `comment_count` - 1, 0)
    WHERE `id` = OLD.`resource_id`;
  END IF;
END$$

-- ---------- 收藏计数 ----------
DROP TRIGGER IF EXISTS `trg_collect_ai`$$
CREATE TRIGGER `trg_collect_ai`
AFTER INSERT ON `guide_collect`
FOR EACH ROW
BEGIN
  UPDATE `guide`
  SET `collect_count` = `collect_count` + 1
  WHERE `id` = NEW.`guide_id`;
END$$

DROP TRIGGER IF EXISTS `trg_collect_ad`$$
CREATE TRIGGER `trg_collect_ad`
AFTER DELETE ON `guide_collect`
FOR EACH ROW
BEGIN
  UPDATE `guide`
  SET `collect_count` = IF(`collect_count` > 0, `collect_count` - 1, 0)
  WHERE `id` = OLD.`guide_id`;
END$$

DELIMITER ;

SELECT '04_triggers.sql 执行完成：6 个计数触发器' AS `result`;
