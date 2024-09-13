# Salesforce Sync

This package is for syncing Salesforce objects with local data in a Laravel app.

Works with [Laravel 9, 10, and 11](https://laravel.com/docs/11.x).

[*Use version `^1.0` for Laravel 5*](https://github.com/jwilson-cee/salesforce-sync/tree/v1.0.9)

## Installation

This package can be installed via [Composer](http://getcomposer.org) by requiring the `mncee/salesforce-sync` package.

```shell
composer require mncee/salesforce-sync
```

[*For Laravel 5*](https://github.com/jwilson-cee/salesforce-sync/tree/v1.0.9)

```shell
composer require mncee/salesforce-sync:^1.0
```

## Laravel Configuration

### Environment Variables

Include the following environment variables in the `.env` file:
```
SALESFORCE_USERNAME=#your salesfore username#
SALESFORCE_PASSWORD=#your salesfore password#
SALESFORCE_TOKEN=#your salesfore token#
SALESFORCE_WSDL=#path to the wsdl stored in the storage/app/ directory#
```

Place your [your **Enterprise** WSDL file](https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_quickstart_steps_generate_wsdl.htm) into your app `storage/app/` directory you specified in the `.env` file.

**IMPORTANT:** This package only works with Enterprise WSDL

### Package Discovery

This packages *Service Provider* and *Facade alias* should be auto-discovered when requiring it with `composer`.

But if you need to add them manually, you can do so with the following instructions:

#### For Laravel 11

In the `bootstrap/providers.php` file add `CEE\Salesforce\Laravel\SalesforceServiceProvider::class` to the returned array.

```php
// bootstrap/providers.php

return [
    App\Providers\AppServiceProvider::class,
    CEE\Salesforce\Laravel\SalesforceServiceProvider::class,
];
```

In the `config/app.php` file add these corresponding lines to the returned array.

```php
// config/app.php

return [
    
    //...
    
    'aliases' => Facade::defaultAliases()->merge([
        'Salesforce' => \CEE\Salesforce\Laravel\Facades\Salesforce::class,
    ])->toArray(),
]
```

#### For Laravel 9 and 10

Find the `providers` key and `aliases` key in your `config/app.php` and add these corresponding lines to the returned array.

```php
// config/app.php

return [
    
    //...
    
    'providers' => [
        // ...
        CEE\Salesforce\Laravel\SalesforceServiceProvider::class,
    ],
    
    'aliases' => [
        // ...
        'Salesforce' => \CEE\Salesforce\Laravel\Facades\Salesforce::class,
    ],
    
];
```

## The SyncObject Class
This is the class to use for syncing local data with remote Salesforce objects.

### Sub-class usage
Classes that inherit this class can perform functions for syncing (pushing and pulling) with a remote Salesforce object.

```PHP
class Contact extends SyncObject
{
    public $objectName = 'Contact'; // Saleforce Object Name
    ...
}

$salesforceContact = new Contact();
$salesforceContact->push();
$salesforceContact->pull();
```

Functions need to be defined for pushing and pulling Salesforce object fields, and must use this naming convention:
```PHP
public function push_<Salesforce field name>()
public function pull_<Salesforce field name>($value)
```

The `push_...()` functions should return a value that is to be pushed to the corresponding `<Salesforce field name>` of the remote Salesforce object.
```PHP
    public function push_FirstName() {
        return DB::table('contact')->where('id', 1)->value('first_name');
    }
```

The `pull_...($value)` functions will have an argument containing the value corresponding to the `<Salesforce field name>` of the remote Salesforce object that can be used to update local data.
```PHP
    public function pull_FirstName($firstName) {
        DB::table('contact')->where('id', 1)->update(['first_name' => $firstName]);
    }
```

It is not required to have both a `push_...()` and a `pull_...()` function for a given Salesforce field. Either or both can be used according to what is needed for syncing in either direction.

### Static `objectName()` function and chaining usage

This class can also be used on it's own using the chaining functions:
```PHP
SyncObject::objectName('Contact')
	->id('00A10000001aBCde')
	->pushFields(['FirstName' => 'John', 'LastName' => 'Doe'])
	->push();
	
$salesforceContact = SyncObject::objectName('Contact')
	->id('00A10000001aBCde')
	->pullFields(['FirstName', 'LastName'])
	->pull();
```
