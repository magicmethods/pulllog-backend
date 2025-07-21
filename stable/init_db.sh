#!/bin/sh
psql -U postgres -d pulllog -f drop_tables.sql
php artisan migrate:fresh --seed
