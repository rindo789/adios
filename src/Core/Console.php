<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/


namespace ADIOS\Core;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Debugger console for ADIOS application.
 */
class Console {
  public ?\ADIOS\Core\Loader $app = null;

  public array $loggers = [];
  public array $infos = [];
  public array $warnings = [];
  public array $errors = [];
  
  public bool $cliEchoEnabled = FALSE;

  public int $lastTimestamp = 0;

  public string $logDir = "";
 
  public function __construct($app) {
    $this->app = $app;
    $this->logDir = $this->app->configAsString('logDir');

    $this->initLogger('core');
  }

  public function initLogger(string $loggerName = "") {
    if (!class_exists("\\Monolog\\Logger")) return;

    // inicializacia loggerov
    $this->loggers[$loggerName] = new Logger($loggerName);
    $infoStreamHandler = new RotatingFileHandler("{$this->logDir}/{$loggerName}-info.log", 1000, Logger::INFO);
    $infoStreamHandler->setFilenameFormat('{date}/{filename}', 'Y/m/d');

    $warningStreamHandler = new RotatingFileHandler("{$this->logDir}/{$loggerName}-warning.log", 1000, Logger::WARNING);
    $warningStreamHandler->setFilenameFormat('{date}/{filename}', 'Y/m/d');

    $errorStreamHandler = new RotatingFileHandler("{$this->logDir}/{$loggerName}-error.log", 1000, Logger::ERROR);
    $errorStreamHandler->setFilenameFormat('{date}/{filename}', 'Y/m/d');

    $this->loggers[$loggerName]->pushHandler($infoStreamHandler);
    $this->loggers[$loggerName]->pushHandler($warningStreamHandler);
    $this->loggers[$loggerName]->pushHandler($errorStreamHandler);

  }
  
  public function getLogger($loggerName) {
    if (!isset($this->loggers[$loggerName])) {
      $this->initLogger($loggerName);
    }

    return $this->loggers[$loggerName];
  }
  
  /**
   * Logs a message to the console
   *
   * @param  string $message Message to be logged
   * @return void
   */
  public function log($message, $object = NULL) {
    $this->app->session->push('console', [
      'header' => date('H:i:s'),
      'message' => trim($message.(is_object($object) ? " (".get_class($object).")" : "")),
    ]);
  }

  public function clearLog($logger, $logSeverity) {
    if (!in_array($logSeverity, ["info", "warning", "error"])) {
      $logSeverity = "info";
    }

    $logFile = "{$this->logDir}/".date("Y")."/".date("m")."/".date("d")."/{$logger}-{$logSeverity}.log";
    if (is_file($logFile)) {
      unlink($logFile);
    }
  }

  function timestampMicrosec() {
    list($usec, $sec) = explode(' ', microtime());
    return (float) $usec + (float) $sec;
  }

  public function logTimestamp($message, $logger = "core") {
    if (!$this->app->configAsBool('devel_mode')) return;

    $now = $this->timestampMicrosec();
    if ($this->lastTimestamp == 0) $this->lastTimestamp = $now;
    $this->info($message, ["secFromLast" => $now - $this->lastTimestamp], $logger."-timestamps");
    $this->lastTimestamp = $now;
  }

  public function cliEcho($message, $loggerName, $severity) {
    if ($this->cliEchoEnabled && php_sapi_name() === 'cli') {
      echo date("Y-m-d H:i:s")." {$loggerName}.{$severity} {$message}\n";
    }
  }

  public function info($message, array $context = [], $loggerName = 'core') {
    $this->getLogger($loggerName)->info($message, $context);
    $this->infos[microtime()] = [$message, $context];
  
    $this->cliEcho($message, $loggerName, 'INFO');
  }
  
  public function warning($message, array $context = [], $loggerName = 'core') {
    $this->getLogger($loggerName)->warning($message, $context);
    $this->warnings[microtime()] = [$message, $context];

    $this->cliEcho($message, $loggerName, 'WARNING');
  }
  
  public function error($message, array $context = [], $loggerName = 'core') {
    $this->getLogger($loggerName)->error($message, $context);
    $this->log($message);
    $this->errors[microtime()] = [$message, $context];

    $this->cliEcho($message, $loggerName, 'ERROR');
  }

  public function getInfos() {
    return $this->infos;
  }
  
  public function getWarnings() {
    return $this->warnings;
  }
  
  public function getErrors() {
    return $this->errors;
  }
  
  /**
   * Clears the console
   *
   * @return void
   */
  public function clear() {
    $this->app->session->set('console', []);
  }
  
  /**
   * Returns list of logged messages
   *
   * @return array List of logged messages. Empty array in case of no messages.
   */
  public function getLogs() {
    return $this->app->session->get('console') ?? [];
  }
  
  /**
   * Returns string-formatted content of the console
   *
   * @return string String-formatted content of the console
   */
  public function getContents() {
    $contents = "";
    foreach ($this->getLogs() as $log) {
      $contents .= "{$log['header']}\n{$log['message']}\n\n";
    }
    return $contents;
  }

  public function convertLogsToHtml($logs, $addTimestamps = FALSE) {
    $html = "";
    foreach ($logs as $mictotime => $log) {
      if ($addTimestamps) {
        list($msec, $sec) = explode(" ", $mictotime);
        $html .= date("Y-m-h H:i:s", $sec).".".round($msec*1000)." ";
      }
      $html .= hsc($log[0])." ".hsc($log[1]['exception'])."<br/>";
    }
    return $html;
  }

  public function convertLogsToPlainText($logs, $addTimestamps = FALSE) {
    $html = "";
    foreach ($logs as $mictotime => $log) {
      if ($addTimestamps) {
        list($msec, $sec) = explode(" ", $mictotime);
        $html .= date("Y-m-h H:i:s", $sec).".".round($msec*1000)." ";
      }
      $html .= hsc($log[0])." ".hsc($log[1]['exception'])."\n";
    }
    return $html;
  }

}