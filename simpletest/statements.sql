
-- name: create_accounts ; db: mysql
CREATE TABLE `accounts` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL DEFAULT 0,
  `username` VARCHAR( 255 ) NOT NULL DEFAULT '',
  `password` VARCHAR( 255 ) NULL ,
  `age` INT NOT NULL DEFAULT '10',
  `height` DOUBLE NOT NULL DEFAULT '10.0',
  `favorite_word` VARCHAR( 255 ) NULL DEFAULT 'hi',
  `birthday` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'
)

-- name: create_accounts ; db: sqlite
CREATE TABLE `accounts` (
  `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  `profile_id` INTEGER NOT NULL DEFAULT 0,
  `username` VARCHAR( 255 ) NOT NULL DEFAULT '',
  `password` VARCHAR( 255 ) NULL ,
  `age` INT NOT NULL DEFAULT '10',
  `height` DOUBLE NOT NULL DEFAULT '10.0',
  `favorite_word` VARCHAR( 255 ) NULL DEFAULT 'hi',
  `birthday` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'
)

-- name: create_accounts ; db: pgsql
CREATE TABLE accounts (
  id SERIAL PRIMARY KEY,
  profile_id INT NOT NULL DEFAULT 0,
  username VARCHAR( 255 ) NOT NULL DEFAULT '',
  password VARCHAR( 255 ) NULL ,
  age INT NOT NULL DEFAULT '10',
  height DOUBLE PRECISION NOT NULL DEFAULT 10.0,
  favorite_word VARCHAR( 255 ) NULL DEFAULT 'hi',
  birthday VARCHAR( 255 ) NOT NULL DEFAULT '0000-00-00 00:00:00'
)

-- name: create_persons ; db: mysql
CREATE TABLE persons (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `employer_id` int unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `age` int unsigned NOT NULL DEFAULT 0,
  `height` double unsigned NOT NULL DEFAULT 0,
  `favorite_color` varchar(255) NULL,
  `favorite_animaniacs` varchar(255) NOT NULL DEFAULT '',
  `last_happy_moment` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_male` tinyint(1) NOT NULL DEFAULT 0,
  `is_alive` tinyint(1) NULL
) ENGINE = InnoDB

-- name: create_houses ; db: mysql
CREATE TABLE `houses` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `owner_id` INT NOT NULL DEFAULT 0,
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `sqft` INT NOT NULL DEFAULT 0,
  `price` INT NOT NULL DEFAULT 0
)

-- name: create_souls ; db: mysql
CREATE TABLE `souls` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT NOT NULL DEFAULT 0,
  `heaven_bound` tinyint(1) NOT NULL DEFAULT 0
)

-- name: create_companies ; db: mysql
CREATE TABLE `companies` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `shares` INT NOT NULL DEFAULT 0
)

-- name: create_profile ; db: mysql
CREATE TABLE `profile` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `signature` VARCHAR( 255 ) NULL DEFAULT 'donewriting'
)

-- name: create_profile ; db: sqlite
CREATE TABLE `profile` (
  `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  `signature` VARCHAR( 255 ) NULL DEFAULT 'donewriting'
)

-- name: create_profile ; db: pgsql
CREATE TABLE profile (
  id SERIAL PRIMARY KEY,
  signature VARCHAR( 255 ) NULL DEFAULT 'donewriting'
)

-- name: create_store ; db: mysql
CREATE TABLE `store data` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `picture` BLOB
)

-- name: create_store ; db: sqlite
CREATE TABLE `store data` (
  `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  `picture` BLOB
)

-- name: create_store ; db: pgsql
CREATE TABLE "store data" (
  id SERIAL PRIMARY KEY,
  picture BYTEA
)

-- name: mini_table ; db: mysql
CREATE TABLE `accounts` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `myname` varchar(255) not null
)

-- name: create_faketable ; db: mysql
CREATE TABLE `fake%s:s_``"table` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `my.data` VARCHAR( 1024 ) NULL DEFAULT ''
)

-- name: create_faketable ; db: sqlite
CREATE TABLE `fake%s:s_``"table` (
  `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  `my.data` VARCHAR( 1024 ) NULL DEFAULT ''
)

-- name: create_faketable ; db: pgsql
CREATE TABLE "fake%s:s_`""table" (
  "id" SERIAL PRIMARY KEY,
  "my.data" VARCHAR( 1024 ) NULL DEFAULT ''
)