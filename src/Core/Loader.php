<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core;

// Autoloader function

spl_autoload_register(function ($class) {
  $class = trim(str_replace("\\", "/", $class), "/");

  if (preg_match('/ADIOS\/([\w\/]+)/', $class, $m)) {
    require_once(__DIR__ . "/../{$m[1]}.php");
  }
});

register_shutdown_function(function() {
  $error = error_get_last();
  if ($error !== null && $error['type'] == E_ERROR) {
    header('HTTP/1.1 400 Bad Request', true, 400);
  }
});

// ADIOS Loader class
#[\AllowDynamicProperties]
class Loader
{
  const ADIOS_MODE_FULL = 1;
  const ADIOS_MODE_LITE = 2;

  public string $gtp = "";
  public string $requestedUri = "";
  public string $controller = "";
  public string $permission = "";
  public string $uid = "";
  public string $route = "";

  public ?\ADIOS\Core\Controller $controllerObject;

  public bool $logged = false;

  protected array $config = [];
  public array $widgets = [];

  public array $widgetsInstalled = [];

  private array $pluginFolders = [];
  private array $pluginObjects = [];
  private array $plugins = [];

  public array $modelObjects = [];
  public array $registeredModels = [];

  public bool $userLogged = false;
  public array $userProfile = [];
  public array $userPasswordReset = [];

  public bool $testMode = false; // Set to TRUE only in DEVELOPMENT. Disables authentication.

  public \ADIOS\Core\Session $session;
  public \ADIOS\Core\Db $db;
  public \ADIOS\Core\Console $console;
  public \ADIOS\Core\Locale $locale;
  public \ADIOS\Core\Router $router;
  public \ADIOS\Core\Email $email;
  public \ADIOS\Core\UserNotifications $userNotifications;
  public \ADIOS\Core\Permissions $permissions;
  public \ADIOS\Core\Test $test;
  public \ADIOS\Core\Web\Loader $web;
  public \Illuminate\Database\Capsule\Manager $eloquent;
  public \ADIOS\Core\Auth $auth;
  public \ADIOS\Core\Translator $translator;

  public \Twig\Environment $twig;

  public string $twigNamespaceCore = 'app';

  public \ADIOS\Core\PDO $pdo;

  public array $assetsUrlMap = [];

  public string $desktopContentController = "";

  public string $widgetsDir = "";

  public string $translationContext = '';

  /** @property array<string, string> */
  protected array $params = [];

  public ?array $uploadedFiles = null;

