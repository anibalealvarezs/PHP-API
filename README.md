# Paladins API (PHP)
[![Github tag](https://badgen.net/github/tag/anibalealvarezs/PHP-API)](https://github.com/anibalealvarezs/PHP-API/tags/) [![GitHub license](https://img.shields.io/github/license/anibalealvarezs/PHP-API.svg)](https://github.com/anibalealvarezs/PHP-API/blob/master/LICENSE) [![Github all releases](https://img.shields.io/github/downloads/anibalealvarezs/PHP-API/total.svg)](https://github.com/anibalealvarezs/PHP-API/releases/) [![GitHub latest commit](https://badgen.net/github/last-commit/anibalealvarezs/PHP-API)](https://GitHub.com/anibalealvarezs/PHP-API/commit/) [![Ask Me Anything !](https://img.shields.io/badge/Ask%20me-anything-1abc9c.svg)](https://github.com/anibalealvarezs/anibalealvarezs)

## About

This PHP package came from the need to have a well documented, functional package to help communicate with the Paladins developer API. This package is built using Laravel 5 components and has not been tested outside of that environment.

## Pre-installation

While this branch is not merged with ```master```, you should add the following requirement to your ```composer.json``` and the corresponding repository:

```json
{
  "require": {
    "halfpetal/illuminate-onoi-cache": "^1.0",
    "paladinsdev/php-api": "dev-master"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/anibalealvarezs/PHP-API"
    }
  ]
}
```

## Installation

```sh
$ composer require paladinsdev/php-api
```

## Usage

There are two ways of using the class, however only 1 recommended. The singleton method is used on [Paladins Ninja](https://paladins.ninja) and handles hundreds of thousands a matches a day.

### Recommended (Singleton)

```php
use PaladinsDev\PHP\PaladinsAPI;

class YourClass
{
    public function getSomePlayer()
    {
        $playerDetails = PaladinsAPI::getInstance('YourDevId', 'YourDevAuthKey', $cacheDriver)->getPlayer('SomePlayer');
    }
}
```

#### Using This Method (Laravel)

Laravel makes it very easy to use singletons using the `make` method. You just have to register the singleton in a service provider.

```php
use PaladinsDev\PHP\PaladinsAPI;

$this->app->singleton(
    PaladinsAPI::class,
    function ($app) {
        $cacheDriver = new Cache(app(Repository::class));
        return PaladinsAPI::getInstance(config('app.paladins_devid'), config('app.paladins_authkey'), $cacheDriver);
    }
);
```

This assumes you have two environment variables `PALADINS_DEVID` and `PALADINS_AUTHKEY`.
You can edit this however you like.

Then, this is how you may use the `make` method

```php
use PaladinsDev\PHP\PaladinsAPI;

$paladinsApi = $this->app->make(PaladinsAPI::class);
```

If you can't access the `$app` variable, you can use it by calling `App::make(PaladinsAPI::class)` and then use it just like a normal instance of a class.

```php
use PaladinsDev\PHP\PaladinsAPI;
use Illuminate\Support\Facades\App;

$paladinsApi = App::make(PaladinsAPI::class);
```

### Not Recommended

*This is not recommended as Hi-Rez / Evil Mojo only allows so many sessions a day and the more sessions you create, the quicker you'll get to reaching that limit. Caching **should** be able to handle this method...but it's still not recommended.*

```php
<?php

use PaladinsDev\PHP\PaladinsAPI;
 
class YourClass
{
	private $api;

	public function __construct()
	{
		$this->api = new PaladinsAPI('YourDevId', 'YourDevAuthKey', $cacheDriver);
	}

	public function getSomePlayer()
	{
		$playerDetails = $this->api->getPlayer('SomePlayer');
	}
}
```
## Cache Driver
To uncouple the Laravel/Iluminate framework from the package, we've taken a page from TeamReflex's book and integrated [Onoi Cache](https://packagist.org/packages/onoi/cache).

You can pass the driver as the 3rd parameter of the constructor or `getInstance` method.

### Illuminate Driver
#### Install
```
composer require halfpetal/illuminate-onoi-cache
```

#### Usage
```php
use Halfpetal\Onoi\Illuminate\Cache;
use Illuminate\Cache\Repository;

$cacheDriver = new Cache(app(Repository::class));
```
