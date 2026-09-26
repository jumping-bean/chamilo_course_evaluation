<?php

declare(strict_types=1);

namespace Chamilo\PluginBundle\CourseEvaluation\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plugin_course_evaluation_question')]
class Question
{
    public const TYPE_SCALE = 'scale';
    public const TYPE_YES_NO = 'yes_no';
    public const TYPE_TEXT = 'text';
    public const TYPE_INSTRUCTOR = 'instructor';

    public const CATEGORY_INSTRUCTOR = 'instructor';
    public const CATEGORY_CONTENT = 'content';
    public const CATEGORY_DELIVERY = 'delivery';
    public const CATEGORY_ORGANIZATION = 'organization';
    public const CATEGORY_IMPROVEMENT = 'improvement';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Template $template;

    #[ORM\Column(type: 'string', length: 32)]
    private string $category = self::CATEGORY_CONTENT;

    #[ORM\Column(type: 'string', length: 16)]
    private string $type = self::TYPE_SCALE;

    #[ORM\Column(type: 'text')]
    private string $prompt = '';

    #[ORM\Column(name: 'help_text', type: 'text', nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(type: 'boolean')]
    private bool $required = true;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'scale_min', type: 'integer')]
    private int $scaleMin = 1;

    #[ORM\Column(name: 'scale_max', type: 'integer')]
    private int $scaleMax = 5;

    #[ORM\Column(name: 'scale_low_label', type: 'string', length: 64, nullable: true)]
    private ?string $scaleLowLabel = null;

    #[ORM\Column(name: 'scale_high_label', type: 'string', length: 64, nullable: true)]
    private ?string $scaleHighLabel = null;

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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): self
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getScaleMin(): int
    {
        return $this->scaleMin;
    }

    public function setScaleMin(int $scaleMin): self
    {
        $this->scaleMin = $scaleMin;

        return $this;
    }

    public function getScaleMax(): int
    {
        return $this->scaleMax;
    }

    public function setScaleMax(int $scaleMax): self
    {
        $this->scaleMax = $scaleMax;

        return $this;
    }

    public function getScaleLowLabel(): ?string
    {
        return $this->scaleLowLabel;
    }

    public function setScaleLowLabel(?string $scaleLowLabel): self
    {
        $this->scaleLowLabel = $scaleLowLabel;

        return $this;
    }

    public function getScaleHighLabel(): ?string
    {
        return $this->scaleHighLabel;
    }

    public function setScaleHighLabel(?string $scaleHighLabel): self
    {
        $this->scaleHighLabel = $scaleHighLabel;

        return $this;
    }

    public function isScored(): bool
    {
        return self::TYPE_TEXT !== $this->type && self::TYPE_INSTRUCTOR !== $this->type;
    }
}
