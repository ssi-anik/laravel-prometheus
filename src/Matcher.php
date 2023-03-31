<?php

namespace Anik\Laravel\Prometheus;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Matcher
{
    /**
     * @param  array|string  $haystack
     * @param  string|int  $needle
     *
     * @return false|mixed
     */
    public static function matches($haystack, $needle)
    {
        foreach (Arr::wrap($haystack) as $key => $value) {
            if (is_numeric($key)) {
                $pattern = $value;
                $value = [];
            } else {
                $pattern = $key;
            }

            if (Str::is($pattern, $needle)) {
                return $value;
            }
        }

        return false;
    }
}
