<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeType;
use App\Repository\AttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeRepository::class)]
#[ORM\Table(name: 'attributes')]
class Attribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: AttributeType::class)]
    private AttributeType $type;

    #[ORM\Column(type: 'integer')]
    private int $base_value = 1;

    #[ORM\Column(type: 'integer')]
    private int $background = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $background_title = null;

    #[ORM\Column(type: 'integer')]
    private int $support = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $support_title = null;

    #[ORM\ManyToOne(targetEntity: GameSession::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSession $game_session;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): AttributeType
    {
        return $this->type;
    }

    public function setType(AttributeType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getBaseValue(): int
    {
        return $this->base_value;
    }

    public function setBaseValue(int $base_value): self
    {
        $this->base_value = $base_value;
        return $this;
    }

    public function getBackground(): int
    {
        return $this->background;
    }

    public function setBackground(int $background): self
    {
        $this->background = $background;
        return $this;
    }

    public function getBackgroundTitle(): ?string
    {
        return $this->background_title;
    }

    public function setBackgroundTitle(?string $background_title): self
    {
        $this->background_title = $background_title;
        return $this;
    }

    public function getSupport(): int
    {
        return $this->support;
    }

    public function setSupport(int $support): self
    {
        $this->support = $support;
        return $this;
    }

    public function getSupportTitle(): ?string
    {
        return $this->support_title;
    }

    public function setSupportTitle(?string $support_title): self
    {
        $this->support_title = $support_title;
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

    /**
     * Returns the total modifier value: base_value + background + support.
     */
    public function getTotalModifier(): int
    {
        return $this->base_value + $this->background + $this->support;
    }
}