  public function __construct(array $config = [], int $mode = self::ADIOS_MODE_FULL)
  {

    \ADIOS\Core\Helper::setGlobalApp($this);

    $this->config = $config;

    \ADIOS\Core\Helper::addSpeedLogTag("#1");

    $this->test = $this->getTestProvider();

    $this->widgetsDir = $config['widgetsDir'] ?? "";

    $this->gtp = $this->config['global_table_prefix'] ?? "";
    // $this->requestedController = $_REQUEST['controller'] ?? "";
    $this->params = $this->extractParamsFromRequest();

    if (empty($this->config['dir'])) $this->config['dir'] = "";
    if (empty($this->config['url'])) $this->config['url'] = "";
    if (empty($this->config['rewriteBase'])) $this->config['rewriteBase'] = "";
    if (empty($this->config['appDir'])) $this->config['appDir'] = $this->config['dir'];
    if (empty($this->config['appUrl'])) $this->config['appUrl'] = $this->config['url'];
    if (empty($this->config['accountDir'])) $this->config['accountDir'] = $this->config['dir'];
    if (empty($this->config['accountUrl'])) $this->config['accountUrl'] = $this->config['url'];

    if (empty($this->config['sessionSalt'])) {
      $this->config['sessionSalt'] = rand(100000, 999999);
    }

    $this->config['requestUri'] = $_SERVER['REQUEST_URI'] ?? "";

    // pouziva sa ako vseobecny prefix niektorych session premennych,
    // novy ADIOS ma zatial natvrdo hodnotu, lebo sa sessions riesia cez session name
    if (!defined('_ADIOS_ID')) {
      define(
        '_ADIOS_ID',
        $this->config['sessionSalt']."-".substr(md5($this->config['sessionSalt']), 0, 5)
      );
    }

    // ak requestuje nejaky Asset (css, js, image, font), tak ho vyplujem a skoncim
    if ($this->config['rewriteBase'] == "/") {
      $this->requestedUri = ltrim(parse_url($this->config['requestUri'], PHP_URL_PATH), "/");
    } else {
      $this->requestedUri = str_replace($this->config['rewriteBase'], "",parse_url($this->config['requestUri'], PHP_URL_PATH));
    }

    $this->assetsUrlMap["adios/assets/css/"] = __DIR__."/../Assets/Css/";
    $this->assetsUrlMap["adios/assets/js/"] = __DIR__."/../Assets/Js/";
    $this->assetsUrlMap["adios/assets/images/"] = __DIR__."/../Assets/Images/";
    $this->assetsUrlMap["adios/assets/webfonts/"] = __DIR__."/../Assets/Webfonts/";
    $this->assetsUrlMap["adios/assets/widgets/"] = function ($app, $url) {
      $url = str_replace("adios/assets/widgets/", "", $url);
      preg_match('/(.*?)\/(.+)/', $url, $m);

      $widget = $m[1];
      $asset = $m[2];

      return $app->widgetsDir."/{$widget}/Assets/{$asset}";
    };
    $this->assetsUrlMap["adios/plugins/"] = function ($app, $url) {
      $url = str_replace("adios/plugins/", "", $url);
      preg_match('/(.+?)\/~\/(.+)/', $url, $m);

      $plugin = $m[1];
      $resource = $m[2];

      foreach ($app->pluginFolders as $pluginFolder) {
        $file = "{$pluginFolder}/{$plugin}/{$resource}";
        if (is_file($file)) {
          return $file;
        }
      }
    };

    //////////////////////////////////////////////////
    // inicializacia

    try {

      // inicializacia session managementu
      $this->session = new \ADIOS\Core\Session($this);

      // inicializacia debug konzoly
      $this->console = \ADIOS\Core\Factory::create('Core/Console', [$this]);
      $this->console->clearLog("timestamps", "info");

      // global $gtp; - pouziva sa v basic_functions.php

      $gtp = $this->gtp;

      // nacitanie zakladnych ADIOS lib suborov
      require_once dirname(__FILE__)."/Lib/basic_functions.php";

      \ADIOS\Core\Helper::addSpeedLogTag("#2");

      $this->eloquent = new \Illuminate\Database\Capsule\Manager;

      $dbConnectionConfig = $this->getDefaultConnectionConfig();

      if ($dbConnectionConfig !== null) {
        $this->eloquent->setAsGlobal();
        $this->eloquent->bootEloquent();
        $this->eloquent->addConnection($dbConnectionConfig, 'default');
      }

      $this->pdo = new \ADIOS\Core\PDO($this);

      if ($mode == self::ADIOS_MODE_FULL) {

        $this->initDatabaseConnections();

      }

      \ADIOS\Core\Helper::addSpeedLogTag("#2.1");

      // inicializacia pluginov - aj pre FULL aj pre LITE mod

      $this->onBeforePluginsLoaded();

      foreach ($this->pluginFolders as $pluginFolder) {
        $this->loadAllPlugins($pluginFolder);
      }

      $this->onAfterPluginsLoaded();

      $this->renderAssets();


      if ($mode == self::ADIOS_MODE_FULL) {
        // start session

        session_id();
        session_name(_ADIOS_ID);
        session_start();

        define('_SESSION_ID', session_id());
      }

      \ADIOS\Core\Helper::addSpeedLogTag("#2.2");


      // translator
      $this->translator = $this->getTranslator();

      // inicializacia routera
      $this->router = $this->getRouter();

      // inicializacia locale objektu
      $this->locale = $this->getLocale();

      // inicializacia objektu notifikacii
      $this->userNotifications = \ADIOS\Core\Factory::create('Core/UserNotifications', [$this]);

      $this->registerModel(get_class(\ADIOS\Core\Factory::create('Models/User', [$this])));
      $this->registerModel(get_class(\ADIOS\Core\Factory::create('Models/UserRole', [$this])));
      $this->registerModel(get_class(\ADIOS\Core\Factory::create('Models/UserHasRole', [$this])));

      // inicializacia DB - aj pre FULL aj pre LITE mod

      $this->onBeforeConfigLoaded();

      if ($mode == self::ADIOS_MODE_FULL) {
        $this->loadConfigFromDB();
      }

      \ADIOS\Core\Helper::addSpeedLogTag("#3");

      // finalizacia konfiguracie - aj pre FULL aj pre LITE mode
      $this->finalizeConfig();
      \ADIOS\Core\Helper::addSpeedLogTag("#3.1");

      $this->onAfterConfigLoaded();
      \ADIOS\Core\Helper::addSpeedLogTag("#3.2");

      // object pre kontrolu permissions
      $this->permissions = \ADIOS\Core\Factory::create('Core/Permissions', [$this]);

      // auth provider
      $this->auth = $this->getAuthProvider();

      // inicializacia web renderera (byvala CASCADA)
      if (isset($this->config['web']) && is_array($this->config['web'])) {
        $this->web = \ADIOS\Core\Factory::create('Core/Web/Loader', [$this, $this->config['web']]);
      }

      // timezone
      date_default_timezone_set($this->config['timezone']);

      if ($mode == self::ADIOS_MODE_FULL) {
        \ADIOS\Core\Helper::addSpeedLogTag("#4");


        // inicializacia widgetov

        $this->onBeforeWidgetsLoaded();

        $this->addAllWidgets($this->config['widgets']);

        $this->onAfterWidgetsLoaded();

        \ADIOS\Core\Helper::addSpeedLogTag("#5");

        // vytvorim definiciu tables podla nacitanych modelov

        foreach ($this->registeredModels as $modelName) {
          $this->getModel($modelName);
        }

        // inicializacia a konfiguracia twigu
        $this->initTwig();
        $this->configureTwig();
      }

      $this->dispatchEventToPlugins("onADIOSAfterInit", ["app" => $this]);
    } catch (\Exception $e) {
      echo "ADIOS INIT failed: [".get_class($e)."] ".$e->getMessage() . "\n";
      echo $e->getTraceAsString() . "\n";
      exit;
    }

    \ADIOS\Core\Helper::addSpeedLogTag("#6");
    // \ADIOS\Core\Helper::printSpeedLogTags();

    return $this;
  }

  public function isAjax(): bool
  {
    return isset($_REQUEST['__IS_AJAX__']) && $_REQUEST['__IS_AJAX__'] == "1";
  }

  public function isWindow(): bool
  {
    return isset($_REQUEST['__IS_WINDOW__']) && $_REQUEST['__IS_WINDOW__'] == "1";
  }

  public function getDefaultConnectionConfig(): ?array
  {
    if (isset($this->config['db']['defaultConnection']) && is_array($this->config['db']['defaultConnection'])) {
      return $this->config['db']['defaultConnection'];
    } else {
      return [
        "driver"    => "mysql",
        "host"      => $this->config['db_host'] ?? '',
        "port"      => $this->config['db_port'] ?? 3306,
        "database"  => $this->config['db_name'] ?? '',
        "username"  => $this->config['db_user'] ?? '',
        "password"  => $this->config['db_password'] ?? '',
        "charset"   => 'utf8mb4',
        "collation" => 'utf8mb4_unicode_ci',
      ];
    }
  }

  public function initDatabaseConnections()
  {
    $this->eloquent = new \Illuminate\Database\Capsule\Manager;

    $dbConnectionConfig = $this->getDefaultConnectionConfig();

    if ($dbConnectionConfig !== null) {
      $this->eloquent->setAsGlobal();
      $this->eloquent->bootEloquent();
      $this->eloquent->addConnection($dbConnectionConfig, 'default');
    }

    $this->pdo->connect();
  }

  public function initTwig()
  {
    $this->twigLoader = new \Twig\Loader\FilesystemLoader();
    $this->twigLoader->addPath($this->config['srcDir']);
    $this->twigLoader->addPath($this->config['srcDir'], $this->twigNamespaceCore);

    $this->twig = new \Twig\Environment($this->twigLoader, array(
      'cache' => false,
      'debug' => true,
    ));
  }

