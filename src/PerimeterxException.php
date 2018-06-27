<?php

namespace Perimeterx;

define('pxErrorPrefix', '');

final class PerimeterxException extends \Exception
{
    public static $APP_ID_MISSING = 'perimeterx application id is required';
    public static $AUTH_TOKEN_MISSING = 'perimeterx auth token is required';
    public static $COOKIE_MISSING  = 'perimeterx cookie key is required';
    public static $INVALID_JS_REF  = 'invalid url provided on js_ref';
    public static $INVALID_CSS_REF  = 'invalid url provided on css_ref';
    public static $INVALID_LOGO_URL = 'invalid url provided on custom_logo';
    public static $INVALID_LOGGER  = 'perimeterx logger must implement \Psr\Log\LoggerInterface';
}
