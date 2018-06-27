<?php
namespace Perimeterx;

class PerimeterxDataEnrichment {

public static function processDataEnrichment($pxCtx, $pxConfig) {
    $pxde_cookie = $pxCtx->getDataEnrichmentCookie();
    if(!isset($pxde_cookie)) {
        return;
    }

    $splittedCookie = explode(":", $pxde_cookie);
    if(count($splittedCookie) < 1) {
        return;
    }

    $pxCtx->setDataEnrichmentVerified(false);
    $pxde_hash = $splittedCookie[0];
    $pxde = $splittedCookie[1];

    $hash_digest = hash_hmac('sha256', $pxde, $pxConfig['cookie_key']);
    if(hash_equals($hash_digest, $pxde_hash)) {
        $pxCtx->setDataEnrichmentVerified(true);
        $pxConfig['logger']->debug("pxde hmac validation success");
    }

    $decoded_pxde = base64_decode($pxde);
    if(!$decoded_pxde) {
        $pxConfig['logger']->error("error while decoding pxde");
        return;
    }

    $pxde_obj = json_decode($decoded_pxde);
    if(!$pxde_obj) {
        $pxConfig['logger']->error("error while encoding pxde to json");
        $pxCtx->setDataEnrichment($decoded_pxde);
        return;
    }

    $pxConfig['logger']->debug("pxde json encoding success");
    $pxCtx->setDataEnrichment($pxde_obj);
    return;
    }
}