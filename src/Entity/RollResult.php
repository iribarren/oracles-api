<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeType;
use App\Enum\GamePhase;
use App\Enum\RollOutcome;
use App\Repository\RollResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RollResultRepository::class)]
#[ORM\Table(name: 'roll_results')]
class RollResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: GamePhase::class)]
    private GamePhase $phase;

    /** For epilogue actions 1-3, stores which action number (1, 2, or 3). Null for chapter rolls. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $action_number = null;

    #[ORM\Column(type: 'integer')]
    private int $action_die;

    #[ORM\Column(type: 'integer')]
    private int $challenge_die_1;

    #[ORM\Column(type: 'integer')]
    private int $challenge_die_2;

    #[ORM\Column(type: 'integer')]
    private int $modifier;

    #[ORM\Column(type: 'integer')]
    private int $action_score;

    #[ORM\Column(type: 'string', enumType: RollOutcome::class)]
    private RollOutcome $outcome;

    /** Which attribute was used for this roll — needed to enforce one-attribute-per-phase rules. */
    #[ORM\Column(type: 'string', enumType: AttributeType::class, nullable: true)]
    private ?AttributeType $attribute_type = null;

    #[ORM\ManyToOne(targetEntity: GameSession::class, inversedBy: 'roll_results')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSession $game_session;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhase(): GamePhase
    {
        return $this->phase;
    }

    public function setPhase(GamePhase $phase): self
    {
        $this->phase = $phase;
        return $this;
    }

    public function getActionNumber(): ?int
    {
        return $this->action_number;
    }

    public function setActionNumber(?int $action_number): self
    {
        $this->action_number = $action_number;
        return $this;
    }

    public function getActionDie(): int
    {
        return $this->action_die;
    }

    public function setActionDie(int $action_die): self
    {
        $this->action_die = $action_die;
        return $this;
    }

    public function getChallengeDie1(): int
    {
        return $this->challenge_die_1;
    }

    public function setChallengeDie1(int $challenge_die_1): self
    {
        $this->challenge_die_1 = $challenge_die_1;
        return $this;
    }

    public function getChallengeDie2(): int
    {
        return $this->challenge_die_2;
    }

    public function setChallengeDie2(int $challenge_die_2): self
    {
        $this->challenge_die_2 = $challenge_die_2;
        return $this;
    }

    public function getModifier(): int
    {
        return $this->modifier;
    }

    public function setModifier(int $modifier): self
    {
        $this->modifier = $modifier;
        return $this;
    }

    public function getActionScore(): int
    {
        return $this->action_score;
    }

    public function setActionScore(int $action_score): self
    {
        $this->action_score = $action_score;
        return $this;
    }

    public function getOutcome(): RollOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(RollOutcome $outcome): self
    {
        $this->outcome = $outcome;
        return $this;
    }

    public function getAttributeType(): ?AttributeType
    {
        return $this->attribute_type;
    }

    public function setAttributeType(?AttributeType $attribute_type): self
    {
        $this->attribute_type = $attribute_type;
        return $this;
    }

    public function getGameSession(): GameSession
    {
        return $this->game_session;
    }

    public function setGameSession(GameSession $game_session): self
    {
        $this->game_session = $game_session;
        return $this;
    }
}
