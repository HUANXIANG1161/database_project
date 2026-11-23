-- ============================================================
-- 旅行信息分享平台 · 存储过程
-- 文件: sql/03_procedures.sql
-- 运行身份: MySQL root（先执行 01_schema.sql、02_views.sql）
-- 运行示例:
--   "D:\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 < sql\03_procedures.sql
--
-- 说明:
--   1. sp_search_guide: 攻略组合筛选 + 排序 + 分页。
--   2. sp_review_join: 队长审核入队申请（事务 + 行锁 + 通知）。
--   3. 两个过程均使用 SQL SECURITY INVOKER，按调用者权限执行。
-- ============================================================

USE `travel_share`;

DROP PROCEDURE IF EXISTS `sp_search_guide`;

DELIMITER $$

CREATE PROCEDURE `sp_search_guide`(
  IN p_keyword     VARCHAR(100),
  IN p_destination VARCHAR(100),
  IN p_season      VARCHAR(20),
  IN p_tag         VARCHAR(30),
  IN p_sort        VARCHAR(20),
  IN p_limit       INT,
  IN p_offset      INT
)
COMMENT '攻略搜索：关键词/目的地/季节/标签，支持最新/最热/最多收藏/最多评论排序'
SQL SECURITY INVOKER
BEGIN
  DECLARE v_sort VARCHAR(20) DEFAULT 'new';

  IF p_sort IS NOT NULL AND p_sort <> '' THEN
    SET v_sort = LOWER(p_sort);
  END IF;

  -- 分页参数兜底，防止传入负数或过大值
  IF p_limit IS NULL OR p_limit < 1 THEN
    SET p_limit = 10;
  END IF;
  IF p_limit > 50 THEN
    SET p_limit = 50;
  END IF;
  IF p_offset IS NULL OR p_offset < 0 THEN
    SET p_offset = 0;
  END IF;

  SELECT *
  FROM `v_guide_card`
  WHERE `status` = 'published'
    AND (
      p_keyword IS NULL OR p_keyword = ''
      OR `title` LIKE CONCAT('%', p_keyword, '%')
      OR `summary` LIKE CONCAT('%', p_keyword, '%')
      OR `destination` LIKE CONCAT('%', p_keyword, '%')
    )
    AND (
      p_destination IS NULL OR p_destination = ''
      OR `destination` LIKE CONCAT('%', p_destination, '%')
    )
    AND (
      p_season IS NULL OR p_season = ''
      OR `season` = p_season
      OR `season` = 'all'
    )
    AND (
      p_tag IS NULL OR p_tag = ''
      OR FIND_IN_SET(p_tag, `tags`) > 0
    )
  ORDER BY
    CASE v_sort
      WHEN 'hot'     THEN `like_count`
      WHEN 'collect' THEN `collect_count`
      WHEN 'comment' THEN `comment_count`
      ELSE 0
    END DESC,
    `created_at` DESC
  LIMIT p_limit OFFSET p_offset;
END$$

DELIMITER ;

DROP PROCEDURE IF EXISTS `sp_review_join`;

DELIMITER $$

