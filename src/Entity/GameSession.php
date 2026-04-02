<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GamePhase;
use App\Repository\GameSessionRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
#[ORM\Table(name: 'game_sessions')]
#[ORM\HasLifecycleCallbacks]
class GameSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $character_name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $character_description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $epoch = null;

    #[ORM\Column(type: 'string', enumType: GamePhase::class)]
    private GamePhase $current_phase;

    #[ORM\Column(type: 'integer')]
    private int $overcome_score = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $support_used = false;

    #[ORM\Column(length: 50)]
    private string $game_mode = 'aventura_rapida';

    #[ORM\Column]
    private DateTimeImmutable $created_at;

    #[ORM\Column]
    private DateTimeImmutable $updated_at;

    /** @var Collection<int, Attribute> */
    #[ORM\OneToMany(targetEntity: Attribute::class, mappedBy: 'game_session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributes;

    /** @var Collection<int, Book> */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'game_session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $books;

    /** @var Collection<int, JournalEntry> */
    #[ORM\OneToMany(targetEntity: JournalEntry::class, mappedBy: 'game_session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $journal_entries;

    /** @var Collection<int, RollResult> */
    #[ORM\OneToMany(targetEntity: RollResult::class, mappedBy: 'game_session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $roll_results;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'gameSessions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    public function __construct()
    {
        $this->id            = Uuid::v4();
        $this->current_phase = GamePhase::PROLOGUE;
        $this->created_at    = new DateTimeImmutable();
        $this->updated_at    = new DateTimeImmutable();
        $this->attributes    = new ArrayCollection();
        $this->books         = new ArrayCollection();
        $this->journal_entries = new ArrayCollection();
        $this->roll_results  = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCharacterName(): ?string
    {
        return $this->character_name;
    }

    public function setCharacterName(?string $character_name): self
    {
        $this->character_name = $character_name;
        return $this;
    }

    public function getCharacterDescription(): ?string
    {
        return $this->character_description;
    }

    public function setCharacterDescription(?string $character_description): self
    {
        $this->character_description = $character_description;
        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): self
    {
        $this->genre = $genre;
        return $this;
    }

    public function getEpoch(): ?string
    {
        return $this->epoch;
    }

    public function setEpoch(?string $epoch): self
    {
        $this->epoch = $epoch;
        return $this;
    }

    public function getCurrentPhase(): GamePhase
    {
        return $this->current_phase;
    }

    public function setCurrentPhase(GamePhase $current_phase): self
    {
        $this->current_phase = $current_phase;
        return $this;
    }

    public function getOvercomeScore(): int
    {
        return $this->overcome_score;
    }

    public function setOvercomeScore(int $overcome_score): self
    {
        $this->overcome_score = $overcome_score;
        return $this;
    }

    public function isSupportUsed(): bool
    {
        return $this->support_used;
    }

    public function setSupportUsed(bool $support_used): self
    {
        $this->support_used = $support_used;
        return $this;
    }

    public function getGameMode(): string
    {
        return $this->game_mode;
    }

    public function setGameMode(string $game_mode): self
    {
        $this->game_mode = $game_mode;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updated_at;
    }

    /** @return Collection<int, Attribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(Attribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setGameSession($this);
        }
        return $this;
    }

    /** @return Collection<int, Book> */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): self
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
            $book->setGameSession($this);
        }
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
            $entry->setGameSession($this);
        }
        return $this;
    }

    /** @return Collection<int, RollResult> */
    public function getRollResults(): Collection
    {
        return $this->roll_results;
    }

    public function addRollResult(RollResult $rollResult): self
    {
        if (!$this->roll_results->contains($rollResult)) {
            $this->roll_results->add($rollResult);
            $rollResult->setGameSession($this);
        }
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function __toString(): string
    {
        return $this->character_name ?? $this->id->toRfc4122();
    }
}
