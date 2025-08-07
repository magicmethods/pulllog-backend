## Mock:hooks

モックでのエンドポイント処理のフロントエンド（Nuxtアプリ）と商用バックエンド（Laravelアプリ）のマッピングです。

- `authorization.php`
  -> `App/Http/Middleware/AuthApiKey`
  -> `App/Http/Middleware/AuthCsrfToken`
- `delete_apps.php`
  [F] `endpoints.apps.delete`
  -> `App/Http/Controllers/Api/Apps/AppsController@deleteApp`
- **delete_log_daily (None in mock)**
  [F] `endpoints.logs.delete`
  -> `App/Http/Controllers/Api/Logs/DailyLogController@destroy`
- **delete_user(None in mock)**
  [F] 未
  -> `App/Http/Controllers/Api/User/ProfileController@delete`
- `get_apps.php`
  [F] `endpoints.apps.list`
  -> `App/Http/Controllers/Api/Apps/AppsController@getAppList`
  [F] `endpoints.apps.detail`
  -> `App/Http/Controllers/Api/Apps/AppsController@getAppData`
- `get_logs_daily.php`
  [F] `endpoints.logs.daily`
  -> `App/Http/Controllers/Api/Logs/DailyLogController@show`
- `get_logs.php`
  [F] `endpoints.logs.list`
  -> `App/Http/Controllers/Api/Logs/LogsController@index`
- `get_stats.php`
  [F] `endpoints.stats.list`
  -> `App/Http/Controllers/Api/Stats/StatsController@getAppStats`
- **get_user (None in mock)**
  [F] `endpoints.user.profile`
  -> `App/Http/Controllers/Api/User/ProfileController@userGet`
- `post_apps.php`
  [F] `endpoints.apps.create`
  -> `App/Http/Controllers/Api/Apps/AppsController@registerApp`
- **post_apps_image (None in mock)**
  **[F] `endpoints.apps.image`** (定義だけある)
  -> -
- `post_auth_autologin.php`
  [F] `endpoints.auth.autoLogin`
  -> `App/Http/Controllers/Api/Auth/LoginController@autologin`
- `post_auth_login.php`
  [F] `endpoints.auth.login`
  -> `App/Http/Controllers/Api/Auth/LoginController@login`
- `post_auth_logout.php`
  [F] `endpoints.auth.logout`
  -> `App/Http/Controllers/Api/Auth/LoginController@logout`
- `post_auth_password.php`
  [F] `endpoints.auth.password`
  -> `App/Http/Controllers/Api/Auth/PasswordController@requestReset`
- `post_auth_register.php`
  [F] `endpoints.auth.register`
  -> `App/Http/Controllers/Api/Auth/RegisterController@register`
- `post_auth_verify.php`
  [F] `endpoints.auth.verify`
  -> `App/Http/Controllers/Api/Auth/RegisterController@verifyEmail`
- `post_logs_daily.php`
  [F] `endpoints.logs.create`
  -> `App/Http/Controllers/Api/Logs/DailyLogController@insert`
- `post_logs_import.php`
  [F] `endpoints.logs.import`
  -> `App/Http/Controllers/Api/Logs/LogImportController@import`
- `post_user_avatar.php`
  [F] `endpoints.user.avatar` (APIプロキシで `endpoints.user.update` から分岐)
  -> `App/Http/Controllers/Api/User/ProfileController@avatar`
- `post_user_profile.php`
  [F] `endpoints.user.create`
  -> `App/Http/Controllers/Api/User/ProfileController@`  未
- `put_apps.php`
  [F] `endpoints.apps.update`
  -> `App/Http/Controllers/Api/Apps/AppsController@updateApp`
- `put_auth_password.php`
  [F] `endpoints.auth.updatePassword`
  -> `App/Http/Controllers/Api/Auth/PasswordController@reset`
- `put_logs_daily.php`
  [F] `endpoints.logs.update`
  -> `App/Http/Controllers/Api/Logs/DailyLogController@update`
- `put_user_update.php`
  [F] `endpoints.user.update`
  -> `App/Http/Controllers/Api/User/ProfileController@update`

※ [F] はフロントエンド（Nuxtアプリ）側でのエンドポイント定義
※ 最終更新日: 2025-08-07
