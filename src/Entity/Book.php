<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GamePhase;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\Table(name: 'books')]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: GamePhase::class)]
    private GamePhase $phase;

    #[ORM\Column(length: 100)]
    private string $color;

    #[ORM\Column(length: 255)]
    private string $color_hint;

    #[ORM\Column(length: 100)]
    private string $binding;

    #[ORM\Column(length: 255)]
    private string $binding_hint;

    #[ORM\Column(length: 100)]
    private string $smell;

    #[ORM\Column(length: 255)]
    private string $smell_hint;

    #[ORM\Column(length: 255)]
    private string $interior;

    #[ORM\ManyToOne(targetEntity: GameSession::class, inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSession $game_session;

    /** @var Collection<int, JournalEntry> */
    #[ORM\OneToMany(targetEntity: JournalEntry::class, mappedBy: 'book', cascade: ['persist'])]
    private Collection $journal_entries;

    public function __construct()
    {
        $this->journal_entries = new ArrayCollection();
    }

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

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getColorHint(): string
    {
        return $this->color_hint;
    }

    public function setColorHint(string $color_hint): self
    {
        $this->color_hint = $color_hint;
        return $this;
    }

    public function getBinding(): string
    {
        return $this->binding;
    }

    public function setBinding(string $binding): self
    {
        $this->binding = $binding;
        return $this;
    }

    public function getBindingHint(): string
    {
        return $this->binding_hint;
    }

    public function setBindingHint(string $binding_hint): self
    {
        $this->binding_hint = $binding_hint;
        return $this;
    }

    public function getSmell(): string
    {
        return $this->smell;
    }

    public function setSmell(string $smell): self
    {
        $this->smell = $smell;
        return $this;
    }

    public function getSmellHint(): string
    {
        return $this->smell_hint;
    }

    public function setSmellHint(string $smell_hint): self
    {
        $this->smell_hint = $smell_hint;
        return $this;
    }

    public function getInterior(): string
    {
        return $this->interior;
    }

    public function setInterior(string $interior): self
    {
        $this->interior = $interior;
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

    /** @return Collection<int, JournalEntry> */
    public function getJournalEntries(): Collection
    {
        return $this->journal_entries;
    }

    public function addJournalEntry(JournalEntry $entry): self
    {
        if (!$this->journal_entries->contains($entry)) {
            $this->journal_entries->add($entry);
            $entry->setBook($this);
        }
        return $this;
    }
}
