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
            'app_id' => 'PX_APP_ID',
            'cookie_key' => 'PX_COOKIE_KEY',
            'auth_token' => 'PX_AUTH_TOKEN',
            'blocking_score' => 80,
            'captcha_enabled' => false
        ];
    }

    protected function tearDown()
    {
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
        $this->assertEquals($pxConfig['perimeterx_server_host'], 'https://sapi-px_app_id.perimeterx.net');
    }

    public function testPxConfigurationCustomization()
    {
        $customParams = array_merge([
            'css_ref' => 'http://www.google.com/stylesheet.css',
            'js_ref' => 'http://www.google.com/script.js',
            'custom_logo' => 'http://www.google.com/logo.png',
        ], $this->params);

        $this->px = Perimeterx::Instance($customParams);
        $pxConfig = $this->px->getPxConfig();

        $this->assertEquals($pxConfig['custom_logo'], 'http://www.google.com/logo.png');
        $this->assertEquals($pxConfig['js_ref'], 'http://www.google.com/script.js');
        $this->assertEquals($pxConfig['css_ref'], 'http://www.google.com/stylesheet.css');
    }

    public function testPxConfigurationSensitiveRoutePrefix()
    {
      $customParams = array_merge([
        'sensitive_routes' => ['/','/login']
      ], $this->params);

      $this->px = Perimeterx::Instance($customParams);
      $pxConfig = $this->px->getPxConfig();

      $this->assertArrayHasKey('sensitive_routes', $customParams);
    }

}


?>
