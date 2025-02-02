<?php

namespace ADIOS\Core\Db\Column;

class DateTime extends \ADIOS\Core\Db\Column
{

  protected string $type = 'datetime';
  protected string $sqlDataType = 'datetime';

  public function normalize(mixed $value): mixed
  {
    return strtotime((string) $value) < 1000 ? null : $value;
  }

  public function sqlIndexString(string $table, string $columnName): string
  {
    return "index `{$columnName}` (`{$columnName}`)";
  }

}