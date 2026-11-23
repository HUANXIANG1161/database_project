-- ============================================================
-- 旅行信息分享平台 · 演示种子数据
-- 文件: sql/05_seed.sql
-- 运行身份: MySQL root（先执行 00~04）
-- 说明:
--   1. 清空 18 张表后写入一套可演示的数据。
--   2. 所有账号密码均为 123456，哈希由 PHP password_hash() 生成。
--   3. 攻略/资源的计数列先写 0，点赞/评论/收藏由触发器自动累加。
--   4. 图片/文件地址为演示占位路径，后续在 public/uploads/demo 下放占位图。
-- ============================================================

USE `travel_share`;
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `like_record`;
DELETE FROM `comment`;
DELETE FROM `resource`;
DELETE FROM `route_item`;
DELETE FROM `route_version`;
DELETE FROM `route`;
DELETE FROM `team_notice`;
DELETE FROM `team_member`;
DELETE FROM `team`;
DELETE FROM `trip`;
DELETE FROM `guide_collect`;
DELETE FROM `guide_tag`;
DELETE FROM `guide`;
DELETE FROM `tag`;
DELETE FROM `notification`;
DELETE FROM `message`;
DELETE FROM `friendship`;
DELETE FROM `user`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- 用户 ----------
INSERT INTO `user`
  (`id`, `username`, `password_hash`, `nickname`, `avatar`, `gender`, `email`, `phone`, `intro`, `role`, `status`)
VALUES
  (1, 'alice', '$2y$12$7H8CdMSi9X3lZF1AFWtldeH/UKH6QUBRE4Syri08NdbIqffLlr6Bi', '阿黎', NULL, 'female', 'alice@example.com', '13800000001', '喜欢海岛和老街，慢慢走。', 'user', 'active'),
  (2, 'bob',   '$2y$12$Z5QA1DbikY0VtEd3yz4e5ONiipjHQVoCfIHvtn3UOpW.iWdPVggj6', '阿波', NULL, 'male',   'bob@example.com',   '13800000002', '自驾爱好者，川西常客。', 'user', 'active'),
  (3, 'carol', '$2y$12$OdpzRHK56x91fbFTpbK0w.2fs4f/uP8ltnIq1v6PO1xkdGloP04QO', '小卡', NULL, 'female', 'carol@example.com', '13800000003', '带娃旅行，博物馆控。', 'user', 'active'),
  (4, 'dave',  '$2y$12$cQw62rP3h2HPwtG5QJznaO0yj3R/fJnsuqNu7v85VTR5AdJRgrXse', '大伟', NULL, 'male',   'dave@example.com',  '13800000004', '摄影和视频，记录路上。', 'user', 'active'),
  (5, 'admin', '$2y$12$jQrHH2lpWrn3IRUU7oPvIuDZgSgRmCLlmjwbLCdq7zEG0IOYzJp0.', '管理员', NULL, 'unknown', 'admin@example.com', NULL, '课程设计后台管理员。', 'admin', 'active');

-- ---------- 好友关系 ----------
INSERT INTO `friendship` (`id`, `user_id`, `friend_id`, `status`) VALUES
  (1, 1, 2, 'accepted'),
  (2, 1, 3, 'pending'),
  (3, 2, 4, 'accepted');

-- ---------- 私聊 ----------
INSERT INTO `message` (`id`, `from_id`, `to_id`, `content`, `msg_type`, `is_read`) VALUES
  (1, 1, 2, '厦门队集合时间定了吗？', 'text', 0),
  (2, 2, 1, '定了，12月5日早上8点机场见。', 'text', 1),
  (3, 2, 4, '川西车辆检查好了吗？', 'text', 0),
  (4, 4, 2, '检查好了，油也加满了。', 'text', 1);

-- ---------- 通知 ----------
INSERT INTO `notification` (`id`, `user_id`, `type`, `title`, `content`, `link`, `is_read`) VALUES
  (1, 2, 'team_apply', '新的入队申请', '阿黎邀请你加入“厦门慢游小队”。', '/team.php?id=1', 0),
  (2, 1, 'friend_request', '新的好友申请', '小卡请求加你为好友。', '/friend.php', 0),
  (3, 4, 'team_approve', '入队申请已通过', '你已加入“川西自驾车队”。', '/team.php?id=2', 1),
  (4, 1, 'comment', '攻略收到新评论', '阿波评论了你的攻略。', '/guide_detail.php?id=1', 0);

