<?php


function check_authentication($auth_config) {
  $email = $username = $displayname = '';
  $res = authenticate($auth_config, $username, $displayname, $email);
  if ($res) {
    setcookie("authentication[username]", $username);
    setcookie("authentication[displayname]", $displayname);
    setcookie("authentication[email]", $email);
  }
  return $res;
}

function authenticate ($auth_config, &$username, &$displayname, &$email) {
  if (! file_exists ($auth_config['simplesamlphp']['simplesamlphp_basedir'] . '/lib/_autoload.php'))
    die('Configured for SAML authentication, but simplesaml is not found.');
  require_once ($auth_config['simplesamlphp']['simplesamlphp_basedir'] . '/lib/_autoload.php');
  $as = new SimpleSAML_Auth_Simple ($auth_config['simplesamlphp']['sp_profile']);
  if (! $as->isAuthenticated()) {
    $as->requireAuth();
  }

  $SAML_options = $auth_config['simplesamlphp']['SAML_options'];
  $attributes = $as->getAttributes();

  $username = saml_getAttributeValue ($attributes, $SAML_options['usernameAttribute']);
  $displayname = saml_getAttributeValue ($attributes, $SAML_options['fullnameAttribute']);
  $email = saml_getAttributeValue($attributes, $SAML_options['emailAttribute']);

  return $as->isAuthenticated();
}

/**
*   Destroy all cookies and sessions.
**/
function logout () {
  /*if (! file_exists ($SAML_options['simplesamlphp_basedir'] . '/lib/_autoload.php'))
    throw new RackTablesError ('Configured for SAML authentication, but simplesaml is not found.');
  require_once ($SAML_options['simplesamlphp_basedir'] . '/lib/_autoload.php');
  $as = new SimpleSAML_Auth_Simple ($SAML_options['sp_profile']);
  */
  $past = time() - 3600;
  foreach ( $_COOKIE as $key => $value ) {
    setcookie( $key, $value, $past, '/' );
  }
  session_destroy();
  header("Location: ".$ROOT_URL);
  exit;
}

function saml_getAttributeValue ($attributes, $name) {
  if (! isset ($attributes[$name]))
    return '';
  return is_array ($attributes[$name]) ? $attributes[$name][0] : $attributes[$name];
}

function saml_getAttributeValues ($attributes, $name) {
  if (! isset ($attributes[$name]))
    return array();
  return is_array ($attributes[$name]) ? $attributes[$name] : array($attributes[$name]);
}

?>