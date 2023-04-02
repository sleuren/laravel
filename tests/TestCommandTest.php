<?php

namespace Sleuren\Tests;

use Sleuren\Sleuren;
use Sleuren\Tests\Mocks\SleurenClient;

class TestCommandTest extends TestCase
{
    /** @test */
    public function it_detects_if_the_project_key_is_set()
    {
        $this->app['config']['sleuren.project_key'] = '';

        $this->artisan('sleuren:test')
            ->expectsOutput('❌ [sleuren] Could not find your project key, set this in your .env')
            ->assertExitCode(0);

        $this->app['config']['sleuren.project_key'] = 'test';

        $this->artisan('sleuren:test')
            ->expectsOutput('✅ [sleuren] Found project key')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_that_its_running_in_the_correct_environment()
    {
        $this->app['config']['app.env'] = 'production';
        $this->app['config']['sleuren.environments'] = [];

        $this->artisan('sleuren:test')
            ->expectsOutput('❌ [sleuren] Environment (production) not allowed to send errors to sleuren, set this in your config')
            ->assertExitCode(0);

        $this->app['config']['sleuren.environments'] = ['production'];

        $this->artisan('sleuren:test')
            ->expectsOutput('✅ [sleuren] Correct environment found (' . config('app.env') . ')')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_that_it_fails_to_send_to_sleuren()
    {
        $this->artisan('sleuren:test')
            ->expectsOutput('❌ [sleuren] Failed to send exception to sleuren')
            ->assertExitCode(0);

        $this->app['config']['sleuren.environments'] = [
            'testing',
        ];
        $this->app['sleuren'] = new Sleuren($this->client = new SleurenClient(
            'project_key'
        ));

        $this->artisan('sleuren:test')
            ->expectsOutput('✅ [sleuren] Sent exception to sleuren with ID: '.SleurenClient::RESPONSE_ID)
            ->assertExitCode(0);

        $this->assertEquals(SleurenClient::RESPONSE_ID, $this->app['sleuren']->getLastExceptionId());
    }
}
