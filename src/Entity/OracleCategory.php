<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OracleCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OracleCategoryRepository::class)]
#[ORM\Table(name: 'oracle_categories')]
class OracleCategory
{
    /** Valid category name values. */
    public const array VALID_NAMES = ['color', 'binding', 'smell', 'interior', 'genre', 'epoch'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $name;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $display_order = 0;

    /** @var Collection<int, OracleOption> */
    #[ORM\OneToMany(targetEntity: OracleOption::class, mappedBy: 'category', cascade: ['persist'])]
    private Collection $options;

    public function __construct()
    {
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    /** @return Collection<int, OracleOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(OracleOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setCategory($this);
        }
        return $this;
    }

    public function removeOption(OracleOption $option): self
    {
        $this->options->removeElement($option);
        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
