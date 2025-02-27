<?php

namespace ADIOS\Auth\Providers;

class KeycloakOAuth2 extends \ADIOS\Core\Auth {
  public $provider;

  function __construct(\ADIOS\Core\Loader $app, array $config = [])
  {
    parent::__construct($app, $config);

    $this->provider = new \League\OAuth2\Client\Provider\GenericProvider([
      'clientId'                => $config['clientId'],
      'clientSecret'            => $config['clientSecret'],
      'redirectUri'             => $config['redirectUri'],
      'urlAuthorize'            => $config['urlAuthorize'],
      'urlAccessToken'          => $config['urlAccessToken'],
      'urlResourceOwnerDetails' => $config['urlResourceOwnerDetails'],
    ], [
      'httpClient' => new \GuzzleHttp\Client([\GuzzleHttp\RequestOptions::VERIFY => false]),
    ]);

  }

  public function signOut() {
    $accessToken = $this->getAccessToken();
    $accountUrl = $this->app->configAsString('accountUrl');
    try {
      $idToken = $accessToken->getValues()['id_token'] ?? '';

      $this->deleteSession();
      header(
        "Location: " . $this->app->configAsString('auth/urlLogout') . 
        "?id_token_hint=".\urlencode($idToken)."&post_logout_redirect_uri=".\urlencode($accountUrl . "?signed-out")
      );
      exit;
    } catch (\Throwable $e) {
      $this->deleteSession();
      header("Location: {$accountUrl}");
      exit;
    }
  }

  public function getAccessToken() {
    return $this->app->session->get('oauthAccessToken');
  }

  public function setAccessToken($accessToken) {
    $this->app->session->set('oauthAccessToken', $accessToken);
  }

  public function auth()
  {
    $accessToken = $this->getAccessToken();

    if ($accessToken) {
      try {
        $accessToken = $this->provider->getAccessToken('refresh_token', [
          'refresh_token' => $accessToken->getRefreshToken()
        ]);

        $this->setAccessToken($accessToken);

        $resourceOwner = $this->provider->getResourceOwner($accessToken);

        if ($resourceOwner) $this->signIn($resourceOwner->toArray());
        else $this->deleteSession();
      } catch (\Exception $e) {
        $this->deleteSession();
      }
    } else {

      $authCode = $this->app->urlParamAsString('code');
      $authState = $this->app->urlParamAsString('state');

      // If we don't have an authorization code then get one
      if (empty($authCode)) {

        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $this->provider->getAuthorizationUrl(['scope' => ['openid']]);

        // Get the state generated for you and store it to the session.
        $this->app->session->set('oauth2state', $this->provider->getState());

        // Optional, only required when PKCE is enabled.
        // Get the PKCE code generated for you and store it to the session.
        $this->app->session->set('oauth2pkceCode', $this->provider->getPkceCode());

        // Redirect the user to the authorization URL.
        header('Location: ' . $authorizationUrl);
        exit;

      // Check given state against previously stored one to mitigate CSRF attack
      } elseif (
        empty($authState)
        || empty($this->app->session->get('oauth2state'))
        || $authState !== $this->app->session->get('oauth2state')
      ) {
        if ($this->app->session->isset('oauth2state')) $this->app->session->unset('oauth2state');
        exit('Invalid state');
      } else {

        try {

          // Optional, only required when PKCE is enabled.
          // Restore the PKCE code stored in the session.
          $this->provider->setPkceCode($this->app->session->get('oauth2pkceCode'));

          // Try to get an access token using the authorization code grant.
          $accessToken = $this->provider->getAccessToken('authorization_code', [
            'code' => $authCode
          ]);

          $this->setAccessToken($accessToken);

          // Using the access token, we may look up details about the
          // resource owner.
          $resourceOwner = $this->provider->getResourceOwner($accessToken);

          $authResult = $resourceOwner->toArray();

        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

          if ($e->getMessage() == 'invalid_grant') {
          }

          // Failed to get the access token or user details.
          exit($e->getMessage());

        }
      }

      if ($authResult) {
        $this->signIn($authResult);

        $this->app->router->redirectTo('');
        exit;
      } else {
        $this->deleteSession();
      }
    }
  }
}
