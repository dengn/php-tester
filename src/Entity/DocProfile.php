<?php

declare(strict_types=1);

namespace MoTest\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One-to-one owning side: each profile belongs to exactly one author.
 * Used by DoctrineScenarios2 to exercise OneToOne associations on MatrixOne.
 */
#[ORM\Entity]
#[ORM\Table(name: 'doc_profiles')]
class DocProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    public ?string $bio = null;

    #[ORM\OneToOne(targetEntity: DocAuthor::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    public ?DocAuthor $author = null;
}
