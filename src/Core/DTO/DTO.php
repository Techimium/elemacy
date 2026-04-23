<?php

namespace Elemacy\Core\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\Request;

class DTO
{
    public static function from_request(Request $request)
    {
        return static::from_array($request->clean());
    }

    public static function from_array(array $data)
    {
        $dto = new static();

        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }

        return $dto;
    }
}
