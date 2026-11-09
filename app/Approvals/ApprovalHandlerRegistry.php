<?php

namespace App\Approvals;

use InvalidArgumentException;

/** Tagged registry of ApprovalHandler implementations (tag: `approval.handlers`). */
class ApprovalHandlerRegistry
{
    /** @var array<string, ApprovalHandler> */
    private array $handlers = [];

    /** @param  iterable<ApprovalHandler>  $handlers */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    public function register(ApprovalHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function for(string $type): ApprovalHandler
    {
        return $this->handlers[$type]
            ?? throw new InvalidArgumentException("No approval handler registered for [{$type}].");
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->handlers);
    }
}
