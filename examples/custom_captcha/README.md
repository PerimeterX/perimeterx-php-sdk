# Implementing captcha block page

In order to create a customized CAPTCHA block page, that will work with PerimeterX CAPTCHA API, follow these guidlines: 

* [Custom block handler](#blockhandler): Implementing a custom block handler using `perimeterx-php-sdk` that will extract all relevant data.
* [reCAPTCHA](#recaptcha): Adding a `reCaptcha` to your block page.
* [JS CAPTCHA handler](#captchahandler): Implementing a reCAPTCHA function that will handle CAPTCHA API response.
* [PerimeterX Snippet](#pxsnippet): Adding the PerimeterX snippet to the block page.


<a name="blockhandler"></a>
### Custom block handler

A Block handler should extract data from the pxContext object and send that data to the block page:

1. URL - the original URL that the user tried to reach using `$pxContext->getURI()`.
2. VID - using `$pxContext->getVid()`.
3. UUID - using `$pxContext->getUuid()`.

The block handler function should redirect the user to the block page and send the collected data with a request for an instance. One way of doing so is to add query parameters to the block page URI.

#####Implementation:

```
$pxConfig['custom_block_handler'] = function($pxCtx) {
    $vid = $pxCtx->getVid();
    $url = $pxCtx->getURI();
    $uuid = $pxCtx->getUuid();
    $new_url = '/block.html?vid='.$vid.'&url='.$url.'uuid='.$uuid;
    header('Location: '.$new_url);
    header('Status: 403');
    die();
};
```

<a name="recaptcha"></a>
### reCAPTCHA
* Add the following script to your <head> section:

`<script src="https://www.google.com/recaptcha/api.js"></script>`

* Add the following div inside the page's `<body>` section:

```
<div class="g-recaptcha" data-sitekey="6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b" data-callback="handleCaptcha" data-theme="dark"></div>
```

Note the the `callback` attribute should match the name of the CAPTCHA handler described in the next section).

<a name="captchahandler"></a>
### JS CAPTCHA Handler

Once the CAPTCHA is solved, this handler will be triggered.

Copy these functions to your block page's `<head>` section:

```javascript
function handleCaptcha(response) {
    var vid = getQueryString("vid");
    var uuid = getQueryString("uuid");
    var name = '_pxCaptcha';
    var expiryUtc = new Date(Date.now() + 1000 * 10).toUTCString();
    var cookieParts = [name, '=', response + ':' + vid + ':' + uuid + '; expires=', expiryUtc, '; path=/'];
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

Add your [application snippet](https://console.perimeterx.com/botDefender/admin?page=applicationsmgmt) to the block page.