  public function configureTwig()
  {

    $this->twig->addGlobal('config', $this->config);
    $this->twig->addExtension(new \Twig\Extension\StringLoaderExtension());
    $this->twig->addExtension(new \Twig\Extension\DebugExtension());

    $this->twig->addFunction(new \Twig\TwigFunction(
      'adiosModel',
      function (string $model) {
        return $this->getModel($model);
      }
    ));

    $this->twig->addFunction(new \Twig\TwigFunction(
      '_dump',
      function ($var) {
        ob_start();
        _var_dump($var);
        return ob_get_clean();
      }
    ));

    $this->twig->addFunction(new \Twig\TwigFunction(
      'adiosHtmlAttributes',
      function (?array $attributes) {
        if (!is_array($attributes)) {
          return '';
        } else {
          $attrsStr = join(
            ' ',
            array_map(
              function($key) use ($attributes) {
                if (is_bool($attributes[$key])){
                  return $attributes[$key] ? $key : '';
                } else if (is_array($attributes[$key])) {
                  return \ADIOS\Core\Helper::camelToKebab($key)."='".json_encode($attributes[$key])."'";
                } else {
                  return \ADIOS\Core\Helper::camelToKebab($key)."='{$attributes[$key]}'";
                }
              },
              array_keys($attributes)
            )
          );

          return $attrsStr;
        }
      }
    ));

    $this->twig->addFunction(new \Twig\TwigFunction(
      'str2url',
      function ($string) {
        return \ADIOS\Core\Helper::str2url($string ?? '');
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'hasPermission',
      function (string $permission, array $idUserRoles = []) {
        return $this->permissions->granted($permission, $idUserRoles);
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'hasRole',
      function (int|string $role) {
        return $this->permissions->hasRole($role);
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'setTranslationContext',
      function ($context) {
        $this->translationContext = $context;
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'translate',
      function ($string, $context = '') {
        if (empty($context)) $context = $this->translationContext;
        return $this->translate($string, [], $context);
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'adiosView',
      function ($uid, $view, $params) {
        if (!is_array($params)) {
          $params = [];
        }
        return $this->view->create(
          $view . (empty($uid) ? '' : '#' . $uid),
          $params
        )->render();
      }
    ));
    $this->twig->addFunction(new \Twig\TwigFunction(
      'adiosRender',
      function ($controller, $params = []) {
        return $this->render($controller, $params);
      }
    ));
  }

  //////////////////////////////////////////////////////////////////////////////
  // WIDGETS

  public function addWidget($widgetName)
  {
    if (!isset($this->widgets[$widgetName])) {
      try {
        $widgetClassName = "\\" . $this->config['appNamespace'] . "\\Widgets\\".str_replace("/", "\\", $widgetName);
        if (!class_exists($widgetClassName)) {
          throw new \Exception("Widget {$widgetName} not found.");
        }
        $this->widgets[$widgetName] = new $widgetClassName($this);

        $this->router->addRouting($this->widgets[$widgetName]->routing());
      } catch (\Exception $e) {
        exit("Failed to load widget {$widgetName}: ".$e->getMessage());
      }
    }
  }

  public function addAllWidgets(array $widgets = [], $path = "") {
    foreach ($widgets as $wName => $w_config) {
      $fullWidgetName = ($path == "" ? "" : "{$path}/").$wName;
      if (isset($w_config['enabled']) && $w_config['enabled'] === true) {
        $this->addWidget($fullWidgetName);
      } else {
        // ak nie je enabled, moze to este byt dalej vetvene
        if (is_array($w_config)) {
          $this->addAllWidgets($w_config, $fullWidgetName);
        }
      }
    }
  }

  //////////////////////////////////////////////////////////////////////////////
  // MODELS

  public function registerModel(string $modelName): void
  {
    if (!in_array($modelName, $this->registeredModels)) {
      $this->registeredModels[] = $modelName;
    }
  }

  // Deprecated
  public function getModelNames(): array
  {
    return $this->registeredModels;
  }

  /**
  * @return array<string>
  */
  public function getRegisteredModels(): array
  {
    return $this->registeredModels;
  }

  public function getModelClassName($modelName): string
  {
    return str_replace("/", "\\", $modelName);
  }

  /**
   * Returns the object of the model referenced by $modelName.
   * The returned object is cached into modelObjects property.
   *
   * @param  string $modelName Reference of the model. E.g. 'ADIOS/Models/User'.
   * @throws \ADIOS\Core\Exception If $modelName is not available.
   * @return object Instantiated object of the model.
   */
  public function getModel(string $modelName): \ADIOS\Core\Model
  {
    if (!isset($this->modelObjects[$modelName])) {
      try {
        $modelClassName = $this->getModelClassName($modelName);
        $this->modelObjects[$modelName] = new $modelClassName($this);

        // $this->router->addRouting($this->modelObjects[$modelName]->routing());

      } catch (\Exception $e) {
        throw new \ADIOS\Core\Exceptions\GeneralException("Can't find model '{$modelName}'. ".$e->getMessage());
      }
    }

    return $this->modelObjects[$modelName];
  }

  //////////////////////////////////////////////////////////////////////////////
  // PLUGINS

  public function registerPluginFolder($folder)
  {
    if (is_dir($folder) && !in_array($folder, $this->pluginFolders)) {
      $this->pluginFolders[] = $folder;
    }
  }

  public function getPluginClassName($pluginName)
  {
    return "\\ADIOS\\Plugins\\".str_replace("/", "\\", $pluginName);
  }

  public function getPlugin($pluginName)
  {
    return $this->pluginObjects[$pluginName] ?? null;
  }

  public function getPlugins() {
    return $this->pluginObjects;
  }

  public function loadAllPlugins($pluginFolder, $subFolder = "") {
    $folder = $pluginFolder.(empty($subFolder) ? "" : "/{$subFolder}");

    foreach (scandir($folder) as $file) {
      if (strpos($file, ".") !== false) continue;

      $fullPath = (empty($subFolder) ? "" : "{$subFolder}/").$file;

      if (
        is_dir("{$folder}/{$file}")
        && !is_file("{$folder}/{$file}/Main.php")
      ) {
        $this->loadAllPlugins($pluginFolder, $fullPath);
      } else if (is_file("{$folder}/{$file}/Main.php")) {
        try {
          $tmpPluginClassName = $this->getPluginClassName($fullPath);

          if (class_exists($tmpPluginClassName)) {
            $this->plugins[] = $fullPath;
            $this->pluginObjects[$fullPath] = new $tmpPluginClassName($this);
          }
        } catch (\Exception $e) {
          exit("Failed to load plugin {$fullPath}: ".$e->getMessage());
        }
      }
    }
  }

  //////////////////////////////////////////////////////////////////////////////
  // TRANSLATIONS

  public function translate(string $string, array $vars = [], string $context = "app", $toLanguage = ""): string
  {
    return $this->translator->translate($string, $vars, $context, $toLanguage);
  }

  //////////////////////////////////////////////////////////////////////////////
  // MISCELANEOUS

  public function renderAssets() {
    $cachingTime = 3600;
    $headerExpires = "Expires: ".gmdate("D, d M Y H:i:s", time() + $cachingTime)." GMT";
    $headerCacheControl = "Cache-Control: max-age={$cachingTime}";

    if ($this->requestedUri == "adios/cache.css") {
      $cssCache = $this->renderCSSCache();

      header("Content-type: text/css");
      header("ETag: ".md5($cssCache));
      header($headerExpires);
      header("Pragma: cache");
      header($headerCacheControl);

      echo $cssCache;

      exit();
    } else if ($this->requestedUri == "adios/cache.js") {
      $jsCache = $this->renderJSCache();
      $cachingTime = 3600;

      header("Content-type: text/js");
      header("ETag: ".md5($jsCache));
      header($headerExpires);
      header("Pragma: cache");
      header($headerCacheControl);

      echo $jsCache;

      exit();
    } else {
      foreach ($this->assetsUrlMap as $urlPart => $mapping) {
        if (preg_match('/^'.str_replace("/", "\\/", $urlPart).'/', $this->requestedUri, $m)) {

          if ($mapping instanceof \Closure) {
            $sourceFile = $mapping($this, $this->requestedUri);
          } else {
            $sourceFile = $mapping.str_replace($urlPart, "", $this->requestedUri);
          }

          $ext = strtolower(pathinfo($this->requestedUri, PATHINFO_EXTENSION));

          switch ($ext) {
            case "css":
            case "js":
              header("Content-type: text/{$ext}");
              header($headerExpires);
              header("Pragma: cache");
              header($headerCacheControl);
              echo file_get_contents($sourceFile);
              break;
            case "eot":
            case "ttf":
            case "woff":
            case "woff2":
              header("Content-type: font/{$ext}");
              header($headerExpires);
              header("Pragma: cache");
              header($headerCacheControl);
              echo file_get_contents($sourceFile);
              break;
            case "bmp":
            case "gif":
            case "jpg":
            case "jpeg":
            case "png":
            case "tiff":
            case "webp":
            case "svg":
              if ($ext == "svg") {
                $contentType = "svg+xml";
              } else {
                $contentType = $ext;
              }

              header("Content-type: image/{$contentType}");
              header($headerExpires);
              header("Pragma: cache");
              header($headerCacheControl);
              echo file_get_contents($sourceFile);
              break;
          }

          exit();
        }
      }
    }
  }

  public function install() {
    $this->console->clear();

    $installationStart = microtime(true);

    $this->console->info("Dropping existing tables.");

    foreach ($this->registeredModels as $modelName) {
      $model = $this->getModel($modelName);
      $model->dropTableIfExists();
    }

    $this->console->info("Database is empty, installing models.");

    foreach ($this->registeredModels as $modelName) {
      try {
        $model = $this->getModel($modelName);

        $start = microtime(true);

        $model->install();
        $this->console->info("Model {$modelName} installed.", ["duration" => round((microtime(true) - $start) * 1000, 2)." msec"]);
      } catch (\ADIOS\Core\Exceptions\ModelInstallationException $e) {
        $this->console->warning("Model {$modelName} installation skipped.", ["exception" => $e->getMessage()]);
      } catch (\Exception $e) {
        $this->console->error("Model {$modelName} installation failed.", ["exception" => $e->getMessage()]);
      } catch (\Illuminate\Database\QueryException $e) {
        //
      } catch (\ADIOS\Core\Exceptions\DBException $e) {
        // Moze sa stat, ze vytvorenie tabulky zlyha napr. kvoli
        // "Cannot add or update a child row: a foreign key constraint fails".
        // V takom pripade budem instalaciu opakovat v dalsom kole
      }
    }

    foreach ($this->widgets as $widget) {
      try {
        if ($widget->install()) {
          $this->widgetsInstalled[$widget->name] = true;
          $this->console->info("Widget {$widget->name} installed.", ["duration" => round((microtime(true) - $start) * 1000, 2)." msec"]);
        } else {
          $this->console->warning("Model {$modelName} installation skipped.");
        }
      } catch (\Exception $e) {
        $this->console->error("Model {$modelName} installation failed.");
      } catch (\ADIOS\Core\Exceptions\DBException $e) {
        // Moze sa stat, ze vytvorenie tabulky zlyha napr. kvoli
        // "Cannot add or update a child row: a foreign key constraint fails".
        // V takom pripade budem instalaciu opakovat v dalsom kole
      }

      $this->dispatchEventToPlugins("onWidgetAfterInstall", [
        "widget" => $widget,
      ]);
    }

    $this->console->info("Core installation done in ".round((microtime(true) - $installationStart), 2)." seconds.");
  }

  public function extractParamsFromRequest(): array {
    $route = '';
    $params = [];

    if (php_sapi_name() === 'cli') {
      $params = @json_decode($_SERVER['argv'][2] ?? "", true);
      if (!is_array($params)) { // toto nastane v pripade, ked $_SERVER['argv'] nie je JSON string
        $params = $_SERVER['argv'];
      }
      $route = $_SERVER['argv'][1] ?? "";
    } else {
      $params = \ADIOS\Core\Helper::arrayMergeRecursively(
        array_merge($_GET, $_POST),
        json_decode(file_get_contents("php://input"), true) ?? []
      );
      unset($params['route']);
    }

    return $params;
  }

  public function extractRouteFromRequest(): string {
    $route = '';

    if (php_sapi_name() === 'cli') {
      $route = $_SERVER['argv'][1] ?? "";
    } else {
      $route = $_REQUEST['route'] ?? '';
    }

    return $route;
  }

  /**
   * Renders the requested content. It can be the (1) whole desktop with complete <html>
   * content; (2) the HTML of a controller requested dynamically using AJAX; or (3) a JSON
   * string requested dynamically using AJAX and further processed in Javascript.
   *
   * @param  mixed $params Parameters (a.k.a. arguments) of the requested controller.
   * @throws \ADIOS\Core\Exception When running in CLI and requested controller is blocked for the CLI.
   * @throws \ADIOS\Core\Exception When running in SAPI and requested controller is blocked for the SAPI.
   * @return string Rendered content.
   */
  public function render(string $route = '', array $params = []) {

    try {

      \ADIOS\Core\Helper::clearSpeedLogTags();
      \ADIOS\Core\Helper::addSpeedLogTag("render1");

      // Find-out which route is used for rendering

      if (empty($route)) $route = $this->extractRouteFromRequest();
      if (count($params) == 0) $params = $this->extractParamsFromRequest();

      $this->route = $route;
      $this->params = $params;
      $this->uploadedFiles = $_FILES;

      // Apply routing and find-out which controller, permision and rendering params will be used
      // First, try the new routing principle with httpGet
      $tmpController = $this->router->findController(\ADIOS\Core\Router::HTTP_GET, $this->route);

      if ($tmpController !== null) {
        $this->controller = $tmpController;
        $this->view = '';
        $this->permission = '';

        $this->router->extractRouteVariables(\ADIOS\Core\Router::HTTP_GET);

        foreach ($this->router->getRouteVars() as $varName => $varValue) {
          $this->params[$varName] = $varValue;
        }
      } else {
        list($tmpRoute, $this->params) = $this->router->applyRouting($this->route, $this->params);
        $this->console->info("applyRouting for {$this->route}: " . print_r($tmpRoute, true));

        $this->controller = $tmpRoute['controller'] ?? '';
        $this->view = $tmpRoute['view'] ?? '';
        $this->permission = $tmpRoute['permission'] ?? '';

      }

      $this->onAfterRouting();

      if ($this->isUrlParam('sign-out')) {
        $this->auth->signOut();
      }

      if ($this->isUrlParam('signed-out')) {
        $this->router->redirectTo('');
        exit;
      }

      // Check if controller exists and if it can be used
      if (empty($this->controller)) {
        $controllerClassName = \ADIOS\Core\Controller::class;
      } else if (!$this->controllerExists($this->controller)) {
        throw new \ADIOS\Core\Exceptions\ControllerNotFound($this->controller);
      } else {
        $controllerClassName = $this->getControllerClassName($this->controller);
      }

      \ADIOS\Core\Helper::addSpeedLogTag("render2");

      // Create the object for the controller
      $this->controllerObject = new $controllerClassName($this);

      $this->controllerObject->preInit();
      $this->controllerObject->init();
      $this->controllerObject->postInit();

      if (empty($this->permission) && !empty($this->controllerObject->permission)) {
        $this->permission = $this->controllerObject->permission;
      }

      // Perform some basic checks
      if (php_sapi_name() === 'cli') {
        if (!$controllerClassName::$cliSAPIEnabled) {
          throw new \ADIOS\Core\Exceptions\GeneralException("Controller is not enabled in CLI interface.");
        }
      } else {
        if (!$controllerClassName::$webSAPIEnabled) {
          throw new \ADIOS\Core\Exceptions\GeneralException("Controller is not enabled in WEB interface.");
        }
      }

      \ADIOS\Core\Helper::addSpeedLogTag("render3");

      if (!$this->testMode && $this->controllerObject->requiresUserAuthentication) {
        $this->auth->auth();
        if (!$this->auth->isUserInSession()) {
          $this->controllerObject = \ADIOS\Core\Factory::create('Controllers/SignIn', [$this]);
          $this->permission = $this->controllerObject->permission;
        }
        $this->permissions->check($this->permission);
      }

      // All OK, rendering content...

      \ADIOS\Core\Helper::addSpeedLogTag("render4");

      // vygenerovanie UID tohto behu
      if (empty($this->uid)) {
        $uid = $this->getUid($this->urlParamAsString('id'));
      } else {
        $uid = $this->uid.'__'.$this->getUid($this->urlParamAsString('id'));
      }

      $this->setUid($uid);

      $return = '';

      $this->dispatchEventToPlugins("onADIOSBeforeRender", ["app" => $this]);

      unset($this->params['__IS_AJAX__']);

      $this->onBeforeRender();

      // Either return JSON string ...
      $json = $this->controllerObject->renderJson();

      \ADIOS\Core\Helper::addSpeedLogTag("render5");

      if (is_array($json)) {
        $return = json_encode($json);

      // ... Or a view must be applied.
      } else {

        $this->controllerObject->prepareView();
        $view = $this->controllerObject->getView() === '' ? $this->view : $this->controllerObject->getView();

        $contentHtml = '';

        $contentParams = [
          'app' => $this,
          'uid' => $this->uid,
          'user' => $this->auth->getUser(),
          'config' => $this->config,
          'routeUrl' => $this->route,
          'routeParams' => $this->params,
          'route' => $this->route,
          'session' => $this->session->get(),
          'viewParams' => $this->controllerObject->getViewParams(),
          'windowParams' => $this->controllerObject->getViewParams()['windowParams'] ?? null,
        ];

        if ($view !== null) {
          $contentHtml = $this->controllerObject->renderer->render(
            $view,
            $contentParams
          );
        }

        \ADIOS\Core\Helper::addSpeedLogTag("render6");

        // In some cases the result of the view will be used as-is ...
        if ($this->urlParamAsBool('__IS_AJAX__') || $this->controllerObject->hideDefaultDesktop) {
          $html = $contentHtml;

        // ... But in most cases it will be "encapsulated" in the desktop.
        } else {
          $desktopControllerObject = $this->getDesktopController();
          $desktopControllerObject->prepareViewParams();

          $desktopParams = $contentParams;
          $desktopParams['viewParams'] = array_merge($desktopControllerObject->getViewParams(), $contentParams['viewParams']);
          $desktopParams['contentHtml'] = $contentHtml;

          $html = $this->twig->render($this->config['defaultDesktopView'] ?? '@' . $this->twigNamespaceCore . '/Views/Desktop.twig', $desktopParams);

          \ADIOS\Core\Helper::addSpeedLogTag("render7");

        }

        \ADIOS\Core\Helper::addSpeedLogTag("render8");

        return $html;
      }

      $this->onAfterRender();

      \ADIOS\Core\Helper::addSpeedLogTag("render9");

      // \ADIOS\Core\Helper::printSpeedLogTags();

      return $return;

    } catch (\ADIOS\Core\Exceptions\ControllerNotFound $e) {
      header('HTTP/1.1 400 Bad Request', true, 400);
      return $this->renderFatal('Controller not found: ' . $e->getMessage(), false);
      // $this->router->redirectTo("");
    // } catch (\ADIOS\Core\Exceptions\NotEnoughPermissionsException $e) {
    //   header('HTTP/1.1 401 Unauthorized', true, 401);
    //   return $this->renderFatal($e->getMessage(), false);
    } catch (\ADIOS\Core\Exceptions\NotEnoughPermissionsException $e) {
      $message = $e->getMessage();
      if ($this->userLogged) {
        $message .= " Hint: Sign out and sign in again. {$this->config['accountUrl']}?sign-out";
      }
      return $this->renderFatal($message, false);
      // header('HTTP/1.1 401 Unauthorized', true, 401);
    } catch (\ADIOS\Core\Exceptions\GeneralException $e) {
      $lines = [];
      $lines[] = "ADIOS RUN failed: [".get_class($e)."] ".$e->getMessage();
      if ($this->config['debug']) {
        $lines[] = "Requested URI = {$this->requestedUri}";
        $lines[] = "Rewrite base = {$this->config['rewriteBase']}";
        $lines[] = "SERVER.REQUEST_URI = {$this->config['requestUri']}";
      }

      header('HTTP/1.1 400 Bad Request', true, 400);
      return join(" ", $lines);
    } catch (\ArgumentCountError $e) {
      echo $e->getMessage();
      header('HTTP/1.1 400 Bad Request', true, 400);
    } catch (\Exception $e) {
      if ($this->testMode) {
        throw new (get_class($e))($e->getMessage());
      } else {
        $error = error_get_last();

        if ($error && $error['type'] == E_ERROR) {
          $return = $this->renderFatal(
            '<div style="margin-bottom:1em;">'
              . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
            . '</div>'
            . '<pre style="font-size:0.75em;font-family:Courier New">'
              . $e->getTraceAsString()
            . '</pre>',
            true
          );
        } else {
          $return = $this->renderFatal($this->renderExceptionHtml($e));
        }

        return $return;

        if (php_sapi_name() !== 'cli') {
          header('HTTP/1.1 400 Bad Request', true, 400);
        }
      }
    }
  }

  public function getDesktopController(): \ADIOS\Core\Controller
  {
    try {
      return \ADIOS\Core\Factory::create('Controllers/Desktop', [$this]);
    } catch (\Throwable $e) {
      exit("Unable to initialize desktop controller. Check your config.");
    }
  }

  public function getTestProvider(): \ADIOS\Core\Test
  {
    return new \ADIOS\Core\Test($this);
  }

  public function getAuthProvider(): \ADIOS\Core\Auth
  {
    if (!isset($this->config['auth'])) return new \ADIOS\Auth\Providers\DefaultProvider($this, []);

    try {
      return new ($this->config['auth']['provider'])($this, $this->config['auth']['options'] ?? []);
    } catch (\Throwable $e) {
      echo("Unable to initialize auth provider. Check your config.");
      exit($e->getMessage());
    }
  }

  public function getRouter(): \ADIOS\Core\Router
  {
    return \ADIOS\Core\Factory::create('Core/Router', [$this]);
  }

  public function getLocale(): \ADIOS\Core\Locale
  {
    return \ADIOS\Core\Factory::create('Core/Locale', [$this]);
  }

  public function getTranslator(): \ADIOS\Core\Translator
  {
    return new Translator($this);
  }

  public function getControllerClassName(string $controller) : string {
    return '\\' . trim(str_replace('/', '\\', $controller), '\\');
  }

  public function controllerExists(string $controller) : bool {
    return class_exists($this->getControllerClassName($controller));
  }

  public function renderSuccess($return) {
    return json_encode([
      "result" => "success",
      "message" => $return,
    ]);
  }

  public function renderWarning($message, $isHtml = true) {
    if ($this->isAjax() && !$this->isWindow()) {
      return json_encode([
        "status" => "warning",
        "message" => $message,
      ]);
    } else {
      return "
        <div class='alert alert-warning' role='alert'>
          ".($isHtml ? $message : hsc($message))."
        </div>
      ";
    }
  }

  public function renderFatal($message, $isHtml = true) {
    if ($this->isAjax() && !$this->isWindow()) {
      return json_encode([
        "status" => "error",
        "message" => $message,
      ]);
    } else {
      return "
        <div class='alert alert-danger' role='alert' style='z-index:99999999'>
          ".($isHtml ? $message : hsc($message))."
        </div>
      ";
    }
  }

  public function renderHtmlFatal($message) {
    return $this->renderFatal($message, true);
  }


  public function renderExceptionHtml($exception) {

    $traceLog = "";
    foreach ($exception->getTrace() as $item) {
      $traceLog .= "{$item['file']}:{$item['line']}\n";
    }

    $errorMessage = $exception->getMessage();
    $errorHash = md5(date("YmdHis").$errorMessage);

    $errorDebugInfoHtml =
      "Error hash: {$errorHash}<br/>"
      . "<br/>"
      . "<div style='color:#888888'>"
        . get_class($exception) . "<br/>"
        . "Stack trace:<br/>"
        . "<div class='trace-log'>{$traceLog}</div>"
      . "</div>"
    ;

    $this->console->error("{$errorHash}\t{$errorMessage}");

    switch (get_class($exception)) {
      case 'ADIOS\Core\Exceptions\DBException':
        $html = "
          <div class='adios exception emoji'>🥴</div>
          <div class='adios exception message'>
            Oops! Something went wrong with the database.
          </div>
          <div class='adios exception message'>
            {$errorMessage}
          </div>
          {$errorDebugInfoHtml}
        ";
      break;
      case 'Illuminate\Database\QueryException':
      case 'ADIOS\Core\Exceptions\DBDuplicateEntryException':

        if (get_class($exception) == 'Illuminate\Database\QueryException') {
          $dbQuery = $exception->getSql();
          $dbError = $exception->errorInfo[2];
          $errorNo = $exception->errorInfo[1];
        } else {
          list($dbError, $dbQuery, $initiatingModelName, $errorNo) = json_decode($exception->getMessage(), true);
        }

        $invalidColumns = [];

        if (!empty($initiatingModelName)) {
          $initiatingModel = $this->getModel($initiatingModelName);
          $columns = $initiatingModel->columns;
          $indexes = $initiatingModel->indexes();

          preg_match("/Duplicate entry '(.*?)' for key '(.*?)'/", $dbError, $m);
          $invalidIndex = $m[2];
          $invalidColumns = [];
          foreach ($indexes[$invalidIndex]['columns'] as $columnName) {
            $invalidColumns[] = $columns[$columnName]->getTitle();
          }
        } else {
          preg_match("/Duplicate entry '(.*?)' for key '(.*?)'/", $dbError, $m);
          if (!empty($m[2])) $invalidColumns = [$m[2]];
        }

        switch ($errorNo) {
          case 1216:
          case 1451:
            $errorMessage = "You cannot delete record that is linked with another records. Delete the linked records first.";
          break;
          case 1062:
          case 1217:
          case 1452:
            $errorMessage = "You are trying to save a record that is already existing.";
          break;
          default:
            $errorMessage = $dbError;
          break;
        }

        $html = "
          <div class='adios exception message'>
            ".$this->translate($errorMessage)."<br/>
            <br/>
            <b>".join(", ", $invalidColumns)."</b>
          </div>
          <pre style='font-size:9px;text-align:left'>{$errorDebugInfoHtml}</pre>
        ";
      break;
      default:
        $html = "
          <div class='adios exception message'>
            Oops! Something went wrong.
          </div>
          <div class='adios exception message'>
            ".$exception->getMessage()."
          </div>
          {$errorDebugInfoHtml}
        ";
      break;
    }

    return $html;//$this->renderHtmlWarning($html);
  }

  public function renderHtmlWarning($warning) {
    return $this->renderWarning($warning, true);
  }

  /**
   * Propagates an event to all plugins of the application. Each plugin can
   * implement hook for the event. The hook must return either modified event
   * data of false. Returning false in the hook terminates the event propagation.
   *
   * @param  string $eventName Name of the event to propagate.
   * @param  array $eventData Data of the event. Each event has its own specific structure of the data.
   * @throws \ADIOS\Core\Exception When plugin's hook returns invalid value.
   * @return array<string, mixed> Event data modified by plugins which implement the hook.
   */
  public function dispatchEventToPlugins(string $eventName, array $eventData = []): array
  {
    foreach ($this->pluginObjects as $plugin) {
      if (method_exists($plugin, $eventName)) {
        $eventData = $plugin->$eventName($eventData);
        if (!is_array($eventData) && $eventData !== false) {
          throw new \ADIOS\Core\Exceptions\GeneralException("Plugin {$plugin->name}, event {$eventName}: No value returned. Either forward \$event or return FALSE.");
        }

        if ($eventData === false) {
          break;
        }
      }
    }
    return $eventData;
  }

  public function hasPermissionForController($controller, $params) {
    return true;
  }

  ////////////////////////////////////////////////
  // metody pre pracu s konfiguraciou

  public function isConfig(string $path): bool
  {
    $config = $this->config;
    foreach (explode('/', $path) as $key => $value) {
      if (isset($config[$value])) {
        $config = $config[$value];
      } else {
        $config = null;
      }
    }
    return ($config === null ? false : true);
  }

  public function getConfig(string $path, $default = null): mixed
  {
    $config = $this->config;
    foreach (explode('/', $path) as $key => $value) {
      if (isset($config[$value])) {
        $config = $config[$value];
      } else {
        $config = null;
      }
    }
    return ($config === null ? $default : $config);
  }

  public function setConfig(string $path, mixed $value): void
  {
    $path_array = explode('/', $path);

    $cfg = &$this->config;
    foreach ($path_array as $path_level => $path_slice) {
      if ($path_level == count($path_array) - 1) {
        $cfg[$path_slice] = $value;
      } else {
        if (empty($cfg[$path_slice])) {
          $cfg[$path_slice] = null;
        }
        $cfg = &$cfg[$path_slice];
      }
    }
  }

  public function saveConfig(array $config, string $path = '') {
    try {
      if (is_array($config)) {
        foreach ($config as $key => $value) {
          $tmpPath = $path.$key;

          if (is_array($value)) {
            $this->saveConfig($value, $tmpPath.'/');
          } else if ($value === null) {
            $this->pdo->execute("delete from `config` where `path` like '?%'", [$tmpPath]);
          } else {
            $this->pdo->execute("
              insert into `config` set `path` = '?', `value` = '?'
              on duplicate key update `path` = '?', `value` = '?'
            ", [$tmpPath, $value, $tmpPath, $value]);
          }
        }
      }
    } catch (\Exception $e) {
      // do nothing
    }
  }

  public function saveConfigByPath(string $path, string $value) {
    if (!empty($path)) {
      $this->pdo->execute("
        insert into `config` set `path` = :path, `value` = :value
        on duplicate key update `path` = :path, `value` = :value
      ", ['path' => $path, 'value' => $value]);
    }
  }

  public function deleteConfig($path) {
    if (!empty($path)) {
      $this->pdo->execute("delete from `config` where `path` like ?", [$path . '%']);
    }
  }

  public function loadConfigFromDB() {
    if (!$this->pdo->isConnected) return;

    $cfgs = $this->pdo->fetchAll("select * from `config`");

    foreach ($cfgs as $cfg) {
      $tmp = &$this->config;
      foreach (explode("/", $cfg['path']) as $tmp_path) {
        if (!isset($tmp[$tmp_path])) {
          $tmp[$tmp_path] = [];
        }
        $tmp = &$tmp[$tmp_path];
      }
      $tmp = $cfg['value'];
    }
  }

  public function finalizeConfig() {
    // various default values
    $this->config['widgets'] = $this->config['widgets'] ?? [];
    $this->config['protocol'] = (strtoupper($_SERVER['HTTPS'] ?? "") == "ON" ? "https" : "http");
    $this->config['timezone'] = $this->config['timezone'] ?? 'Europe/Bratislava';

    $this->config['uploadDir'] = $this->config['uploadDir'] ?? "{$this->config['accountDir']}/upload";
    $this->config['uploadUrl'] = $this->config['uploadUrl'] ?? "{$this->config['accountUrl']}/upload";

    $this->config['uploadDir'] = str_replace("\\", "/", $this->config['uploadDir']);
  }

  public function onUserAuthorised() {
    // to be overriden
  }

  public function onBeforeConfigLoaded() {
    // to be overriden
  }

  public function onAfterConfigLoaded() {
    // to be overriden
  }

  public function onBeforeWidgetsLoaded() {
    // to be overriden
  }

  public function onAfterWidgetsLoaded() {
    // to be overriden
  }

  public function onBeforePluginsLoaded() {
    // to be overriden
  }

  public function onAfterPluginsLoaded() {
    // to be overriden
  }

  public function onAfterRouting() {
    // to be overriden
  }

  public function onBeforeRender() {
    foreach ($this->widgets as $widget) {
      $widget->onBeforeRender();
    }
  }

  public function onAfterRender() {
    foreach ($this->widgets as $widget) {
      $widget->onAfterRender();
    }
  }

  ////////////////////////////////////////////////



  public function getUid($uid = '') {
    if (empty($uid)) {
      $tmp = $this->controller.'-'.time().rand(100000, 999999);
    } else {
      $tmp = $uid;
    }

    $tmp = str_replace('\\', '/', $tmp);
    $tmp = str_replace('/', '-', $tmp);

    $uid = "";
    for ($i = 0; $i < strlen($tmp); $i++) {
      if ($tmp[$i] == "-") {
        $uid .= strtoupper($tmp[++$i]);
      } else {
        $uid .= $tmp[$i];
      }
    }

    $this->setUid($uid);

    return $uid;
  }

  /**
   * Checks the argument whether it is a valid ADIOS UID string.
   *
   * @param  string $uid The string to validate.
   * @throws \ADIOS\Core\Exceptions\InvalidUidException If the provided string is not a valid ADIOS UID string.
   * @return void
   */
  public function checkUid($uid) {
    if (preg_match('/[^A-Za-z0-9\-_]/', $uid)) {
      throw new \ADIOS\Core\Exceptions\InvalidUidException();
    }
  }

  public function setUid($uid) {
    $this->checkUid($uid);
    $this->uid = $uid;
  }

  public function renderCSSCache() {
    $css = "";

    $cssFiles = [
      dirname(__FILE__)."/../Assets/Css/fontawesome-5.13.0.css",
      dirname(__FILE__)."/../Assets/Css/bootstrap.min.css",
      dirname(__FILE__)."/../Assets/Css/sb-admin-2.css",
      dirname(__FILE__)."/../Assets/Css/components.css",
      dirname(__FILE__)."/../Assets/Css/colors.css",
    ];

    foreach ($cssFiles as $file) {
      $css .= @file_get_contents($file)."\n";
    }

    return $css;
  }

  private function scanReactFolder(string $path): string {
    $reactJs = '';

    foreach (scandir($path . '/Assets/Js/React') as $file) {
      if ('.js' == substr($file, -3)) {
        $reactJs = @file_get_contents($path . "/Assets/Js/React/{$file}") . ";";
        break;
      }
    }

    return $reactJs;
  }

  public function renderJSCache() {
    $js = "";

    $jsFiles = [
      dirname(__FILE__)."/../Assets/Js/jquery-3.5.1.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.scrollTo.min.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.window.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.ui.widget.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.ui.mouse.js",
      // dirname(__FILE__)."/../Assets/Js/jquery-ui-touch-punch.js",
      dirname(__FILE__)."/../Assets/Js/md5.js",
      dirname(__FILE__)."/../Assets/Js/base64.js",
      dirname(__FILE__)."/../Assets/Js/cookie.js",
      // dirname(__FILE__)."/../Assets/Js/keyboard_shortcuts.js",
      dirname(__FILE__)."/../Assets/Js/json.js",
      dirname(__FILE__)."/../Assets/Js/moment.min.js",
      dirname(__FILE__)."/../Assets/Js/chart.min.js",
      dirname(__FILE__)."/../Assets/Js/desktop.js",
      dirname(__FILE__)."/../Assets/Js/ajax_functions.js",
      dirname(__FILE__)."/../Assets/Js/adios.js",
      dirname(__FILE__)."/../Assets/Js/quill-1.3.6.min.js",
      // dirname(__FILE__)."/../Assets/Js/bootstrap.bundle.js",
      dirname(__FILE__)."/../Assets/Js/jquery.easing.js",
      dirname(__FILE__)."/../Assets/Js/sb-admin-2.js",
      // dirname(__FILE__)."/../Assets/Js/jsoneditor.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.tag-editor.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.caret.min.js",
      // dirname(__FILE__)."/../Assets/Js/jquery-ui.min.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.multi-select.js",
      // dirname(__FILE__)."/../Assets/Js/jquery.quicksearch.js",
      // dirname(__FILE__)."/../Assets/Js/datatables.js",
      dirname(__FILE__)."/../Assets/Js/jeditable.js",
      dirname(__FILE__)."/../Assets/Js/draggable.js"
    ];


    foreach ($jsFiles as $file) {
      $js .= (string) @file_get_contents($file) . ";\n";
    }

    return $js;
  }

  public function configAsString(string $path, string $defaultValue = ''): string
  {
    return (string) $this->getConfig($path, $defaultValue);
  }

  public function configAsInteger(string $path, int $defaultValue = 0): int
  {
    return (int) $this->getConfig($path, $defaultValue);
  }

  public function configAsFloat(string $path, float $defaultValue = 0): float
  {
    return (float) $this->getConfig($path, $defaultValue);
  }

  public function configAsBool(string $path, bool $defaultValue = false): bool
  {
    return (bool) $this->getConfig($path, $defaultValue);
  }

  public function configAsArray(string $path, array $defaultValue = []): array
  {
    return (array) $this->getConfig($path, $defaultValue);
  }




  public function getUrlParams(): array
  {
    return $this->params;
  }

  public function isUrlParam(string $paramName): bool
  {
    return isset($this->params[$paramName]);
  }

  public function urlParamNotEmpty(string $paramName): bool
  {
    return $this->isUrlParam($paramName) && !empty($this->params[$paramName]);
  }

  public function setUrlParam(string $paramName, string $newValue): void
  {
    $this->params[$paramName] = $newValue;
  }

  public function removeUrlParam(string $paramName): void
  {
    if (isset($this->params[$paramName])) unset($this->params[$paramName]);
  }

  public function urlParamAsString(string $paramName, string $defaultValue = ''): string
  {
    if (isset($this->params[$paramName])) return (string) $this->params[$paramName];
    else return $defaultValue;
  }

  public function urlParamAsInteger(string $paramName, int $defaultValue = 0): int
  {
    if (isset($this->params[$paramName])) return (int) $this->params[$paramName];
    else return $defaultValue;
  }

  public function urlParamAsFloat(string $paramName, float $defaultValue = 0): float
  {
    if (isset($this->params[$paramName])) return (float) $this->params[$paramName];
    else return $defaultValue;
  }

  public function urlParamAsBool(string $paramName, bool $defaultValue = false): bool
  {
    if (isset($this->params[$paramName])) return (bool) $this->params[$paramName];
    else return $defaultValue;
  }

  /**
  * @return array<string, string>
  */
  public function urlParamAsArray(string $paramName, array $defaultValue = []): array
  {
    if (isset($this->params[$paramName])) return (array) $this->params[$paramName];
    else return $defaultValue;
  }

}
