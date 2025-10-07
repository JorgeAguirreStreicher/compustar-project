<?php
namespace CompuImport\Kernel;

interface StageInterface
{
    /**
     * Returns the numeric identifier of the stage (e.g. "02").
     */
    public function id(): string;

    /**
     * Human readable title for logging.
     */
    public function title(): string;

    /**
     * Declares expected input artifact names.
     *
     * @return string[]
     */
    public function inputs(): array;

    /**
     * Declares output artifact names produced by the stage.
     *
     * @return string[]
     */
    public function outputs(): array;

    /**
     * Executes the stage and returns a result payload.
     *
     * @param array<string,mixed> $context
     */
    public function run(array $context): StageResult;
}
