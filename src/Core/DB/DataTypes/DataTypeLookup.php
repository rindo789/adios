<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core\DB\DataTypes;

/**
* @package DataTypes
*/
class DataTypeLookup extends \ADIOS\Core\DB\DataType
{

  public function sqlCreateString($table_name, $col_name, $params = [])
  {
    $col_def = $this->app->db->tables[$table_name][$col_name];

    // if (!$col_def['disable_foreign_key']) {
    //   $params['sql_definitions'] = '' != trim((string) $params['sql_definitions']) ? $params['sql_definitions'] : ' NULL ';
    // } else {
    //   $params['sql_definitions'] = '' != trim((string) $params['sql_definitions']) ? $params['sql_definitions'] : ' default 0 ';
    // }

    return "`{$col_name}` ".($params['sql_type'] ?? 'int(8)')." ".($params['sql_definitions'] ?? '')." NULL default 0";
  }

  public function sqlValueString($table, $colName, $value, $params = []) {
    $colDefinition = $this->app->db->tables[$table][$colName];

    $params = \ADIOS\Core\Helper::arrayMergeRecursively($params, [
      'null_value' => false,
      'dumping_data' => false,
      'escape_string' => $this->app->getConfig('m_datapub/escape_string', true),
    ]);

    if ($params['null_value']) {
      return "`{$colName}` = null";
    } else if (is_string($value) && !is_numeric($value)) {
      $model = $this->app->getModel($colDefinition["model"]);

      // $tmp = reset($this->app->db->fetchRaw("
      //   select
      //     id,
      //     " . $model->lookupSqlValue("t") . " as `input_lookup_value`
      //   from `" . $model->getFullTableSqlName() . "` t
      //   having `input_lookup_value` = '" . $this->app->db->escape($value) . "'
      // "));

      $tmp = reset($this->app->db->select($model)
        ->columns([
          [ 'id', 'id' ],
          [ $model->lookupSqlValue(), 'input_lookup_value' ]
        ])
        ->having([
          ['input_lookup_value', '=', $value]
        ])
        ->fetch()
      );

      $id = (int) $tmp['id'];

      return "`{$colName}` = ".($id == 0 ? "null" : $id);
    } else {
      $value = (int) $value;

      if ($colDefinition['disable_foreign_key']) {
        $retval = "`{$colName}` = {$value}";
      } else {
        $retval = "`{$colName}` = ".($value == 0 ? "null" : $value);
      }

      return $retval;
    }
  }

  private function _toHtmlOrCsv($value, $params = [])
  {
    $html = $params['row']["{$params['col_name']}:LOOKUP"] ?? "";
    return $params['export_csv'] ? $html : htmlspecialchars($html);
  }

  public function toHtml($value, $params = [])
  {
    return $this->_toHtmlOrCsv($value, $params);
  }

  public function toCsv($value, $params = [])
  {
    return $this->_toHtmlOrCsv($value, $params);
  }

  public function fromString(?string $value)
  {
    return (int) $value;
  }

  public function validate($value): bool
  {
    return empty($value) || is_numeric($value);
  }
}
