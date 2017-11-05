<html>
<head><head>
<body>
    <h1>Sample site</h1>

<?php

// Make sure to load Perimeterx class
use Perimeterx\Perimeterx;

// Add your configuration here
$pxConfig = [
    'app_id' => '',
    'cookie_key' => '',
    'auth_token' => '',
    'module_mode' => Perimeterx::$ACTIVE_MODE,
    'blocking_score' => 40
];

$pxConfig['custom_block_handler'] = function($pxCtx) {
    $vid = $pxCtx->getVid();
    $full_url = $pxCtx->getURI();
    $uuid = $pxCtx->getUuid();
    $new_url = '/block.html?vid='.$vid.'&url='.$full_url.'&uuid='.$uuid;
    header('Location: '.$new_url);
    header('Status: 403');
    die();
};

$px = Perimeterx::Instance($pxConfig);
$px->pxVerify();
    ?>

    <script type="text/javascript">
        (function () {
            window._pxAppId = '';
            // Custom parameters
            // window._pxParam1 = "<param1>";
            var p = document.getElementsByTagName('script')[0],
                    s = document.createElement('script');
            s.async = 1;
            s.src = '//client.perimeterx.net/<pxAppId>/main.min.js';
            p.parentNode.insertBefore(s, p);
        }());

    </script>
    <div style="position:fixed; top:0; left:0;" width="1" height="1">
        <img src="//collector-<pxAppId>.perimeterx.net/api/v1/collector/pxPixel.gif?appId=<pxAppId>">
        <!-- With custom parameters: -->
        <!--<img src="//collector-<pxAppId>.perimeterx.net/api/v1/collector/pxPixel.gif?appId=<pxAppId>&p1=VALUE&p2=VALUE2&p3=VALUE3">-->
    </div>
    <noscript>
        <div style="position:fixed; top:0; left:0;" width="1" height="1">
            <img src="//collector-<pxAppId>.perimeterx.net/api/v1/collector/noScript.gif?appId=<pxAppId>">
        </div>
    </noscript>
    </body>
</html>
