# Implementing captcah block page

In order to create customize captcha block page that will work with PerimeterX Captcha API you should follow the next guidlines: 

* [Custom block handler](#blockhandler): Implementing custom block handler using `perimeterx-php-sdk` that will extract all relevant data.
* [reCaptcha](#recaptcha): Adding reCaptcha to your block page.
* [JS captcha handler](#captchahandler): Implementing reCaptcha function that will handle captcha API response.
* [PerimeterX Snippet](#pxsnippet): Adding PerimeterX snippet to the block page.


<a name="blockhandler"></a>
### Custom block handler

Block handler should extract some data from pxContext and send it to the block page:

1. URL - the original URL that the user tried to reach using `$pxContext-> getURI()`.
2. VID - using `$pxContext->getVid()`.

The block handler should redirect the user to the block page and send the collected data with the request for instance. One way of doing so is to add query params to the block page url.

##### Example:

```
$pxConfig['custom_block_handler'] = function($pxCtx) {
    $vid = $pxCtx->getVid();
    $url = $pxCtx->getURI();
    $new_url = '/block.html?vid='.$vid.'&url='.$url;
    header('Location: '.$new_url);
    header('Status: 403');
    die();
};
```

<a name="recaptcha"></a>
### reCaptcha
* Add the following script to your <head> section:

`<script src="https://www.google.com/recaptcha/api.js"></script>`

* Add the following div inside the page `<body>` section:

```
<div class="g-recaptcha" data-sitekey="6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b" data-callback="handleCaptcha" data-theme="dark"></div>
```

Note the the `callback` attribute should match the name of the captcha handler described in the next section).

<a name="captchahandler"></a>
### JS Captcha Handler

Once the captcha is solved this handler will be triggered.

Copy this functions to your block page `<head>` section:

```javascript
function handleCaptcha(response) {
    var vid = getQueryString("vid");
    var name = '_pxCaptcha';
    var expiryUtc = new Date(Date.now() + 1000 * 10).toUTCString();
    var cookieParts = [name, '=', response + ':' + vid + '; expires=', expiryUtc, '; path=/'];
    document.cookie = cookieParts.join('');
    window.location.href = getQueryString("url");
}

function getQueryString(name, url) {
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
    results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}
```

<a name="pxsnippet"></a>
### PerimeterX Snippet

Add your [application snippet](https://console.perimeterx.com/#/app/applicationsmgmt) to the block page.