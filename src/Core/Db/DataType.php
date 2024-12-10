<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core\Db;

/**
 * Basic class for definition of an ADIOS data type.
 *
 * @package DataTypes
 */
class DataType {

  public ?\ADIOS\Core\Loader $app = null;

  protected $defaultValue = null;
  public string $translationContext = '';

  public function __construct($app)
  {
    $this->app = $app;
  }

  public function getSqlDefinitions(array $params): string
  {
    $sqlDef = trim($params['rawSqlDefinitions'] ?? '');
    if (empty($sqlDef)) {
      $defVal = $this->getDefaultValue($params);
      $sqlDef = "default ".($defVal === null ? 'null' : "'" . (string) $defVal . "'");
    }
    return $sqlDef;
  }

  /**
   * Returns the SQL-formatted string used in CREATE TABLE queries.
   *
   * @param  string $table_name Deprecated and not used. Name of the table.
   * @param  string $col_name Name of the column to be created.
   * @param  array<string, mixed> $params Parameter of the column, e.g. default value.
   * @return string
   */
  public function sqlCreateString($table_name, $col_name, $params = [])
  {
  }
  
  /**
   * Returns the SQL-formatted string used in INSERT or UPDATE queries.
   *
   * @param  string $table_name Deprecated and not used. Name of the table.
   * @param  string $col_name Name of the column to be updated.
   * @param  mixed $value Value to be inserted or updated.
   * @param  array<string, mixed> $params Parameter of the column.
   * @return void
   */
  public function sqlValueString($table_name, $col_name, $value, $params = [])
  {
  }
  
  /**
   * Returns the HTML-formatted string of the given value.
   * Used in Components/Table element to format cells of the table.
   *
   * @param  mixed $value Value to be formatted.
   * @param  mixed $params Configuration of the HTML output (e.g. format of date string).
   * @return string HTML-formatted value.
   */
  public function toHtml($value, $params = [])
  {
    return hsc($value);
  }

  /**
   * Returns the HTML-formatted string of the input for this data type.
   * Used in Core/Input view to to render inputs.
   *
   * @param  mixed $params Input parameters.
   * @return string HTML-formatted input.
   */
  public function getInputHtml(array $params = []): ?string
  {
    return NULL;
  }

  /**
   * Returns the CSV-formatted string of the given value.
   * Used in Components/Table element for CSV exports.
   *
   * @param  mixed $value Value to be formatted.
   * @param  mixed $params Configuration of the HTML output (e.g. format of date string).
   * @return string CSV-formatted value.
   */
  public function toCsv($value, $params = [])
  {
    return $value;
  }

  public function translate(string $string, array $vars = []): string
  {
    return $this->app->translate($string, $vars, $this->translationContext);
  }

  public function fromString(?string $value)
  {
    return $value;
  }

  /**
   * Performs custom post-processing of a column definition.
   * Useful, e.g., when the column definition is generated by the prototype builder
   * from the YAML file.
   *
   * @param  array $colDef Original column definition
   * @return array Post-processed column definition
   */
  public function columnDefinitionPostProcess(array $colDef): array
  {
    return $colDef;
  }

  public function normalize(\ADIOS\Core\Model $model, string $colName, $value, $colDefinition)
  {
    return $value;
  }
  
  public function getNullValue(\ADIOS\Core\Model $model, string $colName)
  {
    return null;
  }
  
  public function validate(\ADIOS\Core\Model $model, $value): bool
  {
    return TRUE;
  }
  
  public function getDefaultValue(array $params) {
    return $params['defaultValue'] ?? $this->defaultValue;
  }
}

