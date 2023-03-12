<?php

namespace Sleuren\Commands;

use Exception;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'sleuren:test {exception?}';

    protected $description = 'Generate a test exception and send it to sleuren';

    public function handle()
    {
        try {
            /** @var Sleuren $sleuren */
            $sleuren = app('sleuren');

            // Test proc_open function availability
            try {
                proc_open("", [], $pipes);
            } catch (\Throwable $exception) {
                $this->warn("❌ proc_open function disabled.");
                return;
            }
            if (config('sleuren.sleuren_key')) {
                $this->info('✅ [Sleuren] Found sleuren key');
            } else {
                $this->error('❌ [Sleuren] Could not find your sleuren key, set this in your .env');
                $this->info('More information on setting your sleuren key: https://sleuren.com/docs/how-to-use/installation');
            }

            if (in_array(config('app.env'), config('sleuren.environments'))) {
                $this->info('✅ [Sleuren] Correct environment found (' . config('app.env') . ')');
            } else {
                $this->error('❌ [Sleuren] Environment (' . config('app.env') . ') not allowed to send errors to Sleuren, set this in your config');
                $this->info('More information about environment configuration: https://sleuren.com/docs/how-to-use/installation');
            }

            $response = $sleuren->handle(
                $this->generateException()
            );

            if (isset($response->id)) {
                $this->info('✅ [Sleuren] Sent exception to Sleuren with ID: '.$response->id);
            } elseif (is_null($response)) {
                $this->info('✅ [Sleuren] Sent exception to Sleuren!');
            } else {
                $this->error('❌ [Sleuren] Failed to send exception to Sleuren');
            }
        } catch (\Exception $ex) {
            $this->error("❌ [Sleuren] {$ex->getMessage()}");
        }
    }

    public function generateException(): ?Exception
    {
        try {
            throw new Exception($this->argument('exception') ?? 'This is a test exception from the Sleuren console');
        } catch (Exception $ex) {
            return $ex;
        }
    }
}
