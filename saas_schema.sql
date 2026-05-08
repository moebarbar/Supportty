-- Supportty SaaS Database Schema
-- This file creates all tables required by the account/ (SaaS cloud) layer.
-- Safe to re-run: all statements use CREATE TABLE IF NOT EXISTS.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Main cloud settings (plans, white-label, UI prefs, etc.)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value` longtext COLLATE utf8mb4_unicode_ci,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Cloud (SaaS) accounts / subscribers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `membership` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `membership_expiration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
    `token` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `last_activity` datetime DEFAULT NULL,
    `creation_time` datetime DEFAULT NULL,
    `credits` float DEFAULT 0.05,
    `extra` text COLLATE utf8mb4_unicode_ci,
    `email_confirmed` tinyint(1) DEFAULT 0,
    `phone_confirmed` tinyint(1) DEFAULT 0,
    `customer_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    UNIQUE KEY `token` (`token`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Per-user metadata (key-value store per cloud account)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users_data` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value` text COLLATE utf8mb4_unicode_ci,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `slug` (`slug`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Support agents linked to cloud accounts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agents` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`),
    KEY `admin_id` (`admin_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Slack integration tokens per cloud account
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `slack` (
    `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `team_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`token`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Facebook Messenger integration tokens
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messenger` (
    `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `page_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`token`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- WhatsApp integration tokens
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp` (
    `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`token`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