CREATE PROCEDURE `sp_review_join`(
  IN  p_team_id     INT UNSIGNED,
  IN  p_user_id     INT UNSIGNED,
  IN  p_operator_id INT UNSIGNED,
  IN  p_action      VARCHAR(10),
  OUT p_code        INT,
  OUT p_message     VARCHAR(100)
)
COMMENT '队长审核入队申请：approve/reject，事务内更新成员、队伍人数并发送通知'
SQL SECURITY INVOKER
BEGIN
  DECLARE v_leader_id       INT UNSIGNED DEFAULT NULL;
  DECLARE v_team_name       VARCHAR(100) DEFAULT NULL;
  DECLARE v_team_status     VARCHAR(20)  DEFAULT NULL;
  DECLARE v_max_members     TINYINT UNSIGNED DEFAULT NULL;
  DECLARE v_current_members TINYINT UNSIGNED DEFAULT NULL;
  DECLARE v_member_status   VARCHAR(20)  DEFAULT NULL;
  DECLARE v_action          VARCHAR(10)  DEFAULT '';

  -- 任何 SQL 异常都回滚，避免“成员已通过但人数没加”的半更新
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_code = -1;
    SET p_message = '数据库异常，操作已回滚';
  END;

  SET p_code = 0;
  SET p_message = 'ok';

  IF p_team_id IS NULL OR p_user_id IS NULL OR p_operator_id IS NULL THEN
    SET p_code = 400;
    SET p_message = '参数不完整';
  ELSE
    SET v_action = LOWER(COALESCE(p_action, ''));

    IF v_action NOT IN ('approve', 'reject') THEN
      SET p_code = 400;
      SET p_message = '非法操作类型';
    ELSE
      START TRANSACTION;

      -- 锁定队伍行：同一队伍的审核串行化，防止超员
      SELECT `leader_id`, `name`, `status`, `max_members`, `current_members`
        INTO v_leader_id, v_team_name, v_team_status, v_max_members, v_current_members
      FROM `team`
      WHERE `id` = p_team_id
      FOR UPDATE;

      IF v_leader_id IS NULL THEN
        SET p_code = 404;
        SET p_message = '队伍不存在';
        ROLLBACK;
      ELSEIF v_leader_id <> p_operator_id THEN
        SET p_code = 403;
        SET p_message = '只有队长可以审核';
        ROLLBACK;
      ELSEIF p_user_id = v_leader_id THEN
        SET p_code = 409;
        SET p_message = '队长无需申请入队';
        ROLLBACK;
      ELSE
        -- 锁定申请行，防止重复审核
        SELECT `status`
          INTO v_member_status
        FROM `team_member`
        WHERE `team_id` = p_team_id AND `user_id` = p_user_id
        FOR UPDATE;

        IF v_member_status IS NULL OR v_member_status <> 'pending' THEN
          SET p_code = 409;
          SET p_message = '申请不存在或已处理';
          ROLLBACK;
        ELSEIF v_action = 'approve' THEN
          IF v_team_status <> 'recruiting' OR v_current_members >= v_max_members THEN
            SET p_code = 409;
            SET p_message = '队伍已满或不在招募中';
            ROLLBACK;
          ELSE
            UPDATE `team_member`
            SET `status` = 'approved', `joined_at` = NOW()
            WHERE `team_id` = p_team_id AND `user_id` = p_user_id;

            -- 人数 +1；达到上限时状态改为 full
            UPDATE `team`
            SET `current_members` = v_current_members + 1,
                `status` = IF(v_current_members + 1 >= v_max_members, 'full', `status`)
            WHERE `id` = p_team_id;

            INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`)
            VALUES (
              p_user_id,
              'team_approve',
              '入队申请已通过',
              CONCAT('你已加入队伍：', COALESCE(v_team_name, '')),
              CONCAT('/team.php?id=', p_team_id)
            );

            SET p_code = 0;
            SET p_message = '已通过';
            COMMIT;
          END IF;
        ELSE
          UPDATE `team_member`
          SET `status` = 'rejected'
          WHERE `team_id` = p_team_id AND `user_id` = p_user_id;

          INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`)
          VALUES (
            p_user_id,
            'team_reject',
            '入队申请未通过',
            CONCAT('队伍“', COALESCE(v_team_name, ''), '”拒绝了你的申请'),
            CONCAT('/team_detail.php?id=', p_team_id)
          );

          SET p_code = 0;
          SET p_message = '已拒绝';
          COMMIT;
        END IF;
      END IF;
    END IF;
  END IF;
END$$

DELIMITER ;

SELECT '03_procedures.sql 执行完成：sp_search_guide / sp_review_join' AS `result`;