-- ---------- 标签 ----------
INSERT INTO `tag` (`id`, `name`, `type`) VALUES
  (1, '海岛', 'theme'),
  (2, '美食', 'theme'),
  (3, '自驾', 'theme'),
  (4, '亲子', 'theme'),
  (5, '摄影', 'style'),
  (6, '春季', 'season'),
  (7, '夏季', 'season'),
  (8, '秋季', 'season'),
  (9, '冬季', 'season'),
  (10, '穷游', 'style');

-- ---------- 攻略 ----------
INSERT INTO `guide`
  (`id`, `author_id`, `title`, `destination`, `cover`, `summary`, `content`, `day_count`, `cost_avg`, `season`, `status`, `created_at`)
VALUES
  (1, 1, '厦门三天两夜：海岛与老街的慢旅行', '厦门', '/uploads/demo/xiamen.svg',
   '鼓浪屿、厦门大学、环岛路和中山路，一条不赶时间的路线。',
   '<p>第一天下午到厦门，先住中山路附近。傍晚去八市吃海鲜，然后逛中山路骑楼。</p><p>第二天一早去鼓浪屿，提前在官方小程序买船票。岛上的小路适合慢慢走，不要排太满。</p><p>第三天去厦门大学和环岛路，下午坐车去集美学村。</p>',
   3, 1200.00, 'summer', 'published', '2026-11-01 10:00:00'),
  (2, 2, '川西小环线自驾：四天看遍雪山草原', '川西', '/uploads/demo/chuanxi.svg',
   '成都出发，雅安、新都桥、塔公、丹巴，四天三晚的自驾路线。',
   '<p>第一天成都取车，走雅安到新都桥，海拔慢慢上升，注意休息。</p><p>第二天新都桥到塔公草原，下午去丹巴。</p><p>第三天丹巴藏寨，第四天返回成都。山路多，尽量白天开车。</p>',
   4, 2200.00, 'autumn', 'published', '2026-11-10 09:30:00'),
  (3, 3, '北京亲子四日游：博物馆与胡同', '北京', '/uploads/demo/beijing.svg',
   '带娃不累的北京路线：故宫、国家博物馆、景山和胡同。',
   '<p>第一天故宫，提前七天预约。从午门进，神武门出，下午景山看全景。</p><p>第二天国家博物馆，重点看古代中国展厅。</p><p>第三天南锣鼓巷和什刹海，坐三轮车逛胡同。</p><p>第四天自由安排，返程。</p>',
   4, 1800.00, 'spring', 'published', '2026-10-20 14:00:00'),
  (4, 1, '周末露营装备清单（草稿）', '杭州', NULL,
   '两个人两天一夜的轻量露营清单，还在补充。',
   '<p>帐篷、防潮垫、睡袋、折叠桌、卡式炉、头灯、驱蚊液。</p>',
   2, 500.00, 'all', 'draft', '2026-11-18 20:00:00'),
  (5, 4, '青岛老城区摄影机位', '青岛', '/uploads/demo/qingdao.svg',
   '信号山、天主教堂、八大关，适合清晨和傍晚的机位。',
   '<p>清晨去信号山拍老城全景，傍晚到八大关拍海边。天主教堂正面人多，可以绕到侧面。</p>',
   1, 300.00, 'summer', 'pending', '2026-11-20 08:00:00');

-- ---------- 攻略-标签 ----------
INSERT INTO `guide_tag` (`guide_id`, `tag_id`) VALUES
  (1, 1), (1, 2), (1, 6), (1, 7),
  (2, 3), (2, 5), (2, 8),
  (3, 4), (3, 2), (3, 6),
  (4, 5), (4, 10),
  (5, 5), (5, 7);

-- ---------- 行程 ----------
INSERT INTO `trip`
  (`id`, `owner_id`, `title`, `destination`, `start_date`, `end_date`, `budget`, `cover`, `description`, `visibility`, `status`)
