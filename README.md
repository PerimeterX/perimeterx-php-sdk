[![Build Status](https://travis-ci.org/PerimeterX/perimeterx-php-sdk.svg?branch=master)](https://travis-ci.org/PerimeterX/perimeterx-php-sdk)

![image](https://storage.googleapis.com/perimeterx-logos/primary_logo_red_cropped.png)

#

# [PerimeterX](http://www.perimeterx.com) PHP SDK

> Latest stable version: [v3.5.3](https://packagist.org/packages/perimeterx/php-sdk#3.5.3)

## Table of Contents

-   [Usage](#usage)

*   [Dependencies](#dependencies)
*   [Installation](#installation)
*   [Basic Usage Example](#basic-usage)

-   [Upgrading](#upgrade)
-   [Configuration](#configuration)

*   [Blocking Score](#blocking-score)
*   [Extracting Recomended Action](#block-action)
*   [Custom Block Page](#custom-block-page)
*   [Custom Block Action](#custom-block)
*   [Extracting Real IP Address](#real-ip)
*   [Custom URI](#custom-uri)
*   [Filter Sensitive Headers](#sensitive-headers)
*   [Sensitive Route](#sensitive-routes)
*   [API Timeouts](#api-timeout)
*   [Activities API Timeouts](#activities-api-timeout)
*   [Send Page Activities](#send-page-activities)
*   [Additional Page Activity Handler](#additional-page-activity-handler)
*   [Data-Enrichment](#data-enrichment)
*   [Enrich Custom Params](#enrich-custom-params)
*   [Logging](#logging)
*   [Module Mode](#module-mode)
*   [Debug Mode](#debug-mode)
*   [Guzzle Client Handler](#guzzle-client-handler)
*   [Custom Block URL](#custom-block-url)
*   [Defer Activities Sending](#defer-activities)
*   [Test Block Flow on Monitoring Mode](#bypass-monitor-header)

-   [Contributing](#contributing)

*   [Tests](#tests)

<a name="Usage"></a>

## <a name="dependencies"></a> Dependencies

-   [v5.6 <= PHP <= v7.0.15](http://php.net/downloads.php)

## <a name="installation"></a> Installation

Installation can be done using Composer.

```sh
$ composer require perimeterx/php-sdk
```

It can also be done by downloading the sources for this repository, and running `composer install`.

### <a name="basic-usage"></a> Basic Usage Example

```php
use Perimeterx\Perimeterx;

$perimeterxConfig = [
    'app_id' => 'APP_ID',
    'cookie_key' => 'COOKIE_SECRET',
    'auth_token' => 'AUTH_TOKEN',
    'blocking_score' => 60,
    'module_mode' => Perimeterx::$ACTIVE_MODE
];

/* Obtain PerimeterX SDK instance */
$px = Perimeterx::Instance($perimeterxConfig);

/* run verify at the beginning of a page request */
$px->pxVerify();
```

## <a name="upgrade"></a> Upgrading

Download the new version from packagist.

For more information contact [PerimeterX Support](support@perimeterx.com).

## <a name="configuration"></a> Configuration Options

### Configuring Required Parameters

Configuration options are set on the `$perimeterxConfig` variable.

#### Required parameters:

-   app_id
-   cookie_key
-   auth_token
-   module_mode

All parameters are obtainable via the PerimeterX Portal. (Applications and Policies pages)

#### <a name="blocking-score"></a> Changing the Minimum Score for Blocking

**Default blocking value:** 100

```php
$perimeterxConfig = [
	..
    'blocking_score' => 75
    ..
]
```

#### <a name="custom-block"></a> Custom Blocking Actions

In order to customize the action performed on a valid block value, use the 'custom_block_handler' option, and provide a user-defined function.

The custom handler should contain the action to be taken, when a visitor receives a score higher than the 'blocking_score' value.
Common customization options are presenting of a reCAPTCHA, or supplying a custom branded block page.

**Default block behaviour:** return an HTTP status code of 403 and serve the PerimeterX block page.

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_block_handler'] = function ($pxCtx)
{
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();

    // user defined logic goes here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

###### Examples

**Serving a Custom HTML Page**

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
                  '<div>Block reference - ' . $block_uuid . ' </div> ' +
                  '<div>Block score - ' . $block_score . '</div>';

    //echo $html;
    header("Status: 403");
    die();
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

## <a name="custom-block-page"></a> Customizing Default Block Pages

**Custom logo insertion**
Adding a custom logo to the blocking page is by providing the pxConfig a key `custom_logo` , the logo will be displayed at the top div of the the block page
The logo's `max-heigh` property would be 150px and width would be set to `auto`

The key `custom_logo` expects a valid URL address such as `https://s.perimeterx.net/logo.png`

Example below:

```php
$perimeterxConfig = [
    'app_id' => 'APP_ID',
    'cookie_key' => 'COOKIE_SECRET',
    'auth_token' => 'AUTH_TOKEN',
    'blocking_score' => 60,
    'custom_logo' => 'LOGO_URL'
];
```

** Custom JS/CSS **

The block page can be modified with a custom CSS by adding to the `pxConfig` the key `css_ref` and providing a valid URL to the css
In addition there is also the option to add a custom JS file by adding `js_ref` key to the `pxConfig` and providing the JS file that will be loaded with the block page, this key also expects a valid URL

On both cases if the URL is not a valid format an exception will be thrown

Example below:

```php
$perimeterxConfig = [
    'app_id' => 'APP_ID',
    'cookie_key' => 'COOKIE_SECRET',
    'auth_token' => 'AUTH_TOKEN',
    'blocking_score' => 60,
    'css_ref' => 'CSS_URL',
    'js_ref' => 'JS_URL'
];
```

Side notes: Custom logo/js/css can be added together

**No Blocking, Monitor Only**

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_block_handler'] = function ($pxCtx)
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();
    $full_url = $pxCtx->getFullUrl();

    // user defined logic goes here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

<a name="block-action"></a>**Extracting Recomended Action**

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_block_handler'] = function ($pxCtx) {
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();
    $action = $pxCtx->getBlockAction();

    /* user defined logic comes here */
    error_log('px score for user is ' . $block_score);
    error_log('px recommended action for user is ' . $action);
    error_log('px page uuid is ' . $block_uuid);

    switch ($action) {
        case "block":
            log("do block");
            break;
        case "captcha":
            log("do captcha");
            break;
        default:
            log("unknown action");
        }
    }
```

#### <a name="module-mode"></a> Module Mode

**Default mode:** `Perimeterx::$MONITOR_MODE`

**Possible Values:**

-   `Perimeterx::$ACTIVE_MODE` - Module blocks users crossing the predefined block threshold. Server-to-server requests are sent synchronously.
-   `Perimeterx::$MONITOR_MODE` - Module does not block users crossing the predefined block threshold. The pxCustomBlockHandler function will be eval'd in case one is supplied, upon crossing the defined block threshold.

```php
$perimeterxConfig = [
	..
    'module_mode' => Perimeterx::$ACTIVE_MODE
    ..
]
```

#### <a name="real-ip"></a>Extracting the Real User IP Address

> Note: IP extraction, according to your network setup, is very important. It is common to have a load balancer/proxy on top of your applications, in which case the PerimeterX module will send the system's internal IP as the user's. In order to properly perform processing and detection on server-to-server calls, PerimeterX module needs the real user's IP.

The user's IP can be passed to the PerimeterX module using a custom user defined function on the $perimeterxConfig variable, or by passing a list of headers to extract the real IP from, ordered by priority.

**Default with no predefined header:** `$_SERVER['REMOTE_ADDR']`

```php
/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
$perimeterxConfig['custom_user_ip'] = function ($pxCtx)
{
    $headers = getallheaders();

    /* using a socket ip */
    $ip = $_SERVER['REMOTE_ADDR'];

    /* using an ip from a x-forwarded-for header */
    $xff = explode(",", $headers['X-Forwarded-For']);
    $ip = $xff[count($xff)-1];

    /* using an ip from a custom header */
    $ip = $headers['X-REAL-CLIENT-IP'];

    return $ip;
};

$perimeterxConfig = [
	..
    'ip_headers' => ['X-TRUE-IP', 'X-Forwarded-For']
    ..
]

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="custom-uri"></a>Custom URI

The URI can be returned to the PerimeterX module, using a custom user function, defined on the $perimeterxConfig variable.

**Default:** `$_SERVER['REQUEST_URI']`

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

A list of sensitive headers can be configured to prevent specific headers from being sent to PerimeterX servers (lower case header names). Filtering cookie headers for privacy is set by default, and can be overridden on the $perimeterxConfig variable.

**Default: cookie, cookies**

```php
$perimeterxConfig = [
	..
    'sensitive_headers' => ['cookie', 'cookies', 'secret-header']
    ..
]
```

#### <a name="sensitive-routes"></a> Sensitive Routes

List of routes prefix. The Perimeterx module will always match request uri by this prefix list and if match was found will create a server-to-server call for, even if the cookie score is low and valid.

**Default: None**

```php
$perimeterxConfig = [
	..
    'sensitive_routes' => ['/login', '/user/profile']
    ..
]
```

#### <a name="api-timeout"></a>API Timeouts

> Note: Controls the timeouts for PerimeterX requests. The API is called when a Risk Cookie does not exist, or is expired or invalid.

The API Timeout, in seconds (float), to wait for the PerimeterX server API response.

**Default:** 1

```php
$perimeterxConfig = [
	..
    'api_timeout' => 2
    ..
]
```

The API Connection Timeout, in seconds (float), to wait for the connection to the PerimeterX server API.

**Default:** 1

```php
$perimeterxConfig = [
  ..
    'api_connect_timeout' => 2
    ..
]
```

#### <a name="activities-api-timeout"></a>Activities API Timeouts

> Note: Controls the timeouts for PerimeterX activities requests.

The activities API Timeout, in seconds (float), to wait for the PerimeterX server API response.

**Default:** 1

```php
$perimeterxConfig = [
  ..
    'activities_timeout' => 2
    ..
]
```

The activities API Connection Timeout, in seconds (float), to wait for the connection to the PerimeterX server API.

**Default:** 1

```php
$perimeterxConfig = [
  ..
    'activities_connect_timeout' => 2
    ..
]
```

#### <a name="send-page-activities"></a> Send Page Activities

A boolean flag to enable or disable sending of activities and metrics to
PerimeterX on each page request. Enabling this feature will provide data
that populates the PerimeterX portal with valuable information, such as the
amount of requests blocked and additional API usage statistics.

**Default:** false

```php
$perimeterxConfig = [
	..
    'send_page_activities' => true
    ..
]
```

#### <a name="additional-page-activity-handler"></a> Additional Page Activity Handler

Adding an additional activity handler is done by setting 'additional_activity_handler' with a user defined function on the '$perimeterxConfig' variable. The 'additional_activity_handler' function will be executed before sending the data to the PerimeterX portal.

**Default:** Only send activity to PerimeterX as controlled by '$perimeterxConfig'.

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['additional_activity_handler'] = function ($activityType, $pxCtx, $details)
{
    // user defined logic comes here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

###### Additional Activity Handler Usage Examples

**Log Activity**

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['additional_activity_handler'] = function ($activityType, $pxCtx, $details) use ($logger)
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
$perimeterxConfig['additional_activity_handler'] = function ($activityType, $pxCtx, $details) use ($statsd)
{
    $statsd->increment('perimeterx_activity.' . $activityType);
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="data-enrichment"></a> Data-Enrichment

User can use the additional activity handler to retrieve information for the request using the data-enrichment object.
first, validate the data enrichment object is verified, then you can access it's properties.

**Default:** false

```php
/**
 * @param string            $activityType
 * @param PerimeterxContext $pxCtx
 * @param array             $details
 */
$perimeterxConfig['additional_activity_handler'] = function ($activityType, $pxCtx, $details) use ($logger)
{
    if($pxCtx->getDataEnrichmentVerified()) {
    	$pxde = $pxCtx->getDataEnrichment();
        if($pxde->f_type == 'blacklist') {
        	$logger->info('Filtered request with id: {$pxde->f_id} at: {$pxde->timestamp}');
        }
    }
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

#### <a name="enrich-custom-params"></a> Enrich Custom Params

With the `enrich_custom_params` function you can add up to 10 custom parameters to be sent back to PerimeterX servers.
When set, the function is called before seting the payload on every request to PerimetrX servers. The parameters should be passed according to the correct order (1-10).

**Default:** not set

```php
/**
 * @param array             $customParamsArray
 */
$perimeterxConfig['enrich_custom_params'] = function ($customParamsArray)
{
    // user defined logic comes here
};

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();
```

###### Enrich Custom Params Usage Examples

```php
/**
 * @param array             $customParamsArray
 */
$perimeterxConfig['enrich_custom_params'] = function ($customParamsArray)
{
    $customParamsArray['custom_param1'] = "UserId";
    $customParamsArray['custom_param2'] = "SesionId";
    return $customParamsArray;
};
```

#### <a name="logging"></a> Logging

Log messages via an implementation of `\Psr\Log\LoggerInterface` (see [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) for full interface specification). By default, an instance of `\Perimeterx\PerimeterxLogger` is used which will log all message via PHP's `error_log` function.

**Default:** `\Perimeterx\PerimeterxLogger` instance

```php
$perimeterxConfig = [
    ..
    'logger' => new \My\Psr\Log\ConcreteLogger()
    ..
]
```

#### <a name="debug-mode"></a> Debug Mode

Enables debug logging mode.

**Default:** false

```php
$perimeterxConfig = [
	..
    'debug_mode' => true
    ..
]
```

Once enabled, debug messages coming out from PerimeterX should be in the following template:

`[PerimeterX - DEBUG][APP_ID] - MESSAGE` - for debug messages

`[PerimeterX - ERROR][APP_ID] - MESSAGE` - for error messages

An example
log for an high score cookie:

```
[Mon Dec  4 14:03:50 2017] [PerimeterX - DEBUG][APP_ID] -Starting request verification
[Mon Dec  4 14:03:50 2017] [PerimeterX - DEBUG][APP_ID] -Request context created successfully
[Mon Dec  4 14:03:50 2017] [PerimeterX - DEBUG][APP_ID] -No Captcha cookie present on the request
[Mon Dec  4 14:03:50 2017] [PerimeterX - DEBUG][APP_ID] -Cookie V3 found, Evaluating
[Mon Dec  4 14:03:50 2017] [PerimeterX - DEBUG][APP_ID] -Cookie evaluation ended successfully, risk score: 100
[Mon Dec  4 14:03:51 2017] [PerimeterX - DEBUG][APP_ID] -Enforcing action: Captcha page is served
```

#### <a name="guzzle-client-handler"></a> Guzzle Client Handler

Allows setting a handler to the Guzzle client object.

**Default:** false

```php

$container = [];
$history = Middleware::history($container);
$handler = HandlerStack::create();
$handler->push($history);


$perimeterxConfig = [
    ..
    'guzzle_handler' => $handler
    ..
]
```

#### <a name="custom-block-url"></a> Custom Block URL

You can customize the block page to meet branding and message requirements by specifying the URL of the block page HTML file.
The enforcer will redirect to the block page defined in the `custom_block_url` variable. The defined block page will display a 307 (Temporary Redirect) HTTP Response Code.

**Default:** not set

```php

$perimeterxConfig = [
    ..
    'custom_block_url' => '/block.html'
    ..
]
```

#### <a name="defer-activities"></a> Defer Activities Sending

Specifies if sending page activities should be deferred until shutdown or not.

**Default:** true

````php
$perimeterxConfig = [
    ..
    'defer_activities' => false
    ..
]



#### <a name=“bypass-monitor-header”></a> Test Block Flow on Monitoring Mode

Allows you to test an enforcer’s blocking flow while you are still in Monitor Mode.

When the header name is set(eg. `x-px-block`) and the value is set to `1`, when there is a block response (for example from using a User-Agent header with the value of `PhantomJS/1.0`) the Monitor Mode is bypassed and full block mode is applied. If one of the conditions is missing you will stay in Monitor Mode. This is done per request.
To stay in Monitor Mode, set the header value to `0`.

The Header name is configurable using the `bypass_monitor_header` property.

**Default:** not set

```php
$perimeterxConfig = [
    ..
    'bypass_monitor_header' => 'x-px-block'
    ..
]
````

## <a name="contributing"></a> Contributing

The following steps are welcome when contributing to our project.

### Fork/Clone

First and foremost, [Create a fork](https://guides.github.com/activities/forking/) of the repository, and clone it locally.
Create a branch on your fork, preferably using a self descriptive branch name.

### Code/Run

Help improve our project by implementing missing features, adding capabilites or fixing bugs.

To run the code, simply follow the steps in the [installation guide](#installation). Grab the keys from the PerimeterX Portal, and try refreshing your page several times continously. If no default behaviours have been overriden, you should see the PerimeterX block page. Solve the CAPTCHA to clean yourself and start fresh again.

Feel free to check out the [Example App](https://github.com/PerimeterX/perimeterx-php-sdk/blob/master/examples/integration-example.php), to have a feel of the project.

### <a name="tests"></a>Test

> Tests for this project are written using PHPUnit.

**Dont forget to test**. The project relies heavily on tests, thus ensuring each user has the same experience, and no new features break the code.
Before you create any pull request, make sure your project has passed all tests, and if any new features require it, write your own.

To run any of the tests in the available suite, first open the `bootstrap.php.dist` file, and change the values according to the in-file insturctions. Then, rename the `bootstrap.php.dist` to `bootstrap.php`.
Finally, run the `phpunit tests/PerimeterxCookieValidatorTest` command to run all tests, or `phpunit <testName>` to execute a specific test (e.g. `phpunit PerimeterxCookieTest`)

To run coverage tests, run `phpunit --coverage-html tests/coverage`. This will create a directory tests/coverage with an html coverage for inspection.

### Pull Request

After you have completed the process, create a pull request to the Upstream repository. Please provide a complete and thorough description, explaining the changes. Remember this code has to be read by our maintainers, so keep it simple, smart and accurate.
