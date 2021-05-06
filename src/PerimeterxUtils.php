<?php

namespace Perimeterx;

class PerimeterxUtils
{
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
}
