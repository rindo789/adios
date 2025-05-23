# Hubleto CHANGELOG

## Release 1.10 (not published yet)

  * new property `Controller->$returnType`
  * improved default skin
  * new `Console->createLogger()` and `Auth->createUserModel()` methods
  * various bugfixes
  * updated documentation
  * codebase cleanup

## Release 1.9

  * new `\ADIOS\Core\Loader->urlParamNotEmpty()` method
  * various bugfixes
  * description API is now able to describe inputs
  * new `Form.tsx->renderSubtitle()` method
  * started to use `value objects` (see why: https://stevegrunwell.com/blog/php-value-objects)
  * more consistent error reporting thanks to `Request.alertOnError()`
  * codebase cleanup

## Release v1.7

  * enhanced type safety (thanks to PHPStan)
  * removed obsolete `ADIOS\Core\Loader->db` object (use safer `ADIOS\Core\Loader->pdo` for database operations)
  * protected property `ADIOS\Core\Auth->user` and new getter methods (`getUser`, `getUserId`, `getUserLanguage`, `getUserRoles`)
  * new style of column description using `ADIOS\Core\Db\Column` objects
  * new method `Model->describeInput()` to support concept of descriptions for columns, similar to `describeTable()` and `describeForm()`
  * new classes `\ADIOS\Core\Db\ColumnProperty` and `\ADIOS\Core\Db\ColumnProperty\Autocomplete`
  * type-safe definition of columns in the model:

```php
  'class' => (new \ADIOS\Core\Db\Column\Varchar($this, 'Class'))
    ->setProperty('autocomplete', (new \ADIOS\Core\Db\ColumnProperty\Autocomplete())->setEndpoint('api/classes/get')->setCreatable(true))
```

## Release v1.6

First version in the changelog