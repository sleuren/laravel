<?php

namespace Sleuren\Recorders\LogRecorder;

use Throwable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Contracts\Foundation\Application;

class LogRecorder
{
    /** @var \Sleuren\Recorders\LogRecorder\LogMessage[] */
    protected array $logMessages = [];

    protected Application $app;

    protected ?int $maxLogs;


    public function __construct(
        Application $app,
        int $maxLogs = 200
    ) {
        $this->maxLogs = $maxLogs;
        $this->app = $app;
    }

    public function start(): self
    {
        /** @phpstan-ignore-next-line */
        $this->app['events']->listen(MessageLogged::class, [$this, 'record']);

        return $this;
    }

    public function record(MessageLogged $event): void
    {
        if ($this->shouldIgnore($event)) {
            return;
        }

        $this->logMessages[] = LogMessage::fromMessageLoggedEvent($event);

        if (is_int($this->maxLogs)) {
            $this->logMessages = array_slice($this->logMessages, -$this->maxLogs);
        }
    }

    /** @return array<array<int,string>> */
    public function getLogMessages(): array
    {
        return $this->toArray();
    }

    /** @return array<int, mixed> */
    public function toArray(): array
    {
        $logMessages = [];

        foreach ($this->logMessages as $log) {
            $logMessages[] = $log->toArray();
        }

        return $logMessages;
    }

    protected function shouldIgnore(mixed $event): bool
    {
        if (! isset($event->context['exception'])) {
            return false;
        }

        if (! $event->context['exception'] instanceof Throwable) {
            return false;
        }

        return true;
    }

    public function reset(): void
    {
        $this->logMessages = [];
    }

    public function getMaxLogs(): ?int
    {
        return $this->maxLogs;
    }

    public function setMaxLogs(?int $maxLogs): self
    {
        $this->maxLogs = $maxLogs;

        return $this;
    }
}
