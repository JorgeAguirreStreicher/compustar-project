<?php
namespace CompuImport\Kernel;

class StageResult
{
    public const STATUS_OK = 'OK';
    public const STATUS_WARN = 'WARN';
    public const STATUS_ERROR = 'ERROR';

    /**
     * @var string
     */
    public $status;

    /**
     * @var array<string,mixed>
     */
    public $metrics;

    /**
     * @var array<string,string>
     */
    public $artifacts;

    /**
     * @var string|null
     */
    public $notes;

    /**
     * @param array<string,mixed> $metrics
     * @param array<string,string> $artifacts
     */
    public function __construct(string $status, array $metrics = [], array $artifacts = [], ?string $notes = null)
    {
        $this->status = $status;
        $this->metrics = $metrics;
        $this->artifacts = $artifacts;
        $this->notes = $notes;
    }
}
