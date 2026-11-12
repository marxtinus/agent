<?php

namespace App\Ai\Streaming\Events;

use Laravel\Ai\Streaming\Events\StreamEvent;

class StreamDiagnosing extends StreamEvent
{
    public function __construct(
        public string $id,
        public string $message,
        public int $timestamp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invocation_id' => $this->invocationId,
            'type' => 'stream_diagnosing',
            'message' => $this->message,
            'timestamp' => $this->timestamp,
        ];
    }
}
