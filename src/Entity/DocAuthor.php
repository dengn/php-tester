<?php

declare(strict_types=1);

namespace MoTest\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'doc_authors')]
class DocAuthor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    public string $name = '';

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $birthYear = null;

    /** @var Collection<int, DocBook> */
    #[ORM\OneToMany(targetEntity: DocBook::class, mappedBy: 'author', cascade: ['persist', 'remove'])]
    public Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }
}
