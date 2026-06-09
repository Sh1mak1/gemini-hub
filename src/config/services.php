<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'bot_token' => env('SLACK_BOT_TOKEN'),
        'channels' => [
            'work' => env('SLACK_CHANNEL_WORK'),
            'hobby' => env('SLACK_CHANNEL_HOBBY'),
            'other' => env('SLACK_CHANNEL_OTHER'),
        ],
        'channel_names' => [
            'work' => env('SLACK_CHANNEL_NAME_WORK', 'todo-work'),
            'hobby' => env('SLACK_CHANNEL_NAME_HOBBY', 'todo-hobby'),
            'other' => env('SLACK_CHANNEL_NAME_OTHER'),
        ],
        'today' => [
            'channel_id' => env('SLACK_CHANNEL_TODAY', env('SLACK_CHANNEL_KYOU')),
            'channel_name' => env('SLACK_CHANNEL_NAME_TODAY', env('SLACK_CHANNEL_NAME_KYOU', 'kyou')),
            'post_hour' => (int) env('SLACK_TODAY_POST_HOUR', env('SLACK_KYOU_POST_HOUR', 9)),
        ],
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
