<?php
namespace App\Provider;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Config;

trait IndieAuth {

  private function _start_indieauth(&$response, $login_request, $details) {
    // Encode this request's me/redirect_uri/state in the state parameter to avoid a session?
    $state = generate_state();
    $authorize = \IndieAuth\Client::buildAuthorizationURL($details['authorization_endpoint'], $login_request['me'], Config::$base.'redirect/indieauth', $login_request['client_id'], $state, '');

    return $response->withHeader('Location', $authorize)->withStatus(302);
  }

  public function redirect_indieauth(ServerRequestInterface $request, ResponseInterface $response) {
    session_start();

    $query = $request->getQueryParams();

    // Verify the state parameter
    if(!isset($_SESSION['state']) || $_SESSION['state'] != $query['state']) {
      die('Invalid state parameter from IndieAuth server');
    }

    $params = [
      'code' => $query['code'],
      'client_id' => $_SESSION['login_request']['client_id'],
      'redirect_uri' => Config::$base.'redirect/indieauth',
    ];

    $http = http_client();
    $result = $http->post($_SESSION['login_request']['authorization_endpoint'], $params, [
      'Accept: application/json'
    ]);

    $auth = json_decode($result['body'], true);

    if(!isset($auth['me'])) {
      die('Auth endpoint returned invalid result');
    }

    // Make sure "me" returned is on the same domain
    $expectedHost = parse_url($_SESSION['expected_me'], PHP_URL_HOST);
    $actualHost = parse_url($auth['me'], PHP_URL_HOST);

    if($expectedHost != $actualHost) {
      die('A different user logged in');
    }

    unset($_SESSION['state']);

    return $this->_finishAuthenticate($response);
  }

}

