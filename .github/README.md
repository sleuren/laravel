<p align="center">
  <a href="https://sleuren.com/?utm_source=github&utm_medium=logo" target="_blank">
    <img src="https://sleuren.com/logo_positive.svg" alt="Sleuren" width="200" height="84">
  </a>
</p>

# Official sleuren SDK for Laravel

[![Latest Stable Version](https://poser.pugx.org/sleuren/laravel/v/stable)](https://packagist.org/packages/sleuren/laravel)
[![Build Status](https://github.com/sleuren/laravel/workflows/tests/badge.svg)](https://github.com/sleuren/laravel/actions)
[![License](https://poser.pugx.org/sleuren/laravel/license)](https://packagist.org/packages/sleuren/laravel)
[![Total Downloads](https://poser.pugx.org/sleuren/laravel/downloads)](https://packagist.org/packages/sleuren/laravel)
[![Monthly Downloads](https://poser.pugx.org/sleuren/laravel/d/monthly)](https://packagist.org/packages/sleuren/laravel)
[![PHP Version Require](http://poser.pugx.org/sleuren/laravel/require/php)](https://packagist.org/packages/sleuren/laravel)

The Sleuren Laravel error reporter tracks errors and exceptions that happen during the
execution of your application and provides instant notification with detailed
information needed to prioritize, identify, reproduce and fix each issue.

## Getting started

### Install

To install the SDK you will need to be using [Composer]([https://getcomposer.org/)
in your project. To install it please see the [docs](https://getcomposer.org/download/).

This is Laravel SDK, meaning that all the important code regarding error handling lives here.

```bash
composer require sleuren/laravel
```

### Configuration
```php
php artisan vendor:publish --provider="Sleuren\ServiceProvider"
```
And adjust config file (config/sleuren.php) with your desired settings.


Note: by default only production environments will report errors. To modify this edit your Sleuren configuration.

Next is to add the sleuren driver to the logging.php file:

```php
'channels' => [
    // ...
    'sleuren' => [
        'driver' => 'sleuren',
    ],
],
```
After that you have configured the Utah channel you can add it to the stack section:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'sleuren'],
    ],
    //...
],
```

### Usage

All that is left to do is to define env configuration variable.

```env
SLEUREN_KEY=
```
> **SLEUREN_KEY:** is your Project API key which you've received when creating a project.

Get the variables from your [Sleuren dashboard](https://sleuren.com/dashboard).
