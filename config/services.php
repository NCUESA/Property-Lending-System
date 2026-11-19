<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * NCUESA SSO（Keycloak，OpenID Connect）。金鑰放在 .env。
     * 端點：{base_url}/realms/{realm}/protocol/openid-connect/{auth|token|userinfo|logout}
     */
    'ncuesa_sso' => [
        'base_url' => env('NCUESA_SSO_URL'),          // 例：https://portal.ncuesa.org.tw
        'realm' => env('NCUESA_SSO_REALM', 'NCUESA'), // 有分大小寫
        'client_id' => env('NCUESA_SSO_CLIENT_ID'),
        'client_secret' => env('NCUESA_SSO_CLIENT_SECRET'),
        'redirect' => env('NCUESA_SSO_REDIRECT_URI', '/auth/sso/callback'),
        'scopes' => array_values(array_filter(explode(' ', (string) (env('NCUESA_SSO_SCOPES') ?: 'openid profile email')))),
        // 學號放在哪個 claim；Keycloak 預設沒有學號，留空就不帶入
        'student_id_claim' => env('NCUESA_SSO_STUDENT_ID_CLAIM'),
    ],

];
