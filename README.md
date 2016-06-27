![image](https://843a2be0f3083c485676508ff87beaf088a889c0-www.googledrive.com/host/0B_r_WoIa581oY01QMWNVUElyM2M)

[PerimeterX](http://www.perimeterx.com) PHP SDK
=============================================================

Table of Contents
-----------------

-   [Usage](#usage)
  *   [Dependencies](#dependencies)
  *   [Installation](#installation)
  *   [Basic Usage Example](#basic-usage)
-   [Configuration](#configuration)
  *   [Blocking Score](#blocking-score)
  *   [Custom Block Action](#custom-block)
  *   [Enable/Disable Captcha](#captcha-support)
  *   [Extracting Real IP Address](#real-ip)
  *   [Filter Sensitive Headers](#sensitive-headers)
  *   [API Timeout Milliseconds](#api-timeout)
  *   [Send Page Activities](#send-page-activities)
  *   [Debug Mode](#debug-mode)
-   [Contributing](#contributing)
  *   [Tests](#tests)

<a name="Usage"></a>

<a name="dependencies"></a> Dependencies
----------------------------------------

-   [PHP >= v5.6](http://php.net/downloads.php)
-   [mcrypt](http://php.net/manual/en/book.mcrypt.php)



<a name="installation"></a> Installation
----------------------------------------

Installation can be done using composer

```sh
$ composer require perimeterx/php-sdk
```

Or by downoading the sources for this repository and run `composer install`

### <a name="basic-usage"></a> Basic Usage Example
```php
use Perimeterx\Perimeterx;

$perimeterxConfig = [
    'app_id' => 'APP_ID',
    'cookie_key' => 'COOKIE_SECRET',
    'auth_token' => 'AUTH_
    TOKEN',
    'blocking_score' => 60
];

/* Obtain PerimeterX SDK instance */
$px = Perimeterx::Instance($perimeterxConfig);

/* run verify at the beginning of a page request */
$px->pxVerify();
```
### <a name="configuration"></a> Configuration Options

#### Configuring Required Parameters

Configuration options are set in `$perimeterxConfig`

#### Required parameters:

- app_id
- cookie_key
- auth_token

##### <a name="blocking-score"></a> Changing the Minimum Score for Blocking

**default:** 70

```php
$perimeterxConfig = [
	..
    'blocking_score' => 75
    ..
]
```

#### <a name="custom-block"></a> Custom Blocking Actions
Setting a custom block handler customizes is done by creating a user function named `pxCustomBlockHandler` before running the verify and the SDK will execute it using `call_user_func('pxCustomBlockHandler', $pxCtx);`. 

Cusom handler should contain the action that is taken when a user visits with a high score. Common customizations are to present a reCAPTHA or custom branded block page.

**default:** pxBlockHandler - return HTTP status code 403 and serve the
Perimeterx block page.

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
function pxCustomBlockHandler($pxCtx) {
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();

    /* user defined logic comes here */
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```      

###### Examples

**Serve a Custom HTML Page**

```javascript
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
function pxCustomBlockHandler($pxCtx) {
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();
    $full_url = $pxCtx->getFullUrl();

    $html = '<div>Access to ' . $full_url . ' has been blocked.</div> ' +
                  '<div>Block reference - ' . $pxBlockUuid . ' </div> ' +
                  '<div>Block score - ' . $pxBlockScore . '</div>';

	//echo $html;
	header("Status: 403");
	die();
}

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

**Do Not Block, Monitor Only**

```javascript
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
function pxCustomBlockHandler($pxCtx) {
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();
    $full_url = $pxCtx->getFullUrl();

	/* user logic defined here */
	
	return;
 }

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="captcha-support"></a>Enable/disable captcha in the block page

By enabling captcha support, a captcha will be served as part of the block page giving real users the ability to answer, get score clean up and passed to the requested page.

**default: true**

```php
$perimeterxConfig = [
	..
    'captcha_enabled' => true
    ..
]
```

##### <a name="real-ip"></a>Extracting the Real User IP Address From HTTP Headers or by defining a function

In order to evaluate user's score properly, the PerimeterX module
requires the real socket ip (client IP address that created the HTTP
request). The user ip can be passed to the PerimeterX module using a custom user function defined before the verify.

**default with no predefined header:** `$_SERVER['REMOTE_ADDR']`

```javascript

/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
function pxCustomUserIP($pxCtx)
{
    $headers = getallheaders();

    /* using socket ip */
    $ip = $_SERVER['REMOTE_ADDR'];

    /* using ip from x-forwarded-for header */
    $xff = explode(",", $headers['X-Forwarded-For']);
    $ip = $xff[count($xff)-1];

    /* using ip from custom header */
    $ip = $headers['X-REAL-CLIENT-IP'];

    $pxCtx->setIp($ip);
}

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="sensitive-headers"></a> Filter sensitive headers

A user can define a list of sensitive header he want to prevent from being send to perimeterx servers, filtering cookie header for privacy is set by default and will be overriden if a user set the configuration

**default: cookie, cookies**

```php
$perimeterxConfig = [
	..
    'sensitive_headers' => ['cookie', 'cookies', 'secret-header']
    ..
]
```

#### <a name="api-timeout"></a>API Timeout Milliseconds

Timeout in seconds (float) to wait for the PerimeterX server API response.
The API is called when the risk cookie does not exist, or is expired or
invalid.

**default:** 1

```php
$perimeterxConfig = [
	..
    'api_timeout' => 2
    ..
]
```

#### <a name="send-page-activities"></a> Send Page Activities

Boolean flag to enable or disable sending activities and metrics to
PerimeterX on each page request. Enabling this feature will provide data
that populates the PerimeterX portal with valuable information such as
amount requests blocked and API usage statistics.

**default:** false

```php
$perimeterxConfig = [
	..
    'send_page_activities' => true
    ..
]
```

#### <a name="debug-mode"></a> Debug Mode

Enables debug logging

**default:** false

```php
$perimeterxConfig = [
	..
    'debug_mode' => true
    ..
]
```
<a name="contributing"></a> Contributing
----------------------------------------

