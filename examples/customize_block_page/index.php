<!DOCTYPE html>
<html>
<body>
<?php
  use Perimeterx\Perimeterx;

  $perimeterxConfig = [
      'app_id' => '',
      'cookie_key' => '',
      'auth_token' => '',
      'blocking_score' => 80,
      'module_mode' => Perimeterx::$ACTIVE_MODE,
      'custom_logo' => 'https://s.perimeterx.net/logo.png',
      'js_ref' => 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js'
  ];

  /* Obtain PerimeterX SDK instance */
  $px = Perimeterx::Instance($perimeterxConfig);

  /* run verify at the beginning of a page request */
  $px->pxVerify();
?>
<h1>PxDummy Webapp</h1>
<script type="text/javascript">
    (function(){
        window._pxAppId ='PX_APP_ID';
        // Custom parameters
        // window._pxParam1 = "<param1>";
        var p = document.getElementsByTagName('script')[0],
            s = document.createElement('script');
        s.async = 1;
        s.src = '//client.perimeterx.net/PX_APP_ID/main.min.js';
        p.parentNode.insertBefore(s,p);
    }());
</script>
<noscript>
    <div style="position:fixed; top:0; left:0; display:none" width="1" height="1">
        <img src="//collector-PX_APP_ID.perimeterx.net/api/v1/collector/noScript.gif?appId=PX_APP_ID">
    </div>
</noscript>
</body>
</html>
