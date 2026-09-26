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

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plugin_course_evaluation_answer')]
#[ORM\UniqueConstraint(name: 'uniq_submission_question', columns: ['submission_id', 'question_id'])]
class Answer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Submission::class)]
    #[ORM\JoinColumn(name: 'submission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Submission $submission;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $score = null;

    #[ORM\Column(name: 'text_value', type: 'text', nullable: true)]
    private ?string $textValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function setSubmission(Submission $submission): self
    {
        $this->submission = $submission;

        return $this;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function setQuestion(Question $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getTextValue(): ?string
    {
        return $this->textValue;
    }

    public function setTextValue(?string $textValue): self
    {
        $this->textValue = $textValue;

        return $this;
    }
}
