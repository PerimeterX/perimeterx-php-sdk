<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Perimeterx\Perimeterx;

$perimeterxConfig = [
    'app_id' => 'PX_APP_ID',
    'cookie_key' => 'COOKIE_SECRET',
    'auth_token' => 'AUTH_TOKEN',
    'blocking_score' => 70,
    'module_mode' => Perimeterx::$MONITOR_MODE,
    /*
     * 'custom_user_ip' => function ($pxCtx)
     * {
     *      return $_SERVER['X-REAL-IP'];
     * },
     */

    /*
     * 'custom_block_handler' => function ($pxCtx)
     * {
     *      // $block_score = $pxCtx->getScore();
     *      // $block_uuid = $pxCtx->getUuid();
     *      // user defined logic comes here
     * },
     */

    /*
     * 'additional_activity_handler' => function ($activityType, $pxCtx, $details)
     * {
     *      // user defined logic comes here
     * },
     */

    /*
     * 'custom_risk_handler' => function ($url, $method, $json, $headers) {
     *      // user defined logic comes here
     * },
     */

    /*
     * 'custom_uri' => function ($pxCtx)
     * {
     *      return $_SERVER['HTTP_X_CUSTOM_URI'];
     * },
     */
];

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();

?>
