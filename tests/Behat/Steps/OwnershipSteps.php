<?php

declare(strict_types=1);

namespace App\Tests\Behat\Steps;

/**
 * Game ownership / lifecycle steps.
 *
 * Ownerless sessions are public; owned sessions are restricted to their owner by
 * the GameSessionVoter. These steps switch between identities (owner, another
 * player, guest) to assert who can reach a session.
 */
trait OwnershipSteps
{
    /**
     * @Given another authenticated player :email
     */
    public function anotherAuthenticatedPlayer(string $email): void
    {
        $user = $this->createPlayer($email);
        $this->otherPlayerToken = $this->tokenFor($user);
    }

    /**
     * Creates a game owned by the current authenticated player (via the engine).
     *
     * @Given a game owned by me
     */
    public function aGameOwnedByMe(): void
    {
        $game = $this->gameEngine->createGame('aventura_rapida', $this->currentUser);
        $this->gameUuid = $game->getId();
    }

    /**
     * Creates a game with no owner (anonymous play).
     *
     * @Given an ownerless game
     */
    public function anOwnerlessGame(): void
    {
        $game = $this->gameEngine->createGame('aventura_rapida', null);
        $this->gameUuid = $game->getId();
    }

    /**
     * @When I create a game
     */
    public function iCreateAGame(): void
    {
        $this->sendRequest('POST', '/api/game', '{}');
        $data = $this->getDecodedResponse();
        if (isset($data['id'])) {
            $this->gameUuid = \Symfony\Component\Uid\Uuid::fromString($data['id']);
        }
    }

    /**
     * @When the owner views the game
     */
    public function theOwnerViewsTheGame(): void
    {
        $this->actAs($this->ownerToken);
        $this->sendRequest('GET', $this->gameUrl(), null);
    }

    /**
     * @When another player views the game
     */
    public function anotherPlayerViewsTheGame(): void
    {
        $this->actAs($this->otherPlayerToken);
        $this->sendRequest('GET', $this->gameUrl(), null);
    }

    /**
     * @When a guest views the game
     */
    public function aGuestViewsTheGame(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', $this->gameUrl(), null);
    }

    /**
     * @When I list my sessions
     */
    public function iListMySessions(): void
    {
        $this->sendRequest('GET', '/api/player/sessions', null);
    }

    /**
     * @When a guest lists player sessions
     */
    public function aGuestListsPlayerSessions(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', '/api/player/sessions', null);
    }

    /**
     * @Then access is granted
     */
    public function accessIsGranted(): void
    {
        $status = $this->browser->getResponse()->getStatusCode();
        if ($status !== 200) {
            throw new \RuntimeException(\sprintf('Expected access to be granted (200) but got %d.', $status));
        }
    }

    /**
     * @Then access is forbidden
     */
    public function accessIsForbidden(): void
    {
        $status = $this->browser->getResponse()->getStatusCode();
        if ($status !== 403) {
            throw new \RuntimeException(\sprintf('Expected access to be forbidden (403) but got %d.', $status));
        }
    }

    /**
     * @Then the created game has no owner
     */
    public function theCreatedGameHasNoOwner(): void
    {
        if ($this->refetchGame()->getOwner() !== null) {
            throw new \RuntimeException('Expected the created game to have no owner, but it has one.');
        }
    }

    /**
     * @Then the created game is owned by me
     */
    public function theCreatedGameIsOwnedByMe(): void
    {
        $owner = $this->refetchGame()->getOwner();
        if ($owner === null || $owner->getEmail() !== $this->currentUserEmail) {
            throw new \RuntimeException('Expected the created game to be owned by the current player.');
        }
    }

    /**
     * @Then I see :count sessions
     */
    public function iSeeSessions(int $count): void
    {
        $data = $this->getDecodedResponse();
        if (\count($data) !== $count) {
            throw new \RuntimeException(\sprintf('Expected %d sessions but got %d.', $count, \count($data)));
        }
    }

    private function gameUrl(): string
    {
        return '/api/game/' . (string) $this->gameUuid;
    }
}
