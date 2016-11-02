![image](http://media.marketwire.com/attachments/201604/34215_PerimeterX_logo.jpg)

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
  *   [Custom URI](#custom-uri)
  *   [Filter Sensitive Headers](#sensitive-headers)
  *   [API Timeouts](#api-timeout)
  *   [Send Page Activities](#send-page-activities)
  *   [Custom Page Activity Handler](#custom-page-activity-handler)
  *   [Debug Mode](#debug-mode)
-   [Contributing](#contributing)
  *   [Tests](#tests)

<a name="Usage"></a>

<a name="dependencies"></a> Dependencies
----------------------------------------

-   [PHP >= v5.5](http://php.net/downloads.php)
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

#### <a name="blocking-score"></a> Changing the Minimum Score for Blocking

**default:** 70

```php
$perimeterxConfig = [
	..
    'blocking_score' => 75
    ..
]
```

#### <a name="custom-block"></a> Custom Blocking Actions
Setting a custom block handler customizes is done by setting 'custom_block_handler' with a user function named on the '$perimeterxConfig'.

Custom handler should contain the action that is taken when a user visits with a high score. Common customizations are to present a reCAPTHA or custom branded block page.

**default:** return HTTP status code 403 and serve the Perimeterx block page.

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_block_handler'] = function ($pxCtx)
{
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();

    // user defined logic comes here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```      

###### Examples

**Serve a Custom HTML Page**

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_block_handler'] = function ($pxCtx)
{
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();
    $full_url = $pxCtx->getFullUrl();

    $html = '<div>Access to ' . $full_url . ' has been blocked.</div> ' +
                  '<div>Block reference - ' . $pxBlockUuid . ' </div> ' +
                  '<div>Block score - ' . $pxBlockScore . '</div>';

	//echo $html;
	header("Status: 403");
	die();
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

**Do Not Block, Monitor Only**

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_block_handler'] = function ($pxCtx)
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();
    $full_url = $pxCtx->getFullUrl();

	// user logic defined here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="module-score"></a> Module Mode

**default:** `Perimeterx::$ACTIVE_MODE`

**Possible Values:**

- `Perimeterx::$ACTIVE_MODE` - Module block user crossing the block threshold, server-to-server requests are being sent synchrouniously
- `Perimeterx::$MONITOR_MODE` - Module does not block users crossing the block threshold, but does eval the pxCustomBlockHandler function in case it's defined on score threshold cross.

```php
$perimeterxConfig = [
	..
    'module_mode' => Perimeterx::$MONITOR_MODE
    ..
]
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

#### <a name="real-ip"></a>Extracting the Real User IP Address

> Note: IP extraction according to your network setup is important. It is common to have a load balancer/proxy on top of your applications, in this case the PerimeterX module will send an internal IP as the user's. In order to perform processing and detection for server-to-server calls, PerimeterX module need the real user ip.

The user ip can be returned to the PerimeterX module using a custom user function defined on $perimeterxConfig.

**default with no predefined header:** `$_SERVER['REMOTE_ADDR']`

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_user_ip'] = function ($pxCtx)
{
    $headers = getallheaders();

    /* using socket ip */
    $ip = $_SERVER['REMOTE_ADDR'];

    /* using ip from x-forwarded-for header */
    $xff = explode(",", $headers['X-Forwarded-For']);
    $ip = $xff[count($xff)-1];

    /* using ip from custom header */
    $ip = $headers['X-REAL-CLIENT-IP'];

    return $ip;
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="custom-uri"></a>Custom URI

The URI can be returned to the PerimeterX module using a custom user function defined on $perimeterxConfig.

**default:** `$_SERVER['REQUEST_URI']`

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_uri'] = function ($pxCtx)
{
    return $_SERVER['HTTP_X_CUSTOM_URI'];
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="sensitive-headers"></a> Filter sensitive headers

A user can define a list of sensitive header he want to prevent from being send to perimeterx servers (lowered case header name), filtering cookie header for privacy is set by default and will be overridden if a user set the configuration

**default: cookie, cookies**

```php
$perimeterxConfig = [
	..
    'sensitive_headers' => ['cookie', 'cookies', 'secret-header']
    ..
]
```

#### <a name="api-timeout"></a>API Timeouts

Control the timeouts for PerimeterX requests. The API is called when the risk cookie does not exist, or is expired or invalid.

API Timeout in seconds (float) to wait for the PerimeterX server API response.


**default:** 1

```php
$perimeterxConfig = [
	..
    'api_timeout' => 2
    ..
]
```

API Connection Timeout in seconds (float) to wait for the connection to the PerimeterX server API.


**default:** 1

```php
$perimeterxConfig = [
  ..
    'api_connect_timeout' => 2
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

#### <a name="custom-page-activity-handler"></a> Custom Page Activity Handler

Setting a custom activity handler is done by setting 'custom_activity_handler' with a user function named on the '$perimeterxConfig'.

**default:** send activity to PerimeterX as controlled by '$perimeterxConfig'.

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['custom_activity_handler'] = function ($activityType, $pxCtx, $details)
{
    // user defined logic comes here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

###### Examples

**Log Activity**

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['custom_activity_handler'] = function ($activityType, $pxCtx, $details) use ($logger)
{
    if ($activityType === 'block') {
        $logger->warning('PerimeterX {activityType} details', ['activityType' => $activityType, 'details' => $details]);
    } else {
        $logger->info('PerimeterX {activityType} details', ['activityType' => $activityType, 'details' => $details]);
    }
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

**Send Activity to statsd**

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['custom_activity_handler'] = function ($activityType, $pxCtx, $details) use ($statsd)
{
    $statsd->increment('perimeterx_activity.' . $activityType);
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

**Send Activity to statsd & PerimeterX**

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['custom_activity_handler'] = function ($activityType, $pxCtx, $details) use ($statsd, $pxActivitiesClient)
{
    $statsd->increment('perimeterx_activity.' . $activityType);

    $pxActivitiesClient->sendToPerimeterx($activityType, $pxCtx, $details);
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
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
