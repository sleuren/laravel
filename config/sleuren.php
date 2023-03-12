<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sleuren key
    |--------------------------------------------------------------------------
    |
    | This is your sleuren key which you receive when creating a project
    | Retrieve your key from https://sleuren.com
    |
    */

    'sleuren_key' => env('SLEUREN_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment setting
    |--------------------------------------------------------------------------
    |
    | This setting determines if the exception should be send over or not.
    |
    */

    'environments' => [
        'production'
    ],

    /*
    |--------------------------------------------------------------------------
    | Project version
    |--------------------------------------------------------------------------
    |
    | Set the project version, default: null.
    | For git repository: shell_exec("git log -1 --pretty=format:'%h' --abbrev-commit")
    |
    */
    'project_version' => null,

    /*
    |--------------------------------------------------------------------------
    | Lines near exception
    |--------------------------------------------------------------------------
    |
    | How many lines to show near exception line. The more you specify the bigger
    | the displayed code will be. Max value can be 50, will be defaulted to
    | 12 if higher than 50 automatically.
    |
    */

    'lines_count' => 12,

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicates
    |--------------------------------------------------------------------------
    |
    | Set the sleep time between duplicate exceptions. This value is in seconds, default: 60 seconds (1 minute)
    |
    */

    'sleep' => 60,

    /*
    |--------------------------------------------------------------------------
    | Skip exceptions
    |--------------------------------------------------------------------------
    |
    | List of exceptions to skip sending.
    |
    */

    'except' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Key filtering
    |--------------------------------------------------------------------------
    |
    | Filter out these variables before sending them to Sleuren
    |
    */

    'blacklist' => [
        '*authorization*',
        '*password*',
        '*token*',
        '*auth*',
        '*verification*',
        '*credit_card*',
        'cardToken', // mollie card token
        '*cvv*',
        '*iban*',
        '*name*',
        '*email*'
    ],

    /*
    |--------------------------------------------------------------------------
    | Release git hash
    |--------------------------------------------------------------------------
    |
    |
    */

    'release' => trim(exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD')),

    /*
    |--------------------------------------------------------------------------
    | Verify SSL setting
    |--------------------------------------------------------------------------
    |
    | Enables / disables the SSL verification when sending exceptions to Sleuren
    | Never turn SSL verification off on production instances
    |
    */
    'verify_ssl' => env('VERIFY_SSL', true),
];
