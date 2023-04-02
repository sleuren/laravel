<?php

namespace Sleuren\Logger;

use Throwable;
use Monolog\Logger;
use Sleuren\Sleuren;
use Monolog\Handler\AbstractProcessingHandler;

class SleurenHandler extends AbstractProcessingHandler
{
    /** @var Sleuren */
    protected $sleuren;

    /**
     * @param Sleuren $sleuren
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(Sleuren $sleuren, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->sleuren = $sleuren;

        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     */
    protected function write($record): void
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof Throwable) {
            $this->sleuren->handle(
                $record['context']['exception']
            );

            return;
        }
    }
}
