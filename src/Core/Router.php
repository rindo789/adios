<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core;

class Router {
  public ?\ADIOS\Core\Loader $adios = null;

  public $routing = [];
  
  public function __construct(\ADIOS\Core\Loader $adios) {
    $this->adios = $adios;
  }

  public function setRouting($routing) {
    if (is_array($routing)) {
      $this->routing = $routing;
    }
  }

  public function addRouting($routing) {
    if (is_array($routing)) {
      $this->routing = array_merge($this->routing, $routing);
    }
  }

  public function replaceRouteVariables($routeParams, $variables) {
    if (is_array($routeParams)) {
      foreach ($routeParams as $paramName => $paramValue) {

        if (is_array($paramValue)) {
          $routeParams[$paramName] = $this->replaceRouteVariables($paramValue, $variables);
        } else {
          foreach ($variables as $k2 => $v2) {
            $routeParams[$paramName] = str_replace('$'.$k2, $v2, $routeParams[$paramName]);
          }
        }
      }
    }

    return $routeParams;
  }

  public function applyRouting(string $route, array $params): array {
    $controller = "";
    $permission = "";

    foreach ($this->routing as $routePattern => $tmpRoute) {
      if (preg_match($routePattern.'i', $route, $m)) {

        $controller = $tmpRoute['controller'];
        $permission = $tmpRoute['permission'] ?? '';
        $tmpRoute['params'] = $this->replaceRouteVariables($tmpRoute['params'], $m);

        foreach ($tmpRoute['params'] as $k => $v) {
          $params[$k] = $v;
        }
      }
    }

    if (empty($controller)) {
      throw new \ADIOS\Core\Exceptions\ControllerNotFound;
    }
    

    if (empty($permission)) {
      $permission = $controller;
    }

    return [$controller, $permission, $params];
  }

  public function checkPermission(string $permission) {
    if (
      !empty($permission)
      && !$this->adios->permissions->has($permission)
    ) {
      throw new \ADIOS\Core\Exceptions\NotEnoughPermissionsException("Not enough permissions ({$permission}).");
    }
  }

}