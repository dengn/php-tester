<?php

declare(strict_types=1);

namespace MoTest\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'doc_books')]
class DocBook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 200)]
    public string $title = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?string $price = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $meta = null;

    #[ORM\ManyToOne(targetEntity: DocAuthor::class, inversedBy: 'books')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    public ?DocAuthor $author = null;

    /** @var Collection<int, DocTag> */
    #[ORM\ManyToMany(targetEntity: DocTag::class)]
    #[ORM\JoinTable(name: 'doc_book_tag')]
    public Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}
