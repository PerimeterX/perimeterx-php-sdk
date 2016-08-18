<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Perimeterx\Perimeterx;

/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
function pxCustomUserIP($pxCtx)
{
    $ip = $_SERVER['X-MYHER-USER-IP'];
    return $ip;
}

/**
 * @param \Perimeterx\PerimeterxContext $pxCtx
 */
function pxCustomBlockHandler($pxCtx) {
    $block_score = $pxCtx->getScore();
    $block_uuid = $pxCtx->getUuid();

    /* user defined logic comes here */
    error_log('px score for user is ' . $block_score);
};

function pxRiskHandler($url, $authKey, $payload) {
    error_log('px risk handler - url >' . $url . '<, authKey >' . $authKey . '<, payload >' . $payload . '<');
}

$perimeterxConfig = [
    'app_id' => 'PX_APP_ID',
    'cookie_key' => 'COOKIE_SECRET',
    'auth_token' => 'AUTH_TOKEN',
    'blocking_score' => 70,
    'module_mode' => Perimeterx::$MONITOR_MODE_SYNC,
    'custom_user_ip' => pxCustomUserIP,
    'custom_block_handler' => pxCustomBlockHandler,
    'custom_risk_handler' => pxRiskHandler
];

$px = Perimeterx::Instance($perimeterxConfig);
$px->pxVerify();

?>
