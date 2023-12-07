<?php

namespace ADIOS\Core;

/**
 * Core implementation of ADIOS controller
 * 
 * 'Controller' is fundamendal class for generating HTML content of each ADIOS call. Controllers can
 * be rendered using Twig template or using custom render() method.
 * 
 */
class Controller {
  /**
   * Reference to ADIOS object
   */
  protected ?\ADIOS\Core\Loader $adios = null;
    
  /**
   * Shorthand for "global table prefix"
   */
  protected string $gtp = "";
  
  /**
   * Array of parameters (arguments) passed to the controller
   */
  protected array $params;

  /**
   * TRUE/FALSE array with permissions for the user role
   */
  public static array $permissionsByUserRole = [];
  
  /**
   * If set to FALSE, the rendered content of controller is available to public
   */
  public static bool $requiresUserAuthentication = TRUE;

  /**
   * If set to TRUE, the default ADIOS desktop will not be added to the rendered content
   */
  public static bool $hideDefaultDesktop = FALSE;

  /**
   * If set to FALSE, the controller will not be rendered in CLI
   */
  public static bool $cliSAPIEnabled = TRUE;

  /**
   * If set to FALSE, the controller will not be rendered in WEB
   */
  public static bool $webSAPIEnabled = TRUE;

  public array $dictionary = [];

  public string $name = "";
  public string $shortName = "";
  public string $uid = "";
  public string $controller = "";
  public string $myRootFolder = "";
  public string $twigTemplate = "";

  function __construct(\ADIOS\Core\Loader $adios, array $params = [])
  {
    $this->name = str_replace("\\", "/", str_replace("ADIOS\\", "", get_class($this)));
    $this->shortName = end(explode("/", $this->name));
    $this->adios = $adios;
    $this->params = $params;
    $this->uid = $this->adios->uid;
    $this->gtp = $this->adios->gtp;
    $this->controller = $this->adios->controller;

    $this->myRootFolder = str_replace("\\", "/", dirname((new \ReflectionClass(get_class($this)))->getFileName()));

    if (!is_array($this->params)) {
      $this->params = [];
    }

    if (!empty($this->adios->config['templates'][static::class])) {
      $this->twigTemplate = $this->adios->config['templates'][static::class];
    }

    $this->init();
  }

  public function init() {
    //
  }
  
  /**
   * Used to change ADIOS configuration before calling preRender()
   *
   * @param  array $config Current ADIOS configuration
   * @return array Changed ADIOS configuration
   */
  public static function overrideConfig($config, $params) {
    return $config;
  }

  /**
   * If the controller shall only return JSON, this method must be overriden.
   *
   * @return array Array to be returned as a JSON.
   */
  public function renderJson() {
    return NULL;
  }

  /**
   * If the controller shall return the HTML of the view, this method must be overriden.
   *
   * @return array View to be used to render the HTML.
   */
  public function prepareViewAndParams(): array
  {
    return [
      'view' => '',
      'params' => [],
    ];
  }
  
  /**
   * Used to return values for TWIG renderer. Applies only in TWIG template of the controller.
   *
   * @return array Values for controller's TWIG template
   */
  public function preRender() {
    return [];
  }

  /**
   * Callback to modify TWIG params for Desktop.twig template.
   *
   * @param array $params Params generated by preRender().
   *
   * @return array Values for controller's TWIG template
   */
  public function onAfterDesktopPreRender($params) {
    return $params;
  }

  // public function applyURLParams($myParams) {
  //   if (empty($myParams) || !is_array($myParams)) $myParams = [];
  //   return array_merge($this->params['_GET'], $myParams);
  // }
  
  /**
   * Shorthand for ADIOS core translate() function. Uses own language dictionary.
   *
   * @param  string $string String to be translated
   * @param  string $context Context where the string is used
   * @param  string $toLanguage Output language
   * @return string Translated string.
   */
  public function translate(string $string, array $vars = []): string
  {
    return $this->adios->translate($string, $vars, $this);
  }
  
  /**
   * Renders the content of requested controller using Twig template.
   * In most cases is this method overriden.
   *
   * @return string Rendered HTML content of the controller.
   * @return array Key-value pair of output values. Will be converted to JSON.
   * 
   * @throws \Twig\Error\RuntimeError
   * @throws \Twig\Error\LoaderError
   */
  public function render()
  {
    $twigParams = array_merge($this->params, $this->preRender());

    $twigParams["uid"] = $this->adios->uid;
    $twigParams["gtp"] = $this->adios->gtp;
    $twigParams["config"] = $this->adios->config;
    $twigParams["requestedUri"] = $this->adios->requestedUri;
    $twigParams["user"] = $this->adios->userProfile;
    $twigParams["locale"] = $this->adios->locale->getAll();
    $twigParams["dictionary"] = $this->dictionary;
    $twigParams['userNotifications'] = $this->adios->userNotifications->getAsHtml();

    try {
      $tmpTemplate = empty($this->twigTemplate)
        ? str_replace("\\Controllers\\", "\\Templates\\", static::class)
        : $this->twigTemplate
      ;

      return $this->adios->twig->render(
        $tmpTemplate,
        $twigParams
      );
    } catch (\Twig\Error\RuntimeError $e) {
      throw ($e->getPrevious());
    } catch (\Twig\Error\LoaderError $e) {
      return $e->getMessage();
    }
  }

  public function getRequestParams(): array {
    return json_decode(file_get_contents("php://input"), true);
  }
}

