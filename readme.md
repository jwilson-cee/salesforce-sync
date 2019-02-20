# Salesforce Sync

This package is for syncing Salesforce objects with local data. It's an extension of Davis Peixoto's [Laravel 5 Salesforce](https://github.com/davispeixoto/Laravel-5-Salesforce) package.

Works with [Laravel 5.2](https://laravel.com/docs/) and up.

## Installation

This package can be installed via [Composer](http://getcomposer.org) by requiring the
`mncee/salesforce-sync` package in your project's `composer.json`.

```json
{
    "require": {
        "mncee/salesforce-sync": "1.0.*"
    }
}
```

### Laravel 5 Configuration

To use the [Laravel 5 Salesforce](https://github.com/davispeixoto/Laravel-5-Salesforce) package, you must add the service provider and facade alias your Laravel 5 application.

Find the `providers` key and `aliases` key in your `config/app.php` and add these corresponding lines.

```php
    'providers' => array(
        // ...
        Davispeixoto\Laravel5Salesforce\SalesforceServiceProvider::class,
    )
    
    'aliases' => array(
        // ...
        'Salesforce' => Davispeixoto\Laravel5Salesforce\SalesforceFacade::class,
    )
```

Then you must include the following environment variables in the `.env` file:
```
SALESFORCE_USERNAME=#your salesfore username#
SALESFORCE_PASSWORD=#your salesfore password#
SALESFORCE_TOKEN=#your salesfore token#
SALESFORCE_WSDL=#path to the wsdl stored in the storage/app/ directory#
```

Place your [your **Enterprise** WSDL file](https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_quickstart_steps_generate_wsdl.htm) into your app `storage/app/` directory you specified in the `.env` file.

**IMPORTANT:** the PHP Force.com Toolkit for PHP only works with Enterprise WSDL

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
