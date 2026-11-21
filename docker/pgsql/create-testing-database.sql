-- 只在資料庫 volume 第一次建立時執行。
-- 測試（phpunit.xml）使用獨立的資料庫，RefreshDatabase 不會動到開發資料。
CREATE DATABASE property_testing;