VALUES
  (1, 1, '厦门三日游', '厦门', '2026-12-05', '2026-12-07', 3600.00, '/uploads/demo/xiamen.svg', '三个人，慢节奏，主要吃和逛。', 'team', 'planning'),
  (2, 2, '川西自驾', '川西', '2026-11-20', '2026-11-23', 8800.00, '/uploads/demo/chuanxi.svg', '两车四人，成都集合。', 'team', 'ongoing'),
  (3, 3, '北京亲子游', '北京', '2026-10-01', '2026-10-04', 7200.00, '/uploads/demo/beijing.svg', '带两个孩子，行程宽松。', 'public', 'finished'),
  (4, 1, '杭州周末露营', '杭州', '2026-12-12', '2026-12-13', 1000.00, NULL, '找个营地过周末。', 'private', 'planning');

-- ---------- 队伍 ----------
INSERT INTO `team`
  (`id`, `trip_id`, `leader_id`, `name`, `max_members`, `current_members`, `description`, `status`)
VALUES
  (1, 1, 1, '厦门慢游小队', 6, 2, '不赶景点，主要吃和逛。', 'recruiting'),
  (2, 2, 2, '川西自驾车队', 4, 2, '两车四人，有山路驾驶经验优先。', 'recruiting'),
  (3, 3, 3, '北京亲子团', 8, 3, '带娃家庭一起走。', 'closed'),
  (4, 4, 1, '露营搭子', 4, 1, '轻量露营，装备可共用。', 'recruiting');

-- ---------- 队伍成员/申请 ----------
INSERT INTO `team_member`
  (`id`, `team_id`, `user_id`, `status`, `apply_reason`, `joined_at`)
VALUES
  (1, 1, 1, 'approved', '队长', '2026-11-01 10:00:00'),
  (2, 1, 3, 'approved', '想带孩子一起，可以帮忙拍照。', '2026-11-02 11:00:00'),
  (3, 1, 2, 'pending', '我会开车，可以负责租车。', NULL),
  (4, 2, 2, 'approved', '队长', '2026-11-10 09:00:00'),
  (5, 2, 4, 'approved', '会拍照和剪视频。', '2026-11-10 10:00:00'),
  (6, 3, 3, 'approved', '队长', '2026-09-20 09:00:00'),
  (7, 3, 1, 'approved', '可以帮忙看孩子。', '2026-09-21 09:00:00'),
  (8, 3, 4, 'approved', '负责拍照。', '2026-09-21 10:00:00'),
  (9, 4, 1, 'approved', '队长', '2026-11-18 20:00:00');

-- ---------- 队伍公告 ----------
INSERT INTO `team_notice` (`id`, `team_id`, `publisher_id`, `title`, `content`) VALUES
  (1, 1, 1, '集合时间', '12月5日早上8点，高崎机场T3出口集合。'),
  (2, 2, 2, '车辆安排', '两辆车，出发前检查胎压、刹车和防冻液。');

-- ---------- 路线 ----------
INSERT INTO `route` (`id`, `trip_id`, `title`, `current_version`, `updated_by`) VALUES
  (1, 1, '厦门三日路线', 1, 1),
  (2, 2, '川西小环线', 1, 2),
  (3, 3, '北京亲子路线', 1, 3),
  (4, 4, '杭州露营路线', 1, 1);

INSERT INTO `route_version` (`route_id`, `version_no`, `editor_id`, `remark`) VALUES
  (1, 1, 1, '初版'),
  (2, 1, 2, '初版'),
  (3, 1, 3, '初版'),
  (4, 1, 1, '初版');

INSERT INTO `route_item`
  (`route_id`, `version_no`, `day_no`, `seq`, `item_type`, `title`, `address`, `start_time`, `end_time`, `cost`, `remark`)
VALUES
  (1, 1, 1, 1, 'spot', '鼓浪屿', '厦门市思明区鼓浪屿', '09:00:00', '12:00:00', 100.00, '提前买船票'),
  (1, 1, 1, 2, 'food', '中山路八市', '厦门市思明区中山路', '18:00:00', '20:00:00', 150.00, '海鲜大排档'),
  (1, 1, 2, 1, 'spot', '厦门大学', '厦门市思明区思明南路422号', '09:00:00', '11:30:00', 0.00, '需要预约'),
  (1, 1, 2, 2, 'spot', '环岛路', '厦门市思明区环岛路', '15:00:00', '17:30:00', 0.00, '骑行'),
  (1, 1, 3, 1, 'spot', '集美学村', '厦门市集美区', '09:00:00', '12:00:00', 0.00, '返程前逛'),
  (2, 1, 1, 1, 'spot', '成都取车', '成都市武侯区', '08:00:00', '09:00:00', 0.00, '检查车辆'),
  (2, 1, 1, 2, 'food', '雅安午餐', '雅安市雨城区', '12:30:00', '13:30:00', 80.00, '雅鱼'),
  (2, 1, 2, 1, 'spot', '新都桥', '甘孜州康定市新都桥镇', '16:00:00', '18:00:00', 0.00, '拍日落'),
  (2, 1, 3, 1, 'spot', '塔公草原', '甘孜州康定市塔公镇', '09:00:00', '12:00:00', 0.00, '海拔较高'),
  (2, 1, 4, 1, 'spot', '丹巴藏寨', '甘孜州丹巴县', '09:00:00', '15:00:00', 0.00, '返程'),
  (3, 1, 1, 1, 'spot', '故宫博物院', '北京市东城区景山前街4号', '08:30:00', '12:30:00', 60.00, '提前预约'),
  (3, 1, 2, 1, 'spot', '中国国家博物馆', '北京市东城区东长安街16号', '09:00:00', '12:00:00', 0.00, '免费预约'),
  (3, 1, 3, 1, 'spot', '南锣鼓巷', '北京市东城区南锣鼓巷', '14:00:00', '17:00:00', 0.00, '胡同'),
  (4, 1, 1, 1, 'spot', '露营地', '杭州市余杭区', '14:00:00', '18:00:00', 200.00, '确认营地预约');

-- ---------- 资源 ----------
INSERT INTO `resource`
  (`id`, `uploader_id`, `trip_id`, `type`, `title`, `description`, `file_url`, `cover`, `location`, `status`, `created_at`)
VALUES
  (1, 4, 3, 'photo', '故宫角楼', '傍晚的角楼，光线很好。', '/uploads/demo/gugong.svg', '/uploads/demo/gugong.svg', '北京', 'published', '2026-10-05 18:30:00'),
  (2, 3, 3, 'photo', '胡同秋色', '南锣鼓巷附近的一条小巷。', '/uploads/demo/hutong.svg', '/uploads/demo/hutong.svg', '北京', 'published', '2026-10-05 19:00:00'),
  (3, 1, 1, 'doc', '厦门行程清单.pdf', '三天两夜的行程和预算表。', '/uploads/demo/xiamen-plan.pdf', NULL, '厦门', 'published', '2026-11-15 10:00:00'),
  (4, 2, 2, 'video', '川西行车记录', '新都桥到塔公的沿途风景。', '/uploads/demo/chuanxi-drive.mp4', '/uploads/demo/chuanxi.svg', '川西', 'published', '2026-11-21 17:00:00'),
  (5, 4, NULL, 'photo', '海边日落', '待审核的随手拍。', '/uploads/demo/sunset.svg', '/uploads/demo/sunset.svg', '青岛', 'pending', '2026-11-21 18:00:00');

-- ---------- 评论 ----------
INSERT INTO `comment` (`id`, `guide_id`, `resource_id`, `user_id`, `parent_id`, `content`) VALUES
  (1, 1, NULL, 2, NULL, '攻略很实用，已经收藏了。'),
  (2, 1, NULL, 3, NULL, '请问鼓浪屿船票要提前多久订？'),
  (3, 1, NULL, 1, 2, '建议提前一天在官方小程序买，节假日要更早。'),
  (4, 2, NULL, 4, NULL, '这段路十月去景色怎么样？'),
  (5, NULL, 1, 3, NULL, '拍得真好，角楼这个角度很经典。');

-- ---------- 点赞 ----------
INSERT INTO `like_record` (`id`, `user_id`, `guide_id`, `resource_id`) VALUES
  (1, 2, 1, NULL),
  (2, 3, 1, NULL),
  (3, 4, 2, NULL),
  (4, 1, NULL, 1),
  (5, 2, NULL, 1);

-- ---------- 收藏 ----------
INSERT INTO `guide_collect` (`user_id`, `guide_id`) VALUES
  (2, 1),
  (3, 2),
  (1, 3);

-- ---------- 结果校验 ----------
SELECT '05_seed.sql 执行完成' AS `result`;
SELECT `id`, `title`, `like_count`, `comment_count`, `collect_count` FROM `guide` ORDER BY `id`;
SELECT `id`, `title`, `like_count`, `comment_count` FROM `resource` ORDER BY `id`;
