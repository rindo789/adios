<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Controllers\Components\Table;

/**
 * @package Components\Controllers\Table
 */
class OnLoadParams extends \ADIOS\Core\Controller {
  public static bool $hideDefaultDesktop = true;

  /**
  * React component take this argument type for displaying in table
  * so in some case replace type for own custom type
  */
  private function getColumnType($columnType): string {
    switch ($columnType) {
      case 'datetime':
      case 'date':
      case 'time': return 'string';
      default: return $columnType;
    }
  }

  public function renderJson() { 
    try {
      $tmpModel = $this->adios->getModel($this->params['model']);

      $tmpColumns = $tmpModel->columns();

      $columns = [];
      foreach ($tmpColumns as $columnName => $column) {
        if ($columnName == 'id') continue;

        $columns[] = [
          'field' => $columnName,
          'headerName' => $column['title'],
          'flex' => 1,
          'type' =>  $this->getColumnType($column['type']),
          'columnType' => $column['type']
        ];
      }

      return [
        'columns' => $columns, 
        'title' => $tmpModel->tableTitle,
        'folderUrl' => $tmpModel->getFolderUrl()
      ];
    } catch (\ADIOS\Core\Exceptions\GeneralException $e) {
      // TODO: Error
    }
  }

}
