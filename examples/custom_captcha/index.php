<!DOCKTYPE html>
<html>
<head>

<h1>Welcome</h1>

<?php

//$loader = require('vendor/autoload.php');
//$loader->add('Perimeterx\Perimeterx', '/var/www/html/vendor/');

use Perimeterx\Perimeterx;

$pxConfig = [
    'app_id' => 'PXJWbMQarF',
    'cookie_key' => 'FekuuL9XWdWNbyy1FDl+ZkvMoXtJ6y6Gga15gyo8bTLmVTWqwa2XzkdRquC9E34b',
    'auth_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzY29wZXMiOlsicmlza19zY29yZSIsInJlc3RfYXBpIl0sImlhdCI6MTQ2ODc2NjYxNSwic3ViIjoiUFhiOGJyQXpRMSIsImp0aSI6IjBmMjkyYTZlLWE5MDgtNDYwYi1iMmViLTg5MDY3NTk4ZDVlZiJ9.7gr2Kluofv08Qf9QFSt5DddreXGmAMuyXnRKeTQJuQs',
    'blocking_score' => 40,
    'captcha_enabled' => true
];

//$pxConfig = [
    //'app_id' => '',
    //'cookie_key' => '',
    //'auth_token' => '',
    //'blocking_score' => 40
//];

$pxConfig['custom_block_handler'] = function($pxCtx) {
    $vid = $pxCtx->getVid();
    $full_url = $pxCtx->getURI();
    $new_url = '/block.html?vid='.$vid.'&url='.$full_url;
    header('Location: '.$new_url);
    header('Status: 403');
    die();
};

$px = Perimeterx::Instance($pxConfig);
$px->pxVerify();
?>

<script type="text/javascript">
(function(){
    window._pxAppId ='PXb8brAzQ1';
    // Custom parameters
    // window._pxParam1 = "<param1>";
    var p = document.getElementsByTagName('script')[0],
        s = document.createElement('script');
    s.async = 1;
    s.src = '//client.perimeterx.net/PXb8brAzQ1/main.min.js';
    p.parentNode.insertBefore(s,p);
}());

</script>
<div style="position:fixed; top:0; left:0;" width="1" height="1">
        <img src="//collector-PXb8brAzQ1.perimeterx.net/api/v1/collector/pxPixel.gif?appId=PXb8brAzQ1">
        <!-- With custom parameters: -->
        <!--<img src="//collector-PXb8brAzQ1.perimeterx.net/api/v1/collector/pxPixel.gif?appId=PXb8brAzQ1&p1=VALUE&p2=VALUE2&p3=VALUE3">-->
</div>
<noscript>
        <div style="position:fixed; top:0; left:0;" width="1" height="1">
                <img src="//collector-PXb8brAzQ1.perimeterx.net/api/v1/collector/noScript.gif?appId=PXb8brAzQ1">
        </div>
</noscript>
</body>
</html>
