<?php

namespace Perimeterx;

class PerimeterxUtils
{
	const SECONDS_IN_YEAR = 31557600;
	const ILLEGAL_COOKIE_CHARS = [",", ";", " ", "\t", "\r", "\n", "\013", "\014"];
	protected static $inputStreamName = "php://input";

	private $customParamsArray = [
		'custom_param1' => '',
		'custom_param2' => '',
		'custom_param3' => '',
		'custom_param4' => '',
		'custom_param5' => '',
		'custom_param6' => '',
		'custom_param7' => '',
		'custom_param8' => '',
		'custom_param9' => '',
		'custom_param10' => ''
	];

	public function handleCustomParams($pxConfig, &$array) {
		if (isset($pxConfig['enrich_custom_params'])) {
			$customParams = $pxConfig['enrich_custom_params']($this->customParamsArray);
			foreach ($customParams as $key => $value) {
				if (preg_match('/custom_param\d+$/i', $key) && $value != '') {
					$array[$key] = $value;
				}
			}
		}
	}

	public static function sanitizeCookie($cookieValue) {
		return str_replace(self::ILLEGAL_COOKIE_CHARS, array_map("rawurlencode", self::ILLEGAL_COOKIE_CHARS), $cookieValue);
	}

	public static function getPostRequestBody() {
		return file_get_contents(static::$inputStreamName);
	}

	public static function getNestedArrayProperty($array, $propertyNameArray) {
		if (!is_array($propertyNameArray)) {
			return null;
		}
		$value = $array;
		foreach ($propertyNameArray as $propertyName) {
			if (is_array($value) && array_key_exists($propertyName, $value)) {
				$value = $value[$propertyName];
			} else {
				return null;
			}
		}
		return $value;
	}

	public static function createUuidV4() {
		// source: https://www.php.net/manual/en/function.uniqid.php#94959
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	public static function sha256($text) {
		return hash("sha256", $text);
	}

	public static function getAllHeaders() {
		$headers = [];
		if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        return $headers;
	}

    /**
     * @param array $headers
     * @param array $sensitiveHeaders
     * @return array
     */
    public static function filterSensitiveHeaders($headers, $sensitiveHeaders) {
        $retval = [];
        $sensitiveHeaders = array_map("strtolower", $sensitiveHeaders);
        foreach ($headers as $key => $value) {
            if (isset($key, $value) && !in_array(strtolower($key), $sensitiveHeaders)) {
                $retval[$key] = $value;
            }
        }
        return $retval;
    }

    public static function getCookieValue($name) {
        if (!isset($_SERVER['HTTP_COOKIE'])) {
            return null;
        }

        foreach (explode('; ', $_SERVER['HTTP_COOKIE']) as $rawcookie) {
            if (empty($rawcookie)) {
                continue;
            }
            $equalIndex = strpos($rawcookie, '=');
            if ($equalIndex !== false && substr($rawcookie, 0, $equalIndex) === $name) {
                return substr($rawcookie, $equalIndex + 1);
            }
        }
        return null;
    }

    public static function isEmailAddress($string) {
        $emailRegex = "/^[a-zA-Z0-9_+&*-]+(?:\.[a-zA-Z0-9_+&*-]+)*@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,7}$/";
        return !!preg_match($emailRegex, $string);
    }
}
