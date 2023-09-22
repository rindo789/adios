<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core\Models;

/**
 * Model for storing user profiles. Stored in 'users' SQL table.
 *
 * @package DefaultModels
 */
class User extends \ADIOS\Core\Model {
  const TOKEN_TYPE_USER_FORGOT_PASSWORD = 551155;

  public string $urlBase = "users";
  public ?string $lookupSqlValue = "concat({%TABLE%}.name, ' ', {%TABLE%}.surname)";

  public string $tableTitle = "Users";
  public string $formTitleForInserting = "New user";
  public string $formTitleForEditing = "User";

  public function __construct($adiosOrAttributes = NULL, $eloquentQuery = NULL) {
    $this->sqlName = "users";
    parent::__construct($adiosOrAttributes, $eloquentQuery);

    if (is_object($adiosOrAttributes)) {
      $this->tableTitle = $this->translate("Users");
      $tokenModel = $adiosOrAttributes->getModel("ADIOS/Core/Models/Token");

      if (!$tokenModel->isTokenTypeRegistered(self::TOKEN_TYPE_USER_FORGOT_PASSWORD)) {
        $tokenModel->registerTokenType(self::TOKEN_TYPE_USER_FORGOT_PASSWORD);
      }
    }
  }

  public function columns(array $columns = []): array
  {
    return parent::columns([
      'name' => [
        'type' => 'varchar',
        'title' => $this->translate('Given name'),
        'viewParams' => [
          'Table' => [
            'showColumn' => true
          ],
        ],
      ],
      'surname' => [
        'type' => 'varchar',
        'title' => $this->translate('Family name'),
        'viewParams' => [
          'Table' => [
            'showColumn' => true
          ],
        ],
      ],
      'login' => [
        'type' => 'varchar',
        'title' => $this->translate('Login')
      ],
      'password' => [
        'type' => 'password',
        'title' => $this->translate('Password')
      ],
      'email' => [
        'type' => 'varchar',
        'title' => $this->translate('Email')
      ],
      'phone_number' => [
        'type' => 'varchar',
        'title' => $this->translate('Phone number')
      ],
      'id_role' => [
        'type' => 'lookup',
        'title' => $this->translate('Role'),
        'model' => "ADIOS/Core/Models/UserRole",
        'viewParams' => [
          'Table' => [
            'showColumn' => true
          ],
        ],
        'input_style' => 'select'
      ],
      'photo' => [
        'type' => 'image',
        'title' => $this->translate('Photo'),
        'only_upload' => 'yes',
        'subdir' => 'users/',
        "description" => $this->translate("Supported image extensions: jpg, gif, png, jpeg"),
      ],
      'is_active' => [
        'type' => 'boolean',
        'title' => $this->translate('Active'),
        'viewParams' => [
          'Table' => [
            'showColumn' => true
          ],
        ],
      ],
      'last_login_time' => [
        'type' => 'datetime',
        'title' => $this->translate('Time of last login'),
        'readonly' => TRUE,
      ],
      'last_login_ip' => [
        'type' => 'varchar',
        'title' => $this->translate('Last login IP'),
        'readonly' => TRUE,
      ],
      'last_access_time' => [
        'type' => 'datetime',
        'title' => $this->translate('Time of last access'),
        'readonly' => TRUE,
      ],
      'last_access_ip' => [
        'type' => 'varchar',
        'title' => $this->translate('Last access IP'),
        'readonly' => TRUE,
      ],
      'id_token_reset_password' => [
        'type' => 'lookup',
        'model' => "ADIOS/Core/Models/Token",
        'title' => $this->translate('Reset password token'),
        'readonly' => TRUE,
      ]
    ]);
  }

  public function upgrades() : array {
    // Upgrade nebude fungovať pretože sa mení logika prihlásenia a upgrade sa vykoná až po prihlásení.
    // Upgrade je možné realizovať nanovo vytvorením tabuľky users napríklad pomocou funkcie $model->install()
    // Pri tomto riešení je potrebné manuálne zálohovať používateľov a následne ich importovať.
    return [
      0 => [], // upgrade to version 0 is the same as installation
      1 => [
        "ALTER TABLE `{$this->getFullTableSqlName()}` CHANGE  `active` `is_active` tinyint(1);",
        "
          ALTER TABLE `{$this->getFullTableSqlName()}`
          ADD column `phone_number` varchar(255) DEFAULT '' after `email`
        ",
        "
          ALTER TABLE `{$this->getFullTableSqlName()}`
          ADD column `last_login_time` varchar(255) DEFAULT '' after `is_active`
        ",
        "
          ALTER TABLE `{$this->getFullTableSqlName()}`
          ADD column `last_login_ip` varchar(255) DEFAULT '' after `last_login_time`
        ",
        "
          ALTER TABLE `{$this->getFullTableSqlName()}`
          ADD column `last_access_time` varchar(255) DEFAULT '' after `last_login_ip`
        ",
        "
          ALTER TABLE `{$this->getFullTableSqlName()}`
          ADD column `last_access_ip` varchar(255) DEFAULT '' after `last_access_time`
        ",
      ],
    ];
  }

  public function routing(array $routing = []) {
    return parent::routing([
      '/^MyProfile$/' => [
        "action" => "UI/Form",
        "params" => [
          "model" => "ADIOS/Core/Models/User",
          "myProfileView" => TRUE,
          "id" => $this->adios->userProfile['id'],
        ]
      ],
    ]);
  }

  public function getById($id) {
    $id = (int) $id;
    $user = self::find($id);
    return ($user === NULL ? [] : $user->toArray());
  }

  public function onFormParams(\ADIOS\Core\Views\Form $formObject, array $params): array
  {

    if ($params["myProfileView"]) {
      $params['show_delete_button'] = FALSE;
      $params['template'] = [
        "columns" => [
          [
            "rows" => [
              "name",
              "surname",
              "login",
              "password",
              "email",
              "phone_number",
              "photo",
            ],
          ],
        ],
      ];
    }

    return (array) $params;
  }

  public function generateToken($idUser, $tokenSalt, $tokenType) {
    $tokenModel = $this->adios->getModel("ADIOS/Core/Models/Token");
    $token = $tokenModel->generateToken($tokenSalt, $tokenType);

    $this->updateRow([
      "id_token_reset_password" => $token['id'],
    ], $idUser);

    return $token['token'];
  }

  public function generatePasswordResetToken($idUser, $tokenSalt) {
    return $this->generateToken(
      $idUser,
      $tokenSalt,
      self::TOKEN_TYPE_USER_FORGOT_PASSWORD
    );
  }

  public function validateToken($token, $deleteAfterValidation = TRUE) {
    $tokenModel = $this->adios->getModel("ADIOS/Core/Models/Token");
    $tokenData = $tokenModel->validateToken($token);

    $userData = $this->where(
      'id_token_reset_password', $tokenData['id']
      )->first()
    ;

    if (!empty($userData)) {
      $userData = $userData->toArray();
    }

    if ($deleteAfterValidation) {
      $this->updateRow([
        "id_token_reset_password" => NULL,
      ], $userData["id"]);

      $tokenModel->deleteToken($tokenData['id']);
    }

    return $userData;
  }

  public function getByEmail(string $email) {
    $user = self::where("email", $email)->first();

    return !empty($user) ? $user->toArray() : [];
  }

  public function updatePassword(int $idUser, string $password) {
    return
      self::where('id', $idUser)
      ->update(
        ["password" => password_hash($password, PASSWORD_DEFAULT)]
      )
    ;
  }

}
