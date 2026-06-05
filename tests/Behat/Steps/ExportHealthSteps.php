<?php

declare(strict_types=1);

namespace App\Tests\Behat\Steps;

/**
 * Export and health/test endpoint steps.
 *
 * Export aggregates a printable journal document (owner-restricted). Health and
 * test are public diagnostics.
 */
trait ExportHealthSteps
{
    /**
     * @When I export the game
     */
    public function iExportTheGame(): void
    {
        $this->sendGameRequest('GET', '/export');
    }

    /**
     * @When another player exports the game
     */
    public function anotherPlayerExportsTheGame(): void
    {
        $this->actAs($this->otherPlayerToken);
        $this->sendRequest('GET', '/api/game/' . (string) $this->gameUuid . '/export', null);
    }

    /**
     * @When I check the health endpoint
     */
    public function iCheckTheHealthEndpoint(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', '/api/health', null);
    }

    /**
     * @When I call the test endpoint
     */
    public function iCallTheTestEndpoint(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', '/api/test', null);
    }
}
