[![Build Status](https://travis-ci.org/PerimeterX/perimeterx-php-sdk.svg?branch=master)](https://travis-ci.org/PerimeterX/perimeterx-php-sdk)

![image](https://storage.googleapis.com/perimeterx-logos/primary_logo_red_cropped.png)

#

# [PerimeterX](http://www.perimeterx.com) PHP SDK

> Latest stable version: [v3.8.0](https://packagist.org/packages/perimeterx/php-sdk#3.8.0)

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
*   [Login Credentials Extraction](#login-credentials-extraction)
*   [Additional S2S Activity](#additional-s2s-activity)
*   [Logging](#logging)
*   [Module Mode](#module-mode)
*   [Debug Mode](#debug-mode)
*   [Guzzle Client Handler](#guzzle-client-handler)
*   [Custom Block URL](#custom-block-url)
*   [Defer Activities Sending](#defer-activities)
*   [Advanced Blocking Response Flag](#enable-abr)
*   [Return Response Flag](#return-response)
*   [Test Block Flow on Monitoring Mode](#bypass-monitor-header)

-   [Advanced Blocking Response](#advanced-blocking-response)
-   [Additional Information](#additional-information)
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

### <a name="login-credentials-extraction"></a> Login Credentials Extraction

This feature extracts credentials (hashed username and password) from requests and sends them to PerimeterX as additional info in the risk api call. The feature can be toggled on and off, and may be set for any number of unique paths. The settings are adjusted by modifying the `px_login_credentials_extraction_enabled` and `px_login_credentials_extraction` properties on the `$perimeterxConfig` array.

If credentials are found to be compromised, the field `px-compromised-credentials` will be added to the `$_REQUEST` object with the value `"1"`. You may configure the name of this field with the `px_compromised_credentials_header` configuration.

**Default:**

px_login_credentials_extraction_enabled: false

px_login_credentials_extraction: []

px_compromised_credentials_header: "px-compromised-credentials"

```php
$perimeterxConfig['px_compromised_credentials_header'] = 'px-comp-creds';
$perimeterxConfig['px_login_credentials_extraction_enabled'] = true;
$perimeterxConfig['px_login_credentials_extraction'] = [
    [
        "path" => "/login",           // login path, automatically added to sensitive routes
        "method" => "POST",           // supported methods: POST
        "sent_through" => "body",     // supported sent_throughs: body, header, query-param
        "pass_field" => "password",   // name of the password field in the request
        "user_field" => "username"    // name of the username field in the request
    ], [ ... ], ...
]
```

It is also possible to define a custom callback to extract the username and password. The function should return an associative
array with the keys `user` and `pass`. If extraction is unsuccessful, the function should return `null`.

```php
$perimeterxConfig['px_enable_login_creds_extraction'] = true;
$perimeterxConfig['px_login_creds_extraction'] = [
    [
        "path" => "/login",                 // login path
        "method" => "POST",                 // supported methods: POST
        "callback_name" => "extractCreds"   // name of custom extraction callback
    ], ...
];

function extractCreds() {
    // custom implementation resulting in $username and $password
    if (empty($username) || empty($password)) {
        return null;
    }
    return [
        "user" => $username,
        "pass" => $password
    ];
}
```

### <a name="additional-s2s-activity"></a> Additional S2S Activity

To enhance detection on login credentials extraction endpoints, the following additional information is sent to PerimeterX
via an `additional_s2s` activity:

* __Response Code__ - The numerical HTTP status code of the response. This is sent automatically.
* __Login Success__ - A boolean indicating whether the login completed successfully. See the options listed below for how to provide this data.
* __Raw Username__ - The original username used for the login attempt. In order to report this information, make sure the configuration `px_send_raw_username_on_additional_s2s_activity` is set to `true`.
#### Login Success Reporting

There are a number of different possible ways to report the success or failure of the login attempt. If left empty, the
login successful status will always be reported as `false`.

**Default**: Empty

```php
$perimeterxConfig['px_login_successful_reporting_method'] = 'status';
```

__Status__

Provide a status or array of statuses that represent a successful login. If a response's status code matches the provided
value or one of the values in the provided array, the login successful status is set to `true`. Otherwise, it's set to `false`.

> Note: To define a range of statuses, use the `custom` reporting method.

**Default Values**

px_login_successful_status: 200

```php
$perimeterxConfig['px_login_successful_reporting_method'] = 'status';
$perimeterxConfig['px_login_successful_status'] = [200, 202]; // number or array of numbers
```

__Header__

Provide a header name and value. If the header exists on the response (accessed via the `headers_list()` function ) and matches the provided value, the login successful status is set to `true`. If the header is not found on the response, or if the header value does not match the value in the configuration, the login successful status is set to `false`.

**Default Values**

px_login_successful_header_name: x-px-login-successful

px_login_successful_header_value: 1

```php
$perimeterxConfig['px_login_successful_reporting_method'] = 'header';
$perimeterxConfig['px_login_successful_header_name'] = 'login-successful';
$perimeterxConfig['px_login_successful_header_value'] = 'true';
```

__Custom__

Provide a custom callback that returns a boolean indicating if the login was successful. The value of the configuration field can be either an anonymous function or the name of the defined function as a string.

**Default Values**

px_login_successful_custom_callback: null

```php
$perimeterxConfig['px_login_successful_reporting_method'] = 'custom';

// anonymous callback function
$perimeterxConfig['px_login_successful_custom_callback'] = function() {
    // ...
    return $isLoginSuccessful;
};

// name of defined function as string
$perimeterxConfig['px_login_successful_custom_callback'] = 'isLoginSuccessfulCallback';

function isLoginSuccessfulCallback() {
    // ...
    return $isLoginSuccessful;
}
```

#### Raw Username

When enabled, the raw username used for logins on login credentials extraction endpoints will be reported to PerimeterX
if (1) the credentials were identified as compromised, and (2) the login was successful as reported via the property above.

**Default**: false

```php
$perimeterxConfig['px_send_raw_username_on_additional_s2s_activity'] = true;
```

#### Manually Sending Additional S2S Activity

By default, this `additional_s2s` activity is sent automatically. If it is preferable to send this activity manually,
it's possible to disable automatic sending by configuring the value of `px_automatic_additional_s2s_activity_enabled` to `false`.

**Default Value*: true

```php
$perimeterxConfig['px_automatic_additional_s2s_activity_enabled'] = false;
```


The activity can then be sent manually by invoking the function `$px->pxSendAdditionalS2SActivity()`, which accepts the following parameters:

| Parameter Name | Type | Required | Default Value |
| :--            | :--  | :--      | :-- |
| `$responseStatusCode` | int | yes | n/a |
| `$wasLoginSuccessful` | bool | no | null |

__Example Usage__

```php
// $px is an instance of the Perimeterx class

function handleLogin() {
    // login flow resulting in boolean $isLoginSuccessful
    $px->pxSendAdditionalS2SActivity($isLoginSuccessful ? 200 : 401, $isLoginSuccessful);
}
```

If further flexibility is needed, a JSON representation of the `additional_s2s` activity can be added to the `$_REQUEST` array. This activity can then be sent to another server if needed, parsed, modified, and sent via XHR POST as a JSON to PerimeterX. To do this, disable automatic sending and enable the additional activity header configuration.

**Default Value**

px_additional_s2s_activity_header_enabled: false

```php
$perimeterxConfig['px_automatic_additional_s2s_activity_enabled'] = false;
$perimeterxConfig['px_additional_s2s_activity_header_enabled'] = true;
```

The activity payload and URL destination will then be available by accessing `$_REQUEST['px-additional-activity']` and `$_REQUEST['px-additional-activity-url']`, respectively.

```php
function handleLogin() {
    // custom flow resulting in boolean $isLoginSuccessful
    $activity = json_decode($_REQUEST['px-additional-activity'], true);
    $activity['additional']['http_status_code'] = http_status_code();
    $activity['additional']['login_successful'] = $isLoginSuccessful;

    if ($isLoginSuccessful && $activity['additional']['credentials_compromised']) {
        $activity['additional']['raw_username'] = $_REQUEST['username'];
    }

    $url = $_REQUEST['px-additional-activity-url'];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $_ENV['PX_AUTH_TOKEN']
    ];
    $body = json_encode($activity);

    sendPostRequest($url, $headers, $body);
}

function sendPostRequest($url, $headers, $body) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    curl_exec($curl);
}
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

#### <a name="enable-abr"></a> Advanced Blocking Response Flag

Enables/disables the Advanced Blocking Response functionality.

**Default:** false

```php
$perimeterxConfig = [
    ..
    'enable_json_response' => true
    ..
]
```

#### <a name="return-response"></a> Return Response Flag

Enables/disables the ability to return the response back (useful for frameworks like Symfony) instead of running `die()`.

**Default:** false

```php
$perimeterxConfig = [
    ..
    'return_response' => true
    ..
]
```

#### <a name="defer-activities"></a> Defer Activities Sending

Specifies if sending page activities should be deferred until shutdown or not.

**Default:** true

```php
$perimeterxConfig = [
    ..
    'defer_activities' => false
    ..
]
```


#### <a name="bypass-monitor-header"></a> Test Block Flow on Monitoring Mode

Allows you to test an enforcer’s blocking flow while you are still in Monitor Mode.

When the header name is set (eg. `x-px-block`) and the value is set to `1`, when there is a block response (for example from using a User-Agent header with the value of `PhantomJS/1.0`) the Monitor Mode is bypassed and full block mode is applied. If one of the conditions is missing you will stay in Monitor Mode. This is done per request.
To stay in Monitor Mode, set the header value to `0`.

The Header name is configurable using the `bypass_monitor_header` property.

**Default:** not set

```php
$perimeterxConfig = [
    ..
    'bypass_monitor_header' => 'x-px-block'
    ..
]
```

## <a name="advanced-blocking-response"></a> Advanced Blocking Response

In special cases, (such as XHR post requests) a full Captcha page render might not be an option. In such cases, using the Advanced Blocking Response returns a JSON object continaing all the information needed to render your own Captcha challenge implementation, be it a popup modal, a section on the page, etc. The Advanced Blocking Response occurs when a request contains the _Accept_ header with the value of `application/json`. A sample JSON response appears as follows:

```javascript
{
    "appId": String,
    "jsClientSrc": String,
    "firstPartyEnabled": Boolean,
    "vid": String,
    "uuid": String,
    "hostUrl": String,
    "blockScript": String
}
```

Once you have the JSON response object, you can pass it to your implementation (with query strings or any other solution) and render the Captcha challenge.

In addition, you can add the `_pxOnCaptchaSuccess` callback function on the window object of your Captcha page to react according to the Captcha status. For example when using a modal, you can use this callback to close the modal once the Captcha is successfullt solved. <br/> An example of using the `_pxOnCaptchaSuccess` callback is as follows:

```javascript
window._pxOnCaptchaSuccess = function (isValid) {
    if (isValid) {
        alert('yay');
    } else {
        alert('nay');
    }
};
```

To enable Advanced Blocking Response see the [Advanced Blocking Response Flag](#enable-abr) section.

For details on how to create a custom Captcha page, refer to the [documentation](https://docs.perimeterx.com/pxconsole/docs/customize-challenge-page)

## <a name=“additional-information”></a> Additional Information

### URI Delimiters

PerimeterX processes URI paths with general- and sub-delimiters according to RFC 3986. General delimiters (e.g., `?`, `#`) are used to separate parts of the URI. Sub-delimiters (e.g., `$`, `&`) are not used to split the URI as they are considered valid characters in the URI path.

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
