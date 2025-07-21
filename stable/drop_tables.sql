-- ====================
-- テーブル初期化（DROPは依存関係を逆順に）
-- @usage: `psql -U postgres -d pulllog -f drop_tables.sql`
-- ====================
DROP TABLE IF EXISTS stats_cache      CASCADE;
DROP TABLE IF EXISTS logs             CASCADE;
DROP TABLE IF EXISTS user_sessions    CASCADE;
DROP TABLE IF EXISTS auth_tokens      CASCADE;
DROP TABLE IF EXISTS user_apps        CASCADE;
DROP TABLE IF EXISTS apps             CASCADE;
DROP TABLE IF EXISTS users            CASCADE;
DROP TABLE IF EXISTS plans            CASCADE;
