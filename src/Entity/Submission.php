<?php

/*
 * Copyright (C) 2026 Mark Clarke <mark@jumpingbean.co.za>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

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
