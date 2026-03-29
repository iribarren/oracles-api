<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GamePhase;
use App\Repository\JournalEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\Table(name: 'journal_entries')]
class JournalEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: GamePhase::class)]
    private GamePhase $phase;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Journal entry content cannot be empty.')]
    private string $content;

    #[ORM\Column]
    private DateTimeImmutable $created_at;

    #[ORM\ManyToOne(targetEntity: GameSession::class, inversedBy: 'journal_entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSession $game_session;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'journal_entries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Book $book = null;

    public function __construct()
    {
        $this->created_at = new DateTimeImmutable();
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
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

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): self
    {
        $this->book = $book;
        return $this;
    }
}
