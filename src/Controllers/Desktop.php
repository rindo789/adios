<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Controllers;

/**
 * 'Desktop' action. Renders the ADIOS application's desktop.
 *
 * This is the default action rendered when the ADIOS application is open via a URL.
 * The desktop is divided into following visual parts:
 *   * Left sidebar
 *   * Notification and profile information area on the top of the screen
 *   * The main content area
 *
 * Action can be configured to render another action in the main content area.
 *
 * @package Components\Controllers
 */
class Desktop extends \ADIOS\Core\Controller {

  // public string $twigTemplate = "App/Core/Views/Desktop";
  // public string $contentController = '';

  // function __construct(\ADIOS\Core\Loader $adios, array $params = [])
  // {
  //   parent::__construct($adios, $params);

  //   $this->contentController = $params['contentController'] ?? '';
  // }

  public function addSidebarItem($widget, $item) {
    $item['___widgetClassName'] = get_class($widget);
    $this->adios->config['desktop']['sidebarItems'][] = $item;
  }

  public function prepareViewAndParams(): array {

    foreach ($this->adios->widgets as $widget) {
      $widget->onBeforeDesktopParams($this);
    }

    $settingsMenuItems = [];

    $settingsMenuItems[] = [
      "faIcon" => "fas fa-user",
      "text" => $this->translate("My profile"),
      "onclick" => "
        ADIOS.renderWindow(
          'MyProfile',
          '',
          function() {
            setTimeout(function() {
              window.location.reload();
            }, 10);
          }
        );
      ",
    ];

    $settingsMenuItems[] = [
      "faIcon" => "fas fa-window-restore",
      "text" => $this->translate("Open new tab"),
      "onclick" => "window.open('{$this->adios->config['url']}');",
    ];

    // $settingsMenuItems[] = [
    //   "faIcon" => "fas fa-bolt",
    //   "text" => $this->translate("Restart"),
    //   "onclick" => "
    //     if (window.location.href.indexOf('restart=1') == '-1') {
    //       if (window.location.href.indexOf('?') == -1) {
    //         window.location.href = window.location.href + '?restart=1';
    //       } else {
    //         window.location.href = window.location.href + '&restart=1';
    //       }
    //     } else {
    //       window.location.reload();
    //     }
    //   ",
    // ];

    $settingsLogoutItems = [
      "faIcon" => "fas fa-sign-out-alt",
      "text" => $this->translate("Log out"),
      "consent" => $this->translate("Are you sure to log out?"),
      "not_logout" => $this->translate("Do not logout"),
    ];

    // develMenuItems
    // $develMenuItems = [];

    // if ($this->adios->config['develMode']) {
    //   // $develMenuItems[] = [
    //   //   "text" => $this->translate("Show console"),
    //   //   "faIcon" => "fas fa-terminal",
    //   //   "onclick" => "desktop_show_console();",
    //   // ];
    //   /*$develMenuItems[] = [
    //     "text" => $this->translate("Examples of UI"),
    //     "faIcon" => "fas fa-hammer",
    //     "onclick" => "desktop_render('SkinSamples');",
    //   ];*/
    // }

    // if (
    //   !empty($this->contentController)
    //   && $this->contentController != 'Desktop'
    // ) {
    //   $contentHtml = $this->adios->render($this->contentController, $this->params);
    // } else {
    //   $contentHtml = '';
    // }

    $this->viewParams = array_merge($this->params ?? [],
      [
        "config" => $this->adios->config,
        "user" => $this->adios->userProfile,
        "console" => $this->adios->console->getLogs(),
        "settingsMenuItems" => $settingsMenuItems,
        "settingsLogoutItems" => $settingsLogoutItems,
      ]
    );

    // $desktopContentActionClassName = $this->adios->getActionClassName($this->adios->desktopContentAction);
    // $desktopContentActionObject = new $desktopContentActionClassName($this->adios);
    // $params = $desktopContentActionObject->onAfterDesktopPreRender($params);

    $this->view = 'App/Core/Views/Desktop';

    return [$this->view, $this->viewParams];
  }
}
