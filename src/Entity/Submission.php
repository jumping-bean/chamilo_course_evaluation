<?php

declare(strict_types=1);

namespace Chamilo\PluginBundle\CourseEvaluation\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plugin_course_evaluation_submission')]
#[ORM\UniqueConstraint(name: 'uniq_evaluation_user', columns: ['evaluation_id', 'user_id'])]
class Submission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Evaluation::class)]
    #[ORM\JoinColumn(name: 'evaluation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Evaluation $evaluation;

    #[ORM\Column(name: 'user_id', type: 'integer')]
    private int $userId = 0;

    #[ORM\Column(name: 'improvement_comment', type: 'text', nullable: true)]
    private ?string $improvementComment = null;

    #[ORM\Column(name: 'submitted_at', type: 'datetime')]
    private DateTime $submittedAt;

    public function __construct()
    {
        $this->submittedAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEvaluation(): Evaluation
    {
        return $this->evaluation;
    }

    public function setEvaluation(Evaluation $evaluation): self
    {
        $this->evaluation = $evaluation;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getImprovementComment(): ?string
    {
        return $this->improvementComment;
    }

    public function setImprovementComment(?string $improvementComment): self
    {
        $this->improvementComment = $improvementComment;

        return $this;
    }

    public function getSubmittedAt(): DateTime
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(DateTime $submittedAt): self
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }
}
