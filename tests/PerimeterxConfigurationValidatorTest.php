<?php

  use PHPUnit\Framework\TestCase;
  use Perimeterx\Perimeterx;

  class PerimeterxConfigurationValidatorTest extends PHPUnit_Framework_TestCase
  {

    protected $params;
    protected $px;

    protected function setUp()
    {
      $this->params = [
        'app_id' => 'PXMI1FuMjS',
        'cookie_key' => 'KONhrO4h2efKU+998WsKSL/K8WvmafI5tFnp16xYiQjhOd9g8AEqjIKlA+vvQwhY',
        'auth_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzY29wZXMiOlsicmlza19zY29yZSIsInJlc3RfYXBpIl0sImlhdCI6MTQ4ODI3MTQyMSwic3ViIjoiUFhNSTFGdU1qUyIsImp0aSI6IjVmZWRhZGQwLTYwNzItNGUxOS1iN2MxLTdmMjk1ZWIwMDIwNSJ9.PykF4OCofWs0dL5uTOdDw2pHCJ9WalevUOUL366Me2o',
        'blocking_score' => 80,
        'captcha_enabled' => false
      ];

    }

    protected function tearDown(){
      $reflection = new ReflectionClass($this->px);
      $instance = $reflection->getProperty('instance');
      $instance->setAccessible(true);
      $instance->setValue(null, null);
      $instance->setAccessible(false);
    }


    public function testPxConfigurationAPIPath()
    {
      $this->px = Perimeterx::Instance($this->params);
      $pxConfig = $this->px->getPxConfig();

      $this->assertEquals($pxConfig['perimeterx_server_host'],'https://sapi-pxmi1fumjs.perimeterx.net');
    }

    public function testPxConfigurationCustomization()
    {
      $customParams = array_merge([
        'css_ref' => 'http://www.google.com/stylesheet.css',
        'js_ref' => 'http://www.google.com/script.js',
        'custom_logo' => 'http://www.google.com/logo.png',
      ],$this->params);

      $this->px = Perimeterx::Instance($customParams);
      $pxConfig = $this->px->getPxConfig();

      $this->assertEquals($pxConfig['custom_logo'],'http://www.google.com/logo.png');
      $this->assertEquals($pxConfig['js_ref'],'http://www.google.com/script.js');
      $this->assertEquals($pxConfig['css_ref'],'http://www.google.com/stylesheet.css');
    }

  }



?>
