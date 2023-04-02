<?php

namespace Sleuren\Tests\Fakes;

use Sleuren\Tests\TestCase;
use Sleuren\Facade as SleurenFacade;

class SleurenTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        SleurenFacade::fake();

        $this->app['config']['logging.channels.sleuren'] = ['driver' => 'sleuren'];
        $this->app['config']['logging.default'] = 'sleuren';
        $this->app['config']['sleuren.environments'] = ['testing'];
    }

    /** @test */
    public function it_will_sent_exception_to_sleuren_if_exception_is_thrown()
    {
        $this->app['router']->get('/exception', function () {
            throw new \Exception('Exception');
        });

        $this->get('/exception');

        SleurenFacade::assertSent(\Exception::class);

        SleurenFacade::assertSent(\Exception::class, function (\Throwable $throwable) {
            $this->assertSame('Exception', $throwable->getMessage());

            return true;
        });

        SleurenFacade::assertNotSent(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    }

    /** @test */
    public function it_will_sent_nothing_to_sleuren_if_no_exceptions_thrown()
    {
        SleurenFacade::fake();

        $this->app['router']->get('/nothing', function () {
            //
        });

        $this->get('/nothing');

        SleurenFacade::assertNothingSent();
    }
}
