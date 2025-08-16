-- ====================
-- テーブル初期化（DROPは依存関係を逆順に）
-- @usage: `psql -U postgres -d pulllog -f drop_tables.sql`
-- ====================
DROP TABLE IF EXISTS social_accounts  CASCADE;
DROP TABLE IF EXISTS stats_cache      CASCADE;
DROP TABLE IF EXISTS 
     logs_p0, logs_p1, logs_p2, logs_p3,
     logs_p4, logs_p5, logs_p6, logs_p7,
     logs_p8, logs_p9, logs           CASCADE;
DROP TABLE IF EXISTS user_sessions    CASCADE;
DROP TABLE IF EXISTS auth_tokens      CASCADE;
DROP TABLE IF EXISTS user_apps        CASCADE;
DROP TABLE IF EXISTS apps             CASCADE;
DROP TABLE IF EXISTS currencies       CASCADE;
DROP TABLE IF EXISTS users            CASCADE;
DROP TABLE IF EXISTS plans            CASCADE;
