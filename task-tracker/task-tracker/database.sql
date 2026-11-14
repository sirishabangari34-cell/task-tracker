-- ============================================================
--  TaskFlow — Task Tracker  |  MySQL database export
--  Import with phpMyAdmin (Import tab) or:  mysql -u root < database.sql
--  Creates database `task_tracker`, tables `users` and `tasks`,
--  and a few sample rows for testing.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `task_tracker`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `task_tracker`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
--  users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,            -- password_hash() output, never plain text
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  tasks  (each task belongs to exactly one user)
-- ------------------------------------------------------------
CREATE TABLE `tasks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `status`      ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `priority`    ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `due_date`    DATE NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_user_status`  (`user_id`, `status`),
  KEY `idx_tasks_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_tasks_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Sample users  (both passwords:  Test@1234)
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`) VALUES
  (1, 'Alice Demo', 'alice@example.com', '$2y$10$rQqk2OqtrGiT7euj4p4Kr.NSm8uy38U5vMyW2o1kDD7mS40aD/4kK'),
  (2, 'Bob Demo',   'bob@example.com',   '$2y$10$rQqk2OqtrGiT7euj4p4Kr.NSm8uy38U5vMyW2o1kDD7mS40aD/4kK');

-- ------------------------------------------------------------
--  Sample tasks for Alice (12 -> shows pagination) and Bob (3)
-- ------------------------------------------------------------
INSERT INTO `tasks` (`user_id`, `title`, `description`, `status`, `priority`, `due_date`) VALUES
  (1, 'Design landing page mockup',  'Create a Figma mockup for the new marketing landing page.',       'In Progress', 'High',   DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
  (1, 'Write API documentation',     'Document all REST endpoints with request and response examples.',  'Pending',     'Medium', DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
  (1, 'Fix login redirect bug',      'Users are sent to the wrong page after logging in on mobile.',     'Completed',   'High',   DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
  (1, 'Prepare sprint review slides','Summarise delivered work for Friday''s sprint review.',            'Pending',     'Medium', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
  (1, 'Update team onboarding guide','Refresh screenshots and add the new tooling section.',             'Pending',     'Low',    NULL),
  (1, 'Review pull request #42',     'Check the pagination changes and leave comments.',                 'In Progress', 'Medium', CURDATE()),
  (1, 'Database backup script',      'Automate nightly mysqldump with 7 day retention.',                 'Pending',     'High',   DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
  (1, 'Customer interview notes',    'Transcribe and tag the five interviews from last week.',           'Completed',   'Low',    DATE_SUB(CURDATE(), INTERVAL 4 DAY)),
  (1, 'Plan Q1 roadmap',             'Draft themes, goals and milestones for next quarter.',             'In Progress', 'High',   DATE_ADD(CURDATE(), INTERVAL 10 DAY)),
  (1, 'Renew SSL certificate',       'Certificate expires soon; renew and deploy.',                      'Completed',   'High',   DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
  (1, 'Clean up unused CSS',         NULL,                                                               'Pending',     'Low',    NULL),
  (1, 'Book team offsite venue',     'Shortlist three venues and compare pricing.',                      'Pending',     'Medium', DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
  (2, 'Bob: private grocery list',   'This task belongs to Bob and must never be visible to Alice.',     'Pending',     'Low',    NULL),
  (2, 'Bob: quarterly tax filing',   'Confidential task owned by Bob.',                                  'In Progress', 'High',   DATE_ADD(CURDATE(), INTERVAL 6 DAY)),
  (2, 'Bob: gym schedule',           'Plan the week.',                                                   'Completed',   'Low',    NULL);
