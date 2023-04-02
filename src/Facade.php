<?php

namespace Sleuren;

use Sleuren\Http\Client;
use Sleuren\Fakes\SleurenFake;

/**
 * @method static void assertSent($throwable, $callback = null)
 * @method static void assertRequestsSent(int $count)
 * @method static void assertNotSent($throwable, $callback = null)
 * @method static void assertNothingSent()
 */
class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * Replace the bound instance with a fake.
     *
     * @return void
     */
    public static function fake()
    {
        static::swap(new SleurenFake(new Client('project_key')));
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sleuren';
    }
}
