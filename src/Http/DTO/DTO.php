<?php

namespace Elemacy\Http\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\Contracts\RequestInterface;

/**
 * @api
 */
class DTO
{
    public static function from_request(RequestInterface $request)
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
