<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OracleOptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OracleOptionRepository::class)]
#[ORM\Table(name: 'oracle_options')]
class OracleOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OracleCategory::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OracleCategory $category;

    #[ORM\Column(length: 255)]
    private string $value;

    #[ORM\Column(length: 500, nullable: true, options: ['default' => ''])]
    private ?string $hint = '';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $display_order = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $is_active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): OracleCategory
    {
        return $this->category;
    }

    public function setCategory(OracleCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(?string $hint): self
    {
        $this->hint = $hint;
        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->display_order;
    }

    public function setDisplayOrder(int $display_order): self
    {
        $this->display_order = $display_order;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): self
    {
        $this->is_active = $is_active;
        return $this;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
