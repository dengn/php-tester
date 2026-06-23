<?php

declare(strict_types=1);

namespace MoTest\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity with lifecycle callbacks. PrePersist/PreUpdate stamp timestamps so
 * DoctrineScenarios2 can verify that Doctrine's event system fires correctly
 * when persisting/updating against MatrixOne.
 */
#[ORM\Entity]
#[ORM\Table(name: 'doc_events')]
#[ORM\HasLifecycleCallbacks]
class DocEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    public string $name = '';

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $updatedAt = null;

    /** Set true by the PrePersist callback so the test can assert it fired. */
    public bool $prePersistFired = false;

    /** Set true by the PreUpdate callback so the test can assert it fired. */
    public bool $preUpdateFired = false;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->prePersistFired = true;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
        $this->preUpdateFired = true;
    }
}
