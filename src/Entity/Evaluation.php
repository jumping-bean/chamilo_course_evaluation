<?php

declare(strict_types=1);

namespace Chamilo\PluginBundle\CourseEvaluation\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plugin_course_evaluation_evaluation')]
#[ORM\UniqueConstraint(name: 'uniq_course_session', columns: ['course_id', 'session_id'])]
class Evaluation
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Template $template;

    #[ORM\Column(name: 'course_id', type: 'integer')]
    private int $courseId = 0;

    #[ORM\Column(name: 'session_id', type: 'integer')]
    private int $sessionId = 0;

    #[ORM\Column(name: 'instructor_id', type: 'integer', nullable: true)]
    private ?int $instructorId = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'questionnaire_version', type: 'integer')]
    private int $questionnaireVersion = 1;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'boolean')]
    private bool $anonymous = true;

    #[ORM\Column(name: 'opens_at', type: 'datetime', nullable: true)]
    private ?DateTime $opensAt = null;

    #[ORM\Column(name: 'closes_at', type: 'datetime', nullable: true)]
    private ?DateTime $closesAt = null;

    #[ORM\Column(name: 'require_certificate', type: 'boolean')]
    private bool $requireCertificate = false;

    #[ORM\Column(name: 'gradebook_evaluation_id', type: 'integer', nullable: true)]
    private ?int $gradebookEvaluationId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
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

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getCourseId(): int
    {
        return $this->courseId;
    }

    public function setCourseId(int $courseId): self
    {
        $this->courseId = $courseId;

        return $this;
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function setSessionId(int $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getInstructorId(): ?int
    {
        return $this->instructorId;
    }

    public function setInstructorId(?int $instructorId): self
    {
        $this->instructorId = $instructorId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getQuestionnaireVersion(): int
    {
        return $this->questionnaireVersion;
    }

    public function setQuestionnaireVersion(int $questionnaireVersion): self
    {
        $this->questionnaireVersion = max(1, $questionnaireVersion);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function setAnonymous(bool $anonymous): self
    {
        $this->anonymous = $anonymous;

        return $this;
    }

    public function getOpensAt(): ?DateTime
    {
        return $this->opensAt;
    }

    public function setOpensAt(?DateTime $opensAt): self
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?DateTime
    {
        return $this->closesAt;
    }

    public function setClosesAt(?DateTime $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function requiresCertificate(): bool
    {
        return $this->requireCertificate;
    }

    public function setRequireCertificate(bool $requireCertificate): self
    {
        $this->requireCertificate = $requireCertificate;

        return $this;
    }

    public function getGradebookEvaluationId(): ?int
    {
        return $this->gradebookEvaluationId;
    }

    public function setGradebookEvaluationId(?int $gradebookEvaluationId): self
    {
        $this->gradebookEvaluationId = $gradebookEvaluationId;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isOpenNow(?DateTime $now = null): bool
    {
        if (self::STATUS_OPEN !== $this->status) {
            return false;
        }

        $now ??= new DateTime();
        if (null !== $this->opensAt && $now < $this->opensAt) {
            return false;
        }
        if (null !== $this->closesAt && $now > $this->closesAt) {
            return false;
        }

        return true;
    }
}
