<?php

declare(strict_types=1);

namespace Chamilo\PluginBundle\CourseEvaluation;

use Chamilo\PluginBundle\CourseEvaluation\Entity\Answer;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Evaluation;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Question;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Submission;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Template;
use DateTime;
use Doctrine\DBAL\Connection;
use RuntimeException;

class EvaluationManager
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? \Database::getManager()->getConnection();
        if (class_exists(\CourseEvaluationPlugin::class)) {
            \CourseEvaluationPlugin::create()->update();
        }
    }

    public function categories(): array
    {
        return [
            Question::CATEGORY_INSTRUCTOR,
            Question::CATEGORY_CONTENT,
            Question::CATEGORY_DELIVERY,
            Question::CATEGORY_ORGANIZATION,
            Question::CATEGORY_IMPROVEMENT,
        ];
    }

    public function questionTypes(): array
    {
        return [
            Question::TYPE_SCALE,
            Question::TYPE_YES_NO,
            Question::TYPE_TEXT,
        ];
    }

    public function seedDefaultTemplate(int $createdBy = 0): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM plugin_course_evaluation_template WHERE scope = :scope AND title = :title',
            ['scope' => Template::SCOPE_GLOBAL, 'title' => 'Standard course evaluation']
        );
        if ($existing) {
            return;
        }

        $template = $this->saveTemplate(
            null,
            'Standard course evaluation',
            'Default questionnaire for instructor, content, delivery, and course improvement.',
            true
        );
        if ($createdBy > 0) {
            $template->setCreatedBy($createdBy);
            $this->updateTemplate($template);
        }

        $questions = [
            [Question::CATEGORY_INSTRUCTOR, Question::TYPE_INSTRUCTOR, 'Which instructors did you consult?'],
            [Question::CATEGORY_INSTRUCTOR, Question::TYPE_SCALE, 'The instructor explained the objectives clearly.'],
            [Question::CATEGORY_INSTRUCTOR, Question::TYPE_SCALE, 'The instructor was knowledgeable about the subject.'],
            [Question::CATEGORY_INSTRUCTOR, Question::TYPE_SCALE, 'The instructor encouraged participation and questions.'],
            [Question::CATEGORY_INSTRUCTOR, Question::TYPE_SCALE, 'The instructor gave useful feedback.'],
            [Question::CATEGORY_CONTENT, Question::TYPE_SCALE, 'The content matched the stated objectives.'],
            [Question::CATEGORY_CONTENT, Question::TYPE_SCALE, 'The materials were clear and well organized.'],
            [Question::CATEGORY_CONTENT, Question::TYPE_SCALE, 'The activities and exercises helped me learn.'],
            [Question::CATEGORY_CONTENT, Question::TYPE_SCALE, 'The level of difficulty was appropriate.'],
            [Question::CATEGORY_DELIVERY, Question::TYPE_SCALE, 'The pace of the course was appropriate.'],
            [Question::CATEGORY_DELIVERY, Question::TYPE_SCALE, 'The course was well organized.'],
            [Question::CATEGORY_DELIVERY, Question::TYPE_SCALE, 'The learning environment supported my learning.'],
            [Question::CATEGORY_ORGANIZATION, Question::TYPE_SCALE, 'I knew what was expected of me.'],
            [Question::CATEGORY_ORGANIZATION, Question::TYPE_YES_NO, 'I would recommend this course to a colleague.'],
            [Question::CATEGORY_IMPROVEMENT, Question::TYPE_TEXT, 'What should we keep doing?'],
            [Question::CATEGORY_IMPROVEMENT, Question::TYPE_TEXT, 'What should we change to improve this course?'],
        ];

        $position = 1;
        foreach ($questions as [$category, $type, $prompt]) {
            $question = (new Question())
                ->setTemplate($template)
                ->setCategory($category)
                ->setType($type)
                ->setPrompt($prompt)
                ->setRequired(!in_array($type, [Question::TYPE_TEXT, Question::TYPE_INSTRUCTOR], true))
                ->setHelpText(Question::TYPE_INSTRUCTOR === $type ? 'Select the instructors you asked for help. Leave this empty if you did not consult one.' : null)
                ->setPosition($position)
                ->setScaleMin(1)
                ->setScaleMax(Question::TYPE_YES_NO === $type ? 1 : 5)
                ->setScaleLowLabel(Question::TYPE_SCALE === $type ? 'Strongly disagree' : null)
                ->setScaleHighLabel(Question::TYPE_SCALE === $type ? 'Strongly agree' : null);
            $this->insertQuestion($question);
            ++$position;
        }
    }

    /**
     * @return Template[]
     */
    public function globalTemplates(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM plugin_course_evaluation_template WHERE scope = :scope';
        $params = ['scope' => Template::SCOPE_GLOBAL];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY title ASC';

        return array_map($this->templateFromRow(...), $this->db->fetchAllAssociative($sql, $params));
    }

    /**
     * @return Template[]
     */
    public function courseTemplates(int $courseId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT * FROM plugin_course_evaluation_template
             WHERE scope = :scope AND course_id = :course
             ORDER BY updated_at DESC',
            ['scope' => Template::SCOPE_COURSE, 'course' => $courseId]
        );

        return array_map($this->templateFromRow(...), $rows);
    }

    public function findTemplate(int $id): ?Template
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM plugin_course_evaluation_template WHERE id = :id',
            ['id' => $id]
        );

        return $row ? $this->templateFromRow($row) : null;
    }

    public function saveTemplate(
        ?Template $template,
        string $title,
        ?string $description,
        bool $isActive,
        string $scope = Template::SCOPE_GLOBAL,
        ?int $courseId = null
    ): Template {
        $template ??= new Template();
        $template
            ->setTitle($title)
            ->setDescription($description)
            ->setIsActive($isActive)
            ->setScope($scope)
            ->setCourseId($courseId)
            ->touch();
        if (null === $template->getCreatedBy()) {
            $userId = (int) api_get_user_id();
            $template->setCreatedBy($userId > 0 ? $userId : null);
        }
        if ($template->getId()) {
            $this->updateTemplate($template);
        } else {
            $now = $template->getUpdatedAt()->format('Y-m-d H:i:s');
            $this->db->insert('plugin_course_evaluation_template', [
                'title' => $template->getTitle(),
                'description' => $template->getDescription(),
                'scope' => $template->getScope(),
                'course_id' => $template->getCourseId(),
                'source_template_id' => $template->getSourceTemplateId(),
                'is_active' => $template->isActive() ? 1 : 0,
                'created_by' => $template->getCreatedBy(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $template->setId((int) $this->db->lastInsertId());
        }

        return $template;
    }

    public function deleteTemplate(Template $template): void
    {
        $this->db->delete('plugin_course_evaluation_template', ['id' => $template->getId()]);
    }

    /**
     * @return Question[]
     */
    public function questionsFor(Template $template): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT * FROM plugin_course_evaluation_question
             WHERE template_id = :template
             ORDER BY position ASC, id ASC',
            ['template' => $template->getId()]
        );

        return array_map(fn (array $row) => $this->questionFromRow($row, $template), $rows);
    }

    public function ensureSupportQuestion(Template $template): void
    {
        if ($this->templateHasSubmissions($template)) {
            return;
        }
        foreach ($this->questionsFor($template) as $question) {
            if (Question::TYPE_INSTRUCTOR === $question->getType()) {
                return;
            }
        }
        $question = (new Question())
            ->setTemplate($template)
            ->setCategory(Question::CATEGORY_INSTRUCTOR)
            ->setType(Question::TYPE_INSTRUCTOR)
            ->setPrompt('Which instructors did you consult?')
            ->setHelpText('Select the instructors you asked for help. Leave this empty if you did not consult one.')
            ->setRequired(false)
            ->setPosition($this->nextPosition($template));
        $this->insertQuestion($question);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function availableInstructors(int $courseId, int $sessionId): array
    {
        $ids = [];
        if ($sessionId > 0) {
            foreach ($this->instructorChoices($courseId, $sessionId) as $choice) {
                $ids[] = $choice['id'];
            }
        }
        if ($this->db->createSchemaManager()->tablesExist(['course_rel_user'])) {
            $ids = array_merge($ids, $this->db->fetchFirstColumn(
                'SELECT user_id FROM course_rel_user WHERE c_id = :course AND status = 1',
                ['course' => $courseId]
            ));
        }
        $choices = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id > 0) {
                $choices[] = ['id' => $id, 'name' => $this->userName($id)];
            }
        }

        return $choices;
    }

    public function findQuestion(int $id): ?Question
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM plugin_course_evaluation_question WHERE id = :id',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }
        $template = $this->findTemplate((int) $row['template_id']);

        return $template ? $this->questionFromRow($row, $template) : null;
    }

    public function saveQuestion(Template $template, ?Question $question, array $data): Question
    {
        $type = in_array($data['type'] ?? '', $this->questionTypes(), true)
            ? $data['type']
            : Question::TYPE_SCALE;
        $category = in_array($data['category'] ?? '', $this->categories(), true)
            ? $data['category']
            : Question::CATEGORY_CONTENT;

        if (!$question instanceof Question) {
            $question = new Question();
            $question->setTemplate($template);
            $question->setPosition($this->nextPosition($template));
        }

        $scaleMax = Question::TYPE_YES_NO === $type ? 1 : max(2, (int) ($data['scale_max'] ?? 5));
        $lowLabel = null;
        $highLabel = null;
        if (Question::TYPE_SCALE === $type) {
            $lowLabel = $this->nullableString($data['scale_low_label'] ?? null);
            $highLabel = $this->nullableString($data['scale_high_label'] ?? null);
        } elseif (Question::TYPE_YES_NO === $type) {
            $lowLabel = $this->nullableString($data['no_label'] ?? null);
            $highLabel = $this->nullableString($data['yes_label'] ?? null);
        }
        $question
            ->setCategory($category)
            ->setType($type)
            ->setPrompt(trim((string) ($data['prompt'] ?? '')))
            ->setHelpText($this->nullableString($data['help_text'] ?? null))
            ->setRequired(!empty($data['required']))
            ->setScaleMin(1)
            ->setScaleMax($scaleMax)
            ->setScaleLowLabel($lowLabel)
            ->setScaleHighLabel($highLabel);

        $template->touch();
        if ($question->getId()) {
            $this->updateQuestion($question);
        } else {
            $this->insertQuestion($question);
        }
        $this->updateTemplate($template);

        return $question;
    }

    public function deleteQuestion(Question $question): void
    {
        $this->db->delete('plugin_course_evaluation_question', ['id' => $question->getId()]);
        $this->updateTemplate($question->getTemplate()->touch());
    }

    public function moveQuestion(Question $question, int $direction): void
    {
        $questions = $this->questionsFor($question->getTemplate());
        $index = null;
        foreach ($questions as $i => $item) {
            if ($item->getId() === $question->getId()) {
                $index = $i;
                break;
            }
        }
        if (null === $index) {
            return;
        }
        $swapWith = $index + $direction;
        if (!isset($questions[$swapWith])) {
            return;
        }
        $current = $questions[$index]->getPosition();
        $questions[$index]->setPosition($questions[$swapWith]->getPosition());
        $questions[$swapWith]->setPosition($current);
        if ($questions[$index]->getPosition() === $questions[$swapWith]->getPosition()) {
            $questions[$swapWith]->setPosition($current + $direction);
        }
        $this->updateQuestion($questions[$index]);
        $this->updateQuestion($questions[$swapWith]);
    }

    public function copyCourseEvaluationToSession(int $courseId, int $sessionId, int $userId, string $title = ''): ?Evaluation
    {
        if ($sessionId <= 0) {
            return null;
        }
        $existing = $this->findEvaluation($courseId, $sessionId);
        if ($existing instanceof Evaluation) {
            return $existing;
        }
        $courseEvaluation = $this->findEvaluation($courseId, 0);
        if (!$courseEvaluation instanceof Evaluation) {
            return null;
        }
        $template = $this->copyTemplateToCourse(
            $courseEvaluation->getTemplate(),
            $courseId,
            $userId,
            [Question::TYPE_INSTRUCTOR]
        );
        if (!$this->questionsFor($template)) {
            return null;
        }
        $opensAt = null;
        $closesAt = null;
        if ($this->db->createSchemaManager()->tablesExist(['session'])) {
            $row = $this->db->fetchAssociative(
                'SELECT access_start_date, access_end_date FROM session WHERE id = :id',
                ['id' => $sessionId]
            );
            if ($row && !empty($row['access_start_date'])) {
                $opensAt = new DateTime((string) $row['access_start_date']);
            }
            if ($row && !empty($row['access_end_date'])) {
                $closesAt = new DateTime((string) $row['access_end_date']);
            }
        }

        return $this->openEvaluation(
            $template,
            $courseId,
            $sessionId,
            $this->resolveInstructorId($courseId, $sessionId),
            '' !== $title ? $title : $courseEvaluation->getTitle(),
            $courseEvaluation->isAnonymous(),
            $opensAt,
            $closesAt,
            $courseEvaluation->requiresCertificate()
        );
    }

    public function copyTemplateToCourse(Template $source, int $courseId, int $userId, array $skipTypes = []): Template
    {
        $copy = (new Template())
            ->setTitle($source->getTitle())
            ->setDescription($source->getDescription())
            ->setScope(Template::SCOPE_COURSE)
            ->setCourseId($courseId)
            ->setSourceTemplateId($source->getId())
            ->setIsActive(true)
            ->setCreatedBy($userId > 0 ? $userId : null);
        $this->saveTemplate(
            $copy,
            $copy->getTitle(),
            $copy->getDescription(),
            true,
            Template::SCOPE_COURSE,
            $courseId
        );

        foreach ($this->questionsFor($source) as $question) {
            if (in_array($question->getType(), $skipTypes, true)) {
                continue;
            }
            $clone = (new Question())
                ->setTemplate($copy)
                ->setCategory($question->getCategory())
                ->setType($question->getType())
                ->setPrompt($question->getPrompt())
                ->setHelpText($question->getHelpText())
                ->setRequired($question->isRequired())
                ->setPosition($question->getPosition())
                ->setScaleMin($question->getScaleMin())
                ->setScaleMax($question->getScaleMax())
                ->setScaleLowLabel($question->getScaleLowLabel())
                ->setScaleHighLabel($question->getScaleHighLabel());
            $this->insertQuestion($clone);
        }

        return $copy;
    }

    public function templateHasSubmissions(Template $template): bool
    {
        $count = (int) $this->db->fetchOne(
            'SELECT COUNT(s.id)
             FROM plugin_course_evaluation_submission s
             INNER JOIN plugin_course_evaluation_evaluation c ON c.id = s.evaluation_id
             WHERE c.template_id = :template OR s.template_id = :template',
            ['template' => $template->getId()]
        );

        return $count > 0;
    }

    public function findEvaluation(int $courseId, int $sessionId): ?Evaluation
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM plugin_course_evaluation_evaluation WHERE course_id = :course AND session_id = :session',
            ['course' => $courseId, 'session' => $sessionId]
        );

        return $row ? $this->evaluationFromRow($row) : null;
    }

    public function findEvaluationById(int $id): ?Evaluation
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM plugin_course_evaluation_evaluation WHERE id = :id',
            ['id' => $id]
        );

        return $row ? $this->evaluationFromRow($row) : null;
    }

    public function openEvaluation(
        Template $template,
        int $courseId,
        int $sessionId,
        ?int $instructorId,
        string $title,
        bool $anonymous,
        ?DateTime $opensAt,
        ?DateTime $closesAt,
        bool $requireCertificate
    ): Evaluation {
        $evaluation = $this->findEvaluation($courseId, $sessionId) ?? new Evaluation();
        $version = max(1, $evaluation->getQuestionnaireVersion());
        if ($evaluation->getId() && $evaluation->getTemplate()->getId() !== $template->getId()) {
            $this->archiveCurrentVersion($evaluation);
            ++$version;
        }
        $evaluation
            ->setQuestionnaireVersion($version)
            ->setTemplate($template)
            ->setCourseId($courseId)
            ->setSessionId($sessionId)
            ->setInstructorId($instructorId)
            ->setTitle($title)
            ->setAnonymous($anonymous)
            ->setOpensAt($opensAt)
            ->setClosesAt($closesAt)
            ->setRequireCertificate($requireCertificate)
            ->setStatus(Evaluation::STATUS_OPEN);
        $this->saveEvaluation($evaluation);
        if ($requireCertificate) {
            try {
                $this->attachCertificateRequirement($evaluation);
            } catch (RuntimeException $exception) {
                $evaluation->setRequireCertificate(false);
                $this->saveEvaluation($evaluation);
                throw $exception;
            }
        } else {
            $this->clearCertificateRequirement($evaluation);
        }

        return $evaluation;
    }

    public function setEvaluationStatus(Evaluation $evaluation, string $status): void
    {
        $evaluation->setStatus($status);
        $this->db->update(
            'plugin_course_evaluation_evaluation',
            ['status' => $status],
            ['id' => $evaluation->getId()]
        );
    }

    public function hasEvaluationStarted(Evaluation $evaluation, int $userId): bool
    {
        return false !== $this->db->fetchOne(
            'SELECT id FROM plugin_course_evaluation_start WHERE evaluation_id = :evaluation AND user_id = :user',
            ['evaluation' => $evaluation->getId(), 'user' => $userId]
        );
    }

    public function markEvaluationStarted(Evaluation $evaluation, int $userId): void
    {
        if ($this->hasEvaluationStarted($evaluation, $userId)) {
            return;
        }
        $this->db->insert('plugin_course_evaluation_start', [
            'evaluation_id' => $evaluation->getId(),
            'user_id' => $userId,
            'started_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function learnerCanSubmit(Evaluation $evaluation, int $userId, ?DateTime $now = null): bool
    {
        if ($this->findSubmission($evaluation, $userId)) {
            return false;
        }
        $now ??= new DateTime();
        if ($evaluation->isOpenNow($now)) {
            return true;
        }
        $closesAt = $this->extensionClosesAt($evaluation, $userId);

        return null !== $closesAt && $now <= $closesAt;
    }

    public function grantExtension(Evaluation $evaluation, int $userId, DateTime $closesAt, int $grantedBy): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM plugin_course_evaluation_extension WHERE evaluation_id = :evaluation AND user_id = :user',
            ['evaluation' => $evaluation->getId(), 'user' => $userId]
        );
        $values = [
            'closes_at' => $closesAt->format('Y-m-d H:i:s'),
            'granted_by' => $grantedBy > 0 ? $grantedBy : null,
        ];
        if ($existing) {
            $this->db->update('plugin_course_evaluation_extension', $values, ['id' => (int) $existing]);

            return;
        }
        $values['evaluation_id'] = $evaluation->getId();
        $values['user_id'] = $userId;
        $values['created_at'] = (new DateTime())->format('Y-m-d H:i:s');
        $this->db->insert('plugin_course_evaluation_extension', $values);
    }

    public function revokeExtension(Evaluation $evaluation, int $userId): void
    {
        $this->db->delete('plugin_course_evaluation_extension', [
            'evaluation_id' => $evaluation->getId(),
            'user_id' => $userId,
        ]);
    }

    /**
     * @return list<array{user_id: int, closes_at: string}>
     */
    public function extensionsFor(Evaluation $evaluation): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT user_id, closes_at FROM plugin_course_evaluation_extension
             WHERE evaluation_id = :evaluation ORDER BY closes_at ASC',
            ['evaluation' => $evaluation->getId()]
        );
    }

    /**
     * @return list<array{name: string, session_id: int, submitted_at: ?string, extension: ?string, anonymous: bool}>
     */
    public function learnerStatuses(Evaluation $evaluation, int $courseId, int $sessionId): array
    {
        $contexts = $sessionId > 0 ? [$sessionId] : array_merge([0], $this->courseSessionIds($courseId));
        $rows = [];
        foreach ($contexts as $contextSessionId) {
            $contextEvaluation = $contextSessionId === $sessionId
                ? $evaluation
                : $this->findEvaluation($courseId, $contextSessionId);
            $submitted = [];
            if ($contextEvaluation) {
                foreach ($this->responsesFor($contextEvaluation) as $row) {
                    $submitted[(int) $row['user_id']] = (string) $row['submitted_at'];
                }
            }
            $extensions = [];
            if ($contextEvaluation && $contextSessionId > 0) {
                foreach ($this->extensionsFor($contextEvaluation) as $extension) {
                    $extensions[(int) $extension['user_id']] = (string) $extension['closes_at'];
                }
            }
            $ids = $this->learnerIds($courseId, $contextSessionId);
            if ($submitted) {
                $ids = array_values(array_unique(array_merge($ids, array_keys($submitted))));
            }
            foreach ($ids as $userId) {
                $rows[] = [
                    'name' => $this->userName((int) $userId),
                    'session_id' => $contextSessionId,
                    'submitted_at' => $submitted[$userId] ?? null,
                    'extension' => $extensions[$userId] ?? null,
                    'anonymous' => $contextEvaluation instanceof Evaluation && $contextEvaluation->isAnonymous(),
                ];
            }
        }
        usort($rows, static function (array $left, array $right): int {
            $bySession = $left['session_id'] <=> $right['session_id'];

            return 0 !== $bySession ? $bySession : strcasecmp($left['name'], $right['name']);
        });

        return $rows;
    }

    public function learnersWithoutSubmission(Evaluation $evaluation, int $courseId, int $sessionId): array
    {
        $submitted = array_fill_keys(array_map(
            'intval',
            $this->db->fetchFirstColumn(
                'SELECT user_id FROM plugin_course_evaluation_submission WHERE evaluation_id = :evaluation',
                ['evaluation' => $evaluation->getId()]
            )
        ), true);
        $learners = [];
        foreach ($this->learnerIds($courseId, $sessionId) as $userId) {
            if (isset($submitted[$userId])) {
                continue;
            }
            $learners[] = ['id' => $userId, 'name' => $this->userName($userId)];
        }

        return $learners;
    }

    public function isCourseLearner(int $userId, int $courseId, int $sessionId): bool
    {
        return in_array($userId, $this->learnerIds($courseId, $sessionId), true);
    }

    public function findSubmission(Evaluation $evaluation, int $userId): ?Submission
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM plugin_course_evaluation_submission WHERE evaluation_id = :evaluation AND user_id = :user',
            ['evaluation' => $evaluation->getId(), 'user' => $userId]
        );
        if (!$row) {
            return null;
        }

        return (new Submission())
            ->setId((int) $row['id'])
            ->setEvaluation($evaluation)
            ->setUserId((int) $row['user_id'])
            ->setImprovementComment($row['improvement_comment'])
            ->setSubmittedAt(new DateTime((string) $row['submitted_at']));
    }

    /**
     * @param array<int, array{score?: int|null, text?: string|null}> $answers keyed by question id
     */
    public function submit(Evaluation $evaluation, int $userId, array $answers, ?string $improvementComment): Submission
    {
        $existing = $this->findSubmission($evaluation, $userId);
        if ($existing instanceof Submission) {
            return $existing;
        }

        $submission = (new Submission())
            ->setEvaluation($evaluation)
            ->setUserId($userId)
            ->setImprovementComment($this->nullableString($improvementComment))
            ->setSubmittedAt(new DateTime());
        \Database::getManager()->wrapInTransaction(function () use ($evaluation, $userId, $answers, $submission): void {
            $this->db->insert('plugin_course_evaluation_submission', [
                'evaluation_id' => $evaluation->getId(),
                'user_id' => $userId,
                'template_id' => $evaluation->getTemplate()->getId(),
                'questionnaire_version' => $evaluation->getQuestionnaireVersion(),
                'improvement_comment' => $submission->getImprovementComment(),
                'submitted_at' => $submission->getSubmittedAt()->format('Y-m-d H:i:s'),
            ]);
            $submission->setId((int) $this->db->lastInsertId());

            foreach ($this->questionsFor($evaluation->getTemplate()) as $question) {
                $payload = $answers[$question->getId()] ?? [];
                $this->db->insert('plugin_course_evaluation_answer', [
                    'submission_id' => $submission->getId(),
                    'question_id' => $question->getId(),
                    'score' => isset($payload['score']) ? (int) $payload['score'] : null,
                    'text_value' => $this->nullableString($payload['text'] ?? null),
                ]);
            }

            $this->ensureCertificateResult($evaluation, $userId);
        });

        return $submission;
    }

    public function ensureCertificateResult(Evaluation $evaluation, int $userId): void
    {
        $evaluationId = $evaluation->getGradebookEvaluationId();
        if (!$evaluation->requiresCertificate() || null === $evaluationId || $evaluationId <= 0 || $userId <= 0) {
            return;
        }
        if (!$this->db->createSchemaManager()->tablesExist(['gradebook_result'])) {
            return;
        }
        if (!class_exists(\Chamilo\CoreBundle\Entity\GradebookEvaluation::class) || !class_exists(\Chamilo\CoreBundle\Entity\GradebookResult::class)) {
            return;
        }
        $em = \Database::getManager();
        $evaluation = $em->find(\Chamilo\CoreBundle\Entity\GradebookEvaluation::class, $evaluationId);
        $user = $em->find(\Chamilo\CoreBundle\Entity\User::class, $userId);
        if (!$evaluation instanceof \Chamilo\CoreBundle\Entity\GradebookEvaluation || !$user instanceof \Chamilo\CoreBundle\Entity\User) {
            return;
        }
        $result = $em->getRepository(\Chamilo\CoreBundle\Entity\GradebookResult::class)->findOneBy([
            'evaluation' => $evaluation,
            'user' => $user,
        ]);
        if (!$result instanceof \Chamilo\CoreBundle\Entity\GradebookResult) {
            $result = new \Chamilo\CoreBundle\Entity\GradebookResult();
            $result
                ->setEvaluation($evaluation)
                ->setUser($user)
                ->setCreatedAt(new DateTime());
            $em->persist($result);
        }
        $result->setScore(1);
        if (!$em->getConnection()->isTransactionActive()) {
            $em->flush();
        }
    }

    /**
     * @return list<array{user_id: int, improvement_comment: ?string, submitted_at: string, answers: array<int, array{score: mixed, text_value: mixed}>}>
     */
    public function responsesFor(Evaluation $evaluation): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, user_id, template_id, questionnaire_version, improvement_comment, submitted_at
             FROM plugin_course_evaluation_submission
             WHERE evaluation_id = :evaluation
             ORDER BY submitted_at DESC',
            ['evaluation' => $evaluation->getId()]
        );
        $responses = [];
        foreach ($rows as $row) {
            $answers = [];
            $answerRows = $this->db->fetchAllAssociative(
                'SELECT question_id, score, text_value FROM plugin_course_evaluation_answer WHERE submission_id = :submission',
                ['submission' => $row['id']]
            );
            foreach ($answerRows as $answer) {
                $answers[(int) $answer['question_id']] = [
                    'score' => $answer['score'],
                    'text_value' => $answer['text_value'],
                ];
            }
            $templateId = (int) ($row['template_id'] ?? 0);
            if ($templateId <= 0) {
                $templateId = (int) $evaluation->getTemplate()->getId();
            }
            $responses[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'template_id' => $templateId,
                'version' => (int) ($row['questionnaire_version'] ?? 0) ?: $evaluation->getQuestionnaireVersion(),
                'improvement_comment' => $row['improvement_comment'],
                'submitted_at' => (string) $row['submitted_at'],
                'answers' => $answers,
            ];
        }

        return $responses;
    }

    public function submissionCount(Evaluation $evaluation): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(id) FROM plugin_course_evaluation_submission WHERE evaluation_id = :evaluation',
            ['evaluation' => $evaluation->getId()]
        );
    }

    public function submissionCountForContext(int $courseId, int $sessionId, ?Evaluation $evaluation): int
    {
        return count($this->contextSubmissions($courseId, $sessionId, $evaluation));
    }

    /**
     * Submissions for this course context. Self-paced also includes a direct learner's
     * submission when it was stored on another evaluation for the same course.
     *
     * @return list<array{id: int, user_id: int, template_id: int, version: int, improvement_comment: ?string, submitted_at: string, answers: array<int, array{score: mixed, text_value: mixed}>, anonymous: bool}>
     */
    public function contextSubmissions(int $courseId, int $sessionId, ?Evaluation $evaluation): array
    {
        if ($sessionId > 0) {
            if (!$evaluation) {
                return [];
            }
            $rows = $this->responsesFor($evaluation);
            foreach ($rows as &$row) {
                $row['anonymous'] = $evaluation->isAnonymous();
                $row['session_id'] = $evaluation->getSessionId();
            }
            unset($row);

            return $rows;
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT s.id, s.user_id, s.template_id, s.questionnaire_version, s.improvement_comment, s.submitted_at,
                    c.anonymous, c.session_id, c.questionnaire_version AS evaluation_version, c.template_id AS evaluation_template_id
             FROM plugin_course_evaluation_submission s
             INNER JOIN plugin_course_evaluation_evaluation c ON c.id = s.evaluation_id
             WHERE c.course_id = :course
             ORDER BY s.submitted_at DESC',
            ['course' => $courseId]
        );
        $responses = [];
        foreach ($rows as $row) {
            $answers = [];
            foreach ($this->db->fetchAllAssociative(
                'SELECT question_id, score, text_value FROM plugin_course_evaluation_answer WHERE submission_id = :submission',
                ['submission' => $row['id']]
            ) as $answer) {
                $answers[(int) $answer['question_id']] = [
                    'score' => $answer['score'],
                    'text_value' => $answer['text_value'],
                ];
            }
            $templateId = (int) ($row['template_id'] ?? 0);
            if ($templateId <= 0) {
                $templateId = (int) $row['evaluation_template_id'];
            }
            $responses[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'template_id' => $templateId,
                'version' => (int) ($row['questionnaire_version'] ?? 0) ?: (int) $row['evaluation_version'],
                'improvement_comment' => $row['improvement_comment'],
                'submitted_at' => (string) $row['submitted_at'],
                'answers' => $answers,
                'anonymous' => 1 === (int) $row['anonymous'],
                'session_id' => (int) $row['session_id'],
            ];
        }

        return $responses;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function questionReport(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                'q.category',
                'q.type',
                'q.prompt',
                'q.scale_max',
                'MIN(q.position) AS position',
                'AVG(a.score) AS average_score',
                'COUNT(a.score) AS score_count',
                'c.course_id',
                'c.instructor_id'
            )
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->groupBy('q.category', 'q.type', 'q.prompt', 'q.scale_max', 'c.course_id', 'c.instructor_id')
            ->orderBy('q.category', 'ASC')
            ->addOrderBy('position', 'ASC');

        $this->applyReportFilters($qb, $filters, 's', 'c', 'q');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categoryReport(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                'q.category',
                'AVG(a.score) AS average_score',
                'COUNT(a.score) AS score_count',
                'COUNT(DISTINCT s.id) AS response_count'
            )
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where('a.score IS NOT NULL')
            ->andWhere("q.type NOT IN ('text', 'instructor')")
            ->groupBy('q.category')
            ->orderBy('q.category', 'ASC');
        if (!empty($filters['by_session'])) {
            $qb->addSelect('c.session_id')
                ->addGroupBy('c.session_id')
                ->addOrderBy('c.session_id', 'ASC');
        }

        $this->applyReportFilters($qb, $filters, 's', 'c', 'q');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Scored category averages for each course, so courses can be compared with the overall average.
     *
     * @return list<array{category: string, course_id: int, average_score: float, score_count: int, response_count: int}>
     */
    public function categoryByCourse(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                'q.category',
                'c.course_id',
                'AVG(a.score) AS average_score',
                'COUNT(a.score) AS score_count',
                'COUNT(DISTINCT s.id) AS response_count'
            )
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where('a.score IS NOT NULL')
            ->andWhere("q.type NOT IN ('text', 'instructor')")
            ->groupBy('q.category', 'c.course_id')
            ->orderBy('q.category', 'ASC')
            ->addOrderBy('c.course_id', 'ASC');
        $this->applyReportFilters($qb, $filters, 's', 'c', 'q');
        $rows = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = [
                'category' => (string) $row['category'],
                'course_id' => (int) $row['course_id'],
                'average_score' => (float) $row['average_score'],
                'score_count' => (int) $row['score_count'],
                'response_count' => (int) $row['response_count'],
            ];
        }

        return $rows;
    }

    /**
     * Scored questions, including ones no longer on the current questionnaire.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scoredQuestionReport(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                'q.category',
                'q.prompt',
                'MIN(q.position) AS position',
                'AVG(a.score) AS average_score',
                'COUNT(a.score) AS score_count',
                'MAX(s.submitted_at) AS last_answered'
            )
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where('a.score IS NOT NULL')
            ->andWhere("q.type NOT IN ('text', 'instructor')")
            ->groupBy('q.category', 'q.prompt')
            ->orderBy('q.category', 'ASC')
            ->addOrderBy('position', 'ASC');
        $this->applyReportFilters($qb, $filters, 's', 'c', 'q');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Instructor-category scores attributed to a session coach, or to the mentors a self-paced learner named.
     *
     * @return list<array{mentor_id: int, average_score: ?float, score_count: int, response_count: int, named_count: int}>
     */
    public function mentorPerformance(array $filters): array
    {
        $scoreQb = $this->db->createQueryBuilder()
            ->select('a.score', 's.id AS submission_id', 'c.session_id', 'c.instructor_id')
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where('a.score IS NOT NULL')
            ->andWhere('q.category = :instructorCategory')
            ->andWhere("q.type NOT IN ('text', 'instructor')")
            ->setParameter('instructorCategory', Question::CATEGORY_INSTRUCTOR);
        $this->applyReportFilters($scoreQb, $filters, 's', 'c', 'q');

        $pickQb = $this->db->createQueryBuilder()
            ->select('s.id AS submission_id', 'a.text_value')
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where("q.type = 'instructor'");
        $this->applyReportFilters($pickQb, $filters, 's', 'c', 'q');
        $picks = [];
        foreach ($pickQb->executeQuery()->fetchAllAssociative() as $row) {
            $picks[(int) $row['submission_id']] = array_values(array_filter(array_map('intval', explode(',', (string) $row['text_value']))));
        }

        $coachId = (int) ($filters['coach_id'] ?? 0);
        $buckets = [];
        $touch = static function (array &$buckets, int $mentorId): void {
            if (!isset($buckets[$mentorId])) {
                $buckets[$mentorId] = ['scores' => [], 'responses' => [], 'named' => [], 'sessions' => [], 'self_paced' => []];
            }
        };
        foreach ($picks as $submissionId => $mentorIds) {
            foreach ($mentorIds as $mentorId) {
                if ($mentorId <= 0 || ($coachId > 0 && $mentorId !== $coachId)) {
                    continue;
                }
                $touch($buckets, $mentorId);
                $buckets[$mentorId]['named'][$submissionId] = true;
            }
        }
        foreach ($scoreQb->executeQuery()->fetchAllAssociative() as $row) {
            $mentors = [];
            if ((int) $row['session_id'] > 0 && (int) $row['instructor_id'] > 0) {
                $mentors[] = (int) $row['instructor_id'];
            } else {
                $mentors = $picks[(int) $row['submission_id']] ?? [];
            }
            foreach (array_unique($mentors) as $mentorId) {
                if ($mentorId <= 0 || ($coachId > 0 && $mentorId !== $coachId)) {
                    continue;
                }
                $touch($buckets, $mentorId);
                $buckets[$mentorId]['scores'][] = (float) $row['score'];
                $buckets[$mentorId]['responses'][(int) $row['submission_id']] = true;
                $role = (int) $row['session_id'] > 0 ? 'sessions' : 'self_paced';
                $buckets[$mentorId][$role][(int) $row['submission_id']] = true;
            }
        }

        $rows = [];
        foreach ($buckets as $mentorId => $bucket) {
            $count = count($bucket['scores']);
            $rows[] = [
                'mentor_id' => $mentorId,
                'average_score' => $count > 0 ? array_sum($bucket['scores']) / $count : null,
                'score_count' => $count,
                'response_count' => count($bucket['responses']),
                'session_count' => count($bucket['sessions']),
                'self_paced_count' => count($bucket['self_paced']),
                'named_count' => count($bucket['named']),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $b['score_count'] <=> $a['score_count']);

        return $rows;
    }

    /**
     * Instructor-category scores for one coach in one course and session.
     *
     * @return list<array{mentor_id: int, course_id: int, session_id: int, average_score: ?float, score_count: int, response_count: int}>
     */
    public function mentorContexts(array $filters): array
    {
        $scoreQb = $this->db->createQueryBuilder()
            ->select('a.score', 's.id AS submission_id', 'c.course_id', 'c.session_id', 'c.instructor_id')
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where('a.score IS NOT NULL')
            ->andWhere('q.category = :instructorCategory')
            ->andWhere("q.type NOT IN ('text', 'instructor')")
            ->setParameter('instructorCategory', Question::CATEGORY_INSTRUCTOR);
        $this->applyReportFilters($scoreQb, $filters, 's', 'c', 'q');

        $pickQb = $this->db->createQueryBuilder()
            ->select('s.id AS submission_id', 'a.text_value')
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where("q.type = 'instructor'");
        $this->applyReportFilters($pickQb, $filters, 's', 'c', 'q');
        $picks = [];
        foreach ($pickQb->executeQuery()->fetchAllAssociative() as $row) {
            $picks[(int) $row['submission_id']] = array_values(array_filter(array_map('intval', explode(',', (string) $row['text_value']))));
        }

        $buckets = [];
        foreach ($scoreQb->executeQuery()->fetchAllAssociative() as $row) {
            $mentors = ((int) $row['session_id'] > 0 && (int) $row['instructor_id'] > 0)
                ? [(int) $row['instructor_id']]
                : ($picks[(int) $row['submission_id']] ?? []);
            $courseId = (int) $row['course_id'];
            $sessionId = (int) $row['session_id'];
            foreach (array_unique($mentors) as $mentorId) {
                if ($mentorId <= 0) {
                    continue;
                }
                $key = $mentorId.':'.$courseId.':'.$sessionId;
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'mentor_id' => $mentorId,
                        'course_id' => $courseId,
                        'session_id' => $sessionId,
                        'scores' => [],
                        'responses' => [],
                    ];
                }
                $buckets[$key]['scores'][] = (float) $row['score'];
                $buckets[$key]['responses'][(int) $row['submission_id']] = true;
            }
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $count = count($bucket['scores']);
            $rows[] = [
                'mentor_id' => $bucket['mentor_id'],
                'course_id' => $bucket['course_id'],
                'session_id' => $bucket['session_id'],
                'average_score' => $count > 0 ? array_sum($bucket['scores']) / $count : null,
                'score_count' => $count,
                'response_count' => count($bucket['responses']),
            ];
        }
        usort($rows, static function (array $left, array $right): int {
            $byName = $left['mentor_id'] <=> $right['mentor_id'];

            return 0 !== $byName ? $byName : ($left['course_id'] <=> $right['course_id'] ?: $left['session_id'] <=> $right['session_id']);
        });

        return $rows;
    }

    /**
     * @return list<array{session_id: int, instructor_id: int, response_count: int, average_score: ?float}>
     */
    public function sessionAggregates(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                'c.session_id',
                'c.instructor_id',
                'COUNT(DISTINCT s.id) AS response_count',
                "AVG(CASE WHEN q.category = 'instructor' AND q.type NOT IN ('text', 'instructor') THEN a.score ELSE NULL END) AS average_score"
            )
            ->from('plugin_course_evaluation_submission', 's')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->leftJoin('s', 'plugin_course_evaluation_answer', 'a', 'a.submission_id = s.id')
            ->leftJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->groupBy('c.session_id', 'c.instructor_id')
            ->orderBy('c.session_id', 'ASC');
        $this->applyReportFilters($qb, $filters, 's', 'c', null);
        $coachId = (int) ($filters['coach_id'] ?? 0);
        $rows = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $sessionId = (int) $row['session_id'];
            $instructorId = (int) $row['instructor_id'];
            if ($coachId > 0 && $sessionId > 0 && $instructorId !== $coachId) {
                continue;
            }
            if ($coachId > 0 && $sessionId <= 0) {
                continue;
            }
            $rows[] = [
                'session_id' => $sessionId,
                'instructor_id' => $instructorId,
                'response_count' => (int) $row['response_count'],
                'average_score' => null === $row['average_score'] ? null : (float) $row['average_score'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function courseCoaches(int $courseId): array
    {
        $ids = $this->db->fetchFirstColumn(
            'SELECT DISTINCT instructor_id FROM plugin_course_evaluation_evaluation WHERE course_id = :course AND instructor_id IS NOT NULL AND instructor_id > 0',
            ['course' => $courseId]
        );
        $texts = $this->db->fetchFirstColumn(
            "SELECT a.text_value
             FROM plugin_course_evaluation_answer a
             INNER JOIN plugin_course_evaluation_question q ON q.id = a.question_id
             INNER JOIN plugin_course_evaluation_submission s ON s.id = a.submission_id
             INNER JOIN plugin_course_evaluation_evaluation c ON c.id = s.evaluation_id
             WHERE c.course_id = :course AND q.type = 'instructor' AND a.text_value IS NOT NULL AND a.text_value <> ''",
            ['course' => $courseId]
        );
        foreach ($texts as $text) {
            foreach (array_filter(array_map('intval', explode(',', (string) $text))) as $id) {
                $ids[] = $id;
            }
        }
        $coaches = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id > 0) {
                $coaches[] = ['id' => $id, 'name' => $this->userName($id)];
            }
        }
        usort($coaches, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $coaches;
    }

    /**
     * @return list<array{id: int, user_id: int, anonymous: int, session_id: int, submitted_at: string, version: int}>
     */
    public function reportSubmissions(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('s.id', 's.user_id', 's.submitted_at', 's.questionnaire_version', 'c.anonymous', 'c.session_id', 'c.questionnaire_version AS evaluation_version')
            ->from('plugin_course_evaluation_submission', 's')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->orderBy('s.submitted_at', 'DESC');
        $this->applyReportFilters($qb, $filters, 's', 'c', null);
        $rows = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'anonymous' => (int) $row['anonymous'],
                'session_id' => (int) $row['session_id'],
                'submitted_at' => (string) $row['submitted_at'],
                'version' => (int) ($row['questionnaire_version'] ?: $row['evaluation_version']),
            ];
        }

        return $rows;
    }

    public function findCourseResponse(int $courseId, int $sessionId, int $submissionId): ?array
    {
        if ($submissionId <= 0) {
            return null;
        }
        $row = $this->db->fetchAssociative(
            'SELECT s.evaluation_id, c.session_id
             FROM plugin_course_evaluation_submission s
             INNER JOIN plugin_course_evaluation_evaluation c ON c.id = s.evaluation_id
             WHERE s.id = :submission AND c.course_id = :course',
            ['submission' => $submissionId, 'course' => $courseId]
        );
        if (!$row) {
            return null;
        }
        if ($sessionId > 0 && (int) $row['session_id'] !== $sessionId) {
            return null;
        }
        $evaluation = $this->findEvaluationById((int) $row['evaluation_id']);
        if (!$evaluation) {
            return null;
        }
        $response = $this->findResponse($evaluation, $submissionId);
        if ($response) {
            $response['anonymous'] = $evaluation->isAnonymous();
        }

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function instructorReport(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                'c.instructor_id',
                'c.course_id',
                'AVG(a.score) AS average_score',
                'COUNT(DISTINCT s.id) AS response_count'
            )
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where('a.score IS NOT NULL')
            ->andWhere("q.type NOT IN ('text', 'instructor')")
            ->groupBy('c.instructor_id', 'c.course_id')
            ->orderBy('average_score', 'DESC');

        $this->applyReportFilters($qb, $filters, 's', 'c', 'q');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function improvementComments(array $filters): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select(
                's.id',
                's.improvement_comment',
                's.submitted_at',
                's.user_id',
                'c.anonymous',
                'c.course_id',
                'c.session_id',
                'c.instructor_id',
                'c.title AS evaluation_title'
            )
            ->from('plugin_course_evaluation_submission', 's')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where("s.improvement_comment IS NOT NULL AND s.improvement_comment <> ''")
            ->orderBy('s.submitted_at', 'DESC');

        $this->applyReportFilters($qb, $filters, 's', 'c', null);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $textQb = $this->db->createQueryBuilder()
            ->select(
                's.id AS submission_id',
                'q.prompt',
                'a.text_value',
                's.submitted_at',
                's.user_id',
                'c.anonymous',
                'c.course_id',
                'c.session_id',
                'c.instructor_id',
                'c.title AS evaluation_title'
            )
            ->from('plugin_course_evaluation_answer', 'a')
            ->innerJoin('a', 'plugin_course_evaluation_question', 'q', 'q.id = a.question_id')
            ->innerJoin('a', 'plugin_course_evaluation_submission', 's', 's.id = a.submission_id')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id')
            ->where("q.type = 'text'")
            ->andWhere("a.text_value IS NOT NULL AND a.text_value <> ''")
            ->orderBy('s.submitted_at', 'DESC');
        $this->applyReportFilters($textQb, $filters, 's', 'c', 'q');

        foreach ($textQb->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = [
                'id' => $row['submission_id'],
                'improvement_comment' => $row['prompt']."\n".$row['text_value'],
                'submitted_at' => $row['submitted_at'],
                'user_id' => $row['user_id'],
                'anonymous' => $row['anonymous'],
                'course_id' => $row['course_id'],
                'session_id' => $row['session_id'],
                'instructor_id' => $row['instructor_id'],
                'evaluation_title' => $row['evaluation_title'],
            ];
        }

        return $rows;
    }

    public function responseCount(array $filters): int
    {
        $qb = $this->db->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from('plugin_course_evaluation_submission', 's')
            ->innerJoin('s', 'plugin_course_evaluation_evaluation', 'c', 'c.id = s.evaluation_id');
        $this->applyReportFilters($qb, $filters, 's', 'c', null);

        return (int) $qb->executeQuery()->fetchOne();
    }

    private function extensionClosesAt(Evaluation $evaluation, int $userId): ?DateTime
    {
        $value = $this->db->fetchOne(
            'SELECT closes_at FROM plugin_course_evaluation_extension WHERE evaluation_id = :evaluation AND user_id = :user',
            ['evaluation' => $evaluation->getId(), 'user' => $userId]
        );
        if (!$value) {
            return null;
        }

        return new DateTime((string) $value);
    }

    /**
     * @return list<int>
     */
    private function learnerIds(int $courseId, int $sessionId): array
    {
        if ($sessionId > 0 && $this->db->createSchemaManager()->tablesExist(['session_rel_course_rel_user'])) {
            $ids = $this->db->fetchFirstColumn(
                'SELECT user_id FROM session_rel_course_rel_user
                 WHERE session_id = :session AND c_id = :course AND status = 0
                 ORDER BY user_id ASC',
                ['session' => $sessionId, 'course' => $courseId]
            );
        } else {
            $ids = $this->db->fetchFirstColumn(
                'SELECT user_id FROM course_rel_user
                 WHERE c_id = :course AND status = 5
                 ORDER BY user_id ASC',
                ['course' => $courseId]
            );
        }

        return array_map('intval', $ids);
    }

    /**
     * @return list<int>
     */
    public function courseSessionIds(int $courseId): array
    {
        if ($courseId <= 0 || !$this->db->createSchemaManager()->tablesExist(['session_rel_course'])) {
            return [];
        }

        return array_map('intval', $this->db->fetchFirstColumn(
            'SELECT session_id FROM session_rel_course WHERE c_id = :course ORDER BY session_id ASC',
            ['course' => $courseId]
        ));
    }

    public function isDirectCourseStudent(int $userId, int $courseId): bool
    {
        if ($userId <= 0 || $courseId <= 0 || !$this->db->createSchemaManager()->tablesExist(['course_rel_user'])) {
            return false;
        }

        return (bool) $this->db->fetchOne(
            'SELECT user_id FROM course_rel_user WHERE c_id = :course AND user_id = :user AND status = 5',
            ['course' => $courseId, 'user' => $userId]
        );
    }

    public function isSessionLearner(int $userId, int $courseId, int $sessionId): bool
    {
        if ($userId <= 0 || $courseId <= 0 || $sessionId <= 0 || !$this->db->createSchemaManager()->tablesExist(['session_rel_course_rel_user'])) {
            return false;
        }

        return (bool) $this->db->fetchOne(
            'SELECT user_id FROM session_rel_course_rel_user
             WHERE session_id = :session AND c_id = :course AND user_id = :user AND status = 0',
            ['session' => $sessionId, 'course' => $courseId, 'user' => $userId]
        );
    }

    public function resolveInstructorId(int $courseId, int $sessionId): ?int
    {
        $conn = $this->db;

        if ($sessionId > 0 && $conn->createSchemaManager()->tablesExist(['session_rel_course_rel_user'])) {
            $coach = $conn->fetchOne(
                'SELECT user_id FROM session_rel_course_rel_user
                 WHERE session_id = :session AND c_id = :course AND status = 2
                 ORDER BY user_id ASC',
                ['session' => $sessionId, 'course' => $courseId]
            );
            if ($coach) {
                return (int) $coach;
            }

            $general = $conn->fetchOne(
                'SELECT user_id FROM session_rel_user
                 WHERE session_id = :session AND relation_type = 3
                 ORDER BY user_id ASC',
                ['session' => $sessionId]
            );
            if ($general) {
                return (int) $general;
            }
        }

        if ($conn->createSchemaManager()->tablesExist(['course_rel_user'])) {
            $teacher = $conn->fetchOne(
                'SELECT user_id FROM course_rel_user
                 WHERE c_id = :course AND status = 1
                 ORDER BY user_id ASC',
                ['course' => $courseId]
            );
            if ($teacher) {
                return (int) $teacher;
            }
        }

        return null;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function instructorChoices(int $courseId, int $sessionId): array
    {
        if ($sessionId <= 0) {
            return [];
        }
        $ids = [];
        if ($this->db->createSchemaManager()->tablesExist(['session_rel_course_rel_user'])) {
            $ids = array_merge($ids, $this->db->fetchFirstColumn(
                'SELECT user_id FROM session_rel_course_rel_user
                 WHERE session_id = :session AND c_id = :course AND status = 2',
                ['session' => $sessionId, 'course' => $courseId]
            ));
            $ids = array_merge($ids, $this->db->fetchFirstColumn(
                'SELECT user_id FROM session_rel_user WHERE session_id = :session AND relation_type = 3',
                ['session' => $sessionId]
            ));
        }
        if ($this->db->createSchemaManager()->tablesExist(['course_rel_user'])) {
            $ids = array_merge($ids, $this->db->fetchFirstColumn(
                'SELECT user_id FROM course_rel_user WHERE c_id = :course AND status = 1',
                ['course' => $courseId]
            ));
        }
        $choices = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id > 0) {
                $choices[] = ['id' => $id, 'name' => $this->userName($id)];
            }
        }

        return $choices;
    }

    public function userName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        if (function_exists('api_get_user_info')) {
            $info = api_get_user_info($userId);
            if (is_array($info)) {
                $name = trim((string) ($info['complete_name'] ?? ''));
                if ('' !== $name) {
                    return $name;
                }
            }
        }

        return 'User #'.$userId;
    }

    /**
     * @return array{courses: list<array{id: int, label: string}>, sessions: list<array{id: int, label: string}>, instructors: list<array{id: int, label: string}>}
     */
    public function reportChoices(): array
    {
        $courses = [];
        foreach ($this->db->fetchFirstColumn('SELECT DISTINCT course_id FROM plugin_course_evaluation_evaluation WHERE course_id > 0') as $id) {
            $courses[] = ['id' => (int) $id, 'label' => $this->courseTitle((int) $id)];
        }
        $sessions = [];
        foreach ($this->db->fetchFirstColumn('SELECT DISTINCT session_id FROM plugin_course_evaluation_evaluation') as $id) {
            $sessions[] = ['id' => (int) $id, 'label' => $this->sessionTitle((int) $id)];
        }
        $instructors = [];
        foreach ($this->db->fetchFirstColumn('SELECT DISTINCT instructor_id FROM plugin_course_evaluation_evaluation WHERE instructor_id IS NOT NULL AND instructor_id > 0') as $id) {
            $instructors[] = ['id' => (int) $id, 'label' => $this->userName((int) $id)];
        }
        $byLabel = static function (array $left, array $right): int {
            return strcasecmp($left['label'], $right['label']);
        };
        usort($courses, $byLabel);
        usort($sessions, $byLabel);
        usort($instructors, $byLabel);

        return [
            'courses' => $courses,
            'sessions' => $sessions,
            'instructors' => $instructors,
        ];
    }

    public function courseTitle(int $courseId): string
    {
        if ($courseId <= 0) {
            return '';
        }
        if (function_exists('api_get_course_info_by_id')) {
            $info = api_get_course_info_by_id($courseId);
            if (is_array($info) && !empty($info['title'])) {
                return (string) $info['title'];
            }
        }

        return 'Course #'.$courseId;
    }

    public function sessionTitle(int $sessionId): string
    {
        if ($sessionId <= 0) {
            return 'Self-paced';
        }
        if (function_exists('api_get_session_info')) {
            $info = api_get_session_info($sessionId);
            if (is_array($info)) {
                $title = (string) ($info['title'] ?? $info['name'] ?? '');
                if ('' !== $title) {
                    return $title;
                }
            }
        }

        return 'Session #'.$sessionId;
    }

    public function isStudentInContext(int $userId, int $courseId, int $sessionId): bool
    {
        if ($userId <= 0 || $this->canManageCourse()) {
            return false;
        }

        return function_exists('api_is_allowed_in_course') && api_is_allowed_in_course();
    }

    public function canManageCourse(): bool
    {
        if (function_exists('api_is_student_view_active') && api_is_student_view_active()) {
            return false;
        }
        if (function_exists('api_is_platform_admin') && api_is_platform_admin()) {
            return true;
        }

        if (function_exists('api_is_allowed_to_edit') && api_is_allowed_to_edit()) {
            return true;
        }

        return $this->isCurrentSessionCoach();
    }

    private function isCurrentSessionCoach(): bool
    {
        if (!function_exists('api_get_user_id') || !function_exists('api_get_session_id') || !function_exists('api_get_course_int_id')) {
            return false;
        }
        $userId = (int) api_get_user_id();
        $sessionId = (int) api_get_session_id();
        $courseId = (int) api_get_course_int_id();
        if ($userId <= 0 || $sessionId <= 0 || $courseId <= 0) {
            return false;
        }
        if ($this->db->createSchemaManager()->tablesExist(['session_rel_course_rel_user'])) {
            $coach = $this->db->fetchOne(
                'SELECT user_id FROM session_rel_course_rel_user
                 WHERE session_id = :session AND c_id = :course AND user_id = :user AND status = 2',
                ['session' => $sessionId, 'course' => $courseId, 'user' => $userId]
            );
            if ($coach) {
                return true;
            }
        }
        if ($this->db->createSchemaManager()->tablesExist(['session_rel_user'])) {
            $general = $this->db->fetchOne(
                'SELECT user_id FROM session_rel_user
                 WHERE session_id = :session AND user_id = :user AND relation_type = 3',
                ['session' => $sessionId, 'user' => $userId]
            );
            if ($general) {
                return true;
            }
        }

        return false;
    }

    private function nextPosition(Template $template): int
    {
        $max = $this->db->fetchOne(
            'SELECT MAX(position) FROM plugin_course_evaluation_question WHERE template_id = :template',
            ['template' => $template->getId()]
        );

        return ((int) $max) + 1;
    }

    private function updateTemplate(Template $template): void
    {
        $this->db->update('plugin_course_evaluation_template', [
            'title' => $template->getTitle(),
            'description' => $template->getDescription(),
            'scope' => $template->getScope(),
            'course_id' => $template->getCourseId(),
            'source_template_id' => $template->getSourceTemplateId(),
            'is_active' => $template->isActive() ? 1 : 0,
            'created_by' => $template->getCreatedBy(),
            'updated_at' => $template->getUpdatedAt()->format('Y-m-d H:i:s'),
        ], ['id' => $template->getId()]);
    }

    private function insertQuestion(Question $question): void
    {
        $this->db->insert('plugin_course_evaluation_question', $this->questionValues($question));
        $question->setId((int) $this->db->lastInsertId());
    }

    private function updateQuestion(Question $question): void
    {
        $this->db->update(
            'plugin_course_evaluation_question',
            $this->questionValues($question),
            ['id' => $question->getId()]
        );
    }

    private function questionValues(Question $question): array
    {
        return [
            'template_id' => $question->getTemplate()->getId(),
            'category' => $question->getCategory(),
            'type' => $question->getType(),
            'prompt' => $question->getPrompt(),
            'help_text' => $question->getHelpText(),
            'required' => $question->isRequired() ? 1 : 0,
            'position' => $question->getPosition(),
            'scale_min' => $question->getScaleMin(),
            'scale_max' => $question->getScaleMax(),
            'scale_low_label' => $question->getScaleLowLabel(),
            'scale_high_label' => $question->getScaleHighLabel(),
        ];
    }

    private function saveEvaluation(Evaluation $evaluation): void
    {
        $values = [
            'template_id' => $evaluation->getTemplate()->getId(),
            'course_id' => $evaluation->getCourseId(),
            'session_id' => $evaluation->getSessionId(),
            'instructor_id' => $evaluation->getInstructorId(),
            'title' => $evaluation->getTitle(),
            'questionnaire_version' => $evaluation->getQuestionnaireVersion(),
            'status' => $evaluation->getStatus(),
            'anonymous' => $evaluation->isAnonymous() ? 1 : 0,
            'opens_at' => $evaluation->getOpensAt()?->format('Y-m-d H:i:s'),
            'closes_at' => $evaluation->getClosesAt()?->format('Y-m-d H:i:s'),
            'require_certificate' => $evaluation->requiresCertificate() ? 1 : 0,
            'gradebook_evaluation_id' => $evaluation->getGradebookEvaluationId(),
        ];
        if ($evaluation->getId()) {
            $this->db->update('plugin_course_evaluation_evaluation', $values, ['id' => $evaluation->getId()]);

            return;
        }
        $values['created_at'] = $evaluation->getCreatedAt()->format('Y-m-d H:i:s');
        $this->db->insert('plugin_course_evaluation_evaluation', $values);
        $evaluation->setId((int) $this->db->lastInsertId());
    }

    private function templateFromRow(array $row): Template
    {
        return (new Template())
            ->setId((int) $row['id'])
            ->setTitle((string) $row['title'])
            ->setDescription($row['description'])
            ->setScope((string) $row['scope'])
            ->setCourseId(null === $row['course_id'] ? null : (int) $row['course_id'])
            ->setSourceTemplateId(null === $row['source_template_id'] ? null : (int) $row['source_template_id'])
            ->setIsActive((bool) $row['is_active'])
            ->setCreatedBy(null === $row['created_by'] ? null : (int) $row['created_by']);
    }

    private function questionFromRow(array $row, Template $template): Question
    {
        return (new Question())
            ->setId((int) $row['id'])
            ->setTemplate($template)
            ->setCategory((string) $row['category'])
            ->setType((string) $row['type'])
            ->setPrompt((string) $row['prompt'])
            ->setHelpText($row['help_text'])
            ->setRequired((bool) $row['required'])
            ->setPosition((int) $row['position'])
            ->setScaleMin((int) $row['scale_min'])
            ->setScaleMax((int) $row['scale_max'])
            ->setScaleLowLabel($row['scale_low_label'])
            ->setScaleHighLabel($row['scale_high_label']);
    }

    private function evaluationFromRow(array $row): Evaluation
    {
        $template = $this->findTemplate((int) $row['template_id']);
        if (!$template instanceof Template) {
            throw new RuntimeException('The evaluation questionnaire for this evaluation is missing.');
        }

        return (new Evaluation())
            ->setId((int) $row['id'])
            ->setTemplate($template)
            ->setCourseId((int) $row['course_id'])
            ->setSessionId((int) $row['session_id'])
            ->setInstructorId(null === $row['instructor_id'] ? null : (int) $row['instructor_id'])
            ->setTitle((string) $row['title'])
            ->setQuestionnaireVersion((int) ($row['questionnaire_version'] ?? 1))
            ->setStatus((string) $row['status'])
            ->setAnonymous((bool) $row['anonymous'])
            ->setOpensAt(empty($row['opens_at']) ? null : new DateTime((string) $row['opens_at']))
            ->setClosesAt(empty($row['closes_at']) ? null : new DateTime((string) $row['closes_at']))
            ->setRequireCertificate(!empty($row['require_certificate']))
            ->setGradebookEvaluationId(empty($row['gradebook_evaluation_id']) ? null : (int) $row['gradebook_evaluation_id']);
    }

    public function bumpQuestionnaireVersion(Template $template): void
    {
        $evaluations = $this->db->fetchAllAssociative(
            'SELECT id FROM plugin_course_evaluation_evaluation WHERE template_id = :template',
            ['template' => $template->getId()]
        );
        foreach ($evaluations as $row) {
            $evaluation = $this->findEvaluationById((int) $row['id']);
            if ($evaluation instanceof Evaluation) {
                $this->archiveCurrentVersion($evaluation);
            }
        }
        $this->db->executeStatement(
            'UPDATE plugin_course_evaluation_evaluation
             SET questionnaire_version = questionnaire_version + 1
             WHERE template_id = :template',
            ['template' => $template->getId()]
        );
    }

    /**
     * @return list<array{version: int, template_id: int}>
     */
    public function questionnaireSnapshots(Evaluation $evaluation): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT version, template_id
             FROM plugin_course_evaluation_snapshot
             WHERE evaluation_id = :evaluation
             ORDER BY version ASC',
            ['evaluation' => $evaluation->getId()]
        );
        $snapshots = [];
        foreach ($rows as $row) {
            $snapshots[] = [
                'version' => (int) $row['version'],
                'template_id' => (int) $row['template_id'],
            ];
        }

        return $snapshots;
    }

    public function beginQuestionBatch(Template $template): void
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id FROM plugin_course_evaluation_evaluation WHERE template_id = :template',
            ['template' => $template->getId()]
        );
        foreach ($rows as $row) {
            $evaluation = $this->findEvaluationById((int) $row['id']);
            if (!$evaluation instanceof Evaluation || $evaluation->getSessionId() > 0) {
                if ($evaluation instanceof Evaluation) {
                    $this->archiveCurrentVersion($evaluation);
                }
                continue;
            }
            if ($this->templateHasSubmissions($evaluation->getTemplate())) {
                $this->detachAnsweredTemplate($evaluation);
            } else {
                $this->archiveCurrentVersion($evaluation);
            }
        }
    }

    public function questionOnTemplate(Template $template, Question $question): ?Question
    {
        if ($question->getTemplate()->getId() === $template->getId()) {
            return $question;
        }
        foreach ($this->questionsFor($template) as $candidate) {
            if ($candidate->getPosition() === $question->getPosition()) {
                return $candidate;
            }
        }

        return null;
    }

    public function hasUnpublishedQuestionChanges(Evaluation $evaluation): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT id FROM plugin_course_evaluation_snapshot WHERE evaluation_id = :evaluation AND version = :version',
            ['evaluation' => $evaluation->getId(), 'version' => $evaluation->getQuestionnaireVersion()]
        );
    }

    public function publishQuestionBatch(Evaluation $evaluation): void
    {
        if (!$this->hasUnpublishedQuestionChanges($evaluation)) {
            return;
        }
        $evaluation->setQuestionnaireVersion($evaluation->getQuestionnaireVersion() + 1);
        $this->saveEvaluation($evaluation);
    }

    public function discardQuestionBatch(Evaluation $evaluation): void
    {
        if (!$this->hasUnpublishedQuestionChanges($evaluation)) {
            return;
        }
        $templateId = (int) $this->db->fetchOne(
            'SELECT template_id FROM plugin_course_evaluation_snapshot WHERE evaluation_id = :evaluation AND version = :version',
            ['evaluation' => $evaluation->getId(), 'version' => $evaluation->getQuestionnaireVersion()]
        );
        $source = $this->findTemplate($templateId);
        if (!$source instanceof Template) {
            return;
        }
        $copy = $this->copyTemplateToCourse($source, $evaluation->getCourseId(), (int) api_get_user_id());
        $evaluation->setTemplate($copy);
        $this->saveEvaluation($evaluation);
        $this->db->delete('plugin_course_evaluation_snapshot', [
            'evaluation_id' => $evaluation->getId(),
            'version' => $evaluation->getQuestionnaireVersion(),
        ]);
    }

    public function restoreQuestionnaireVersion(Evaluation $evaluation, int $version, int $userId): Template
    {
        $this->archiveCurrentVersion($evaluation);
        $templateId = 0;
        foreach ($this->questionnaireSnapshots($evaluation) as $snapshot) {
            if ($snapshot['version'] === $version) {
                $templateId = $snapshot['template_id'];
                break;
            }
        }
        $source = $this->findTemplate($templateId);
        if (!$source instanceof Template || !$this->questionsFor($source)) {
            throw new RuntimeException('That version of the questionnaire is not available.');
        }

        return $this->copyTemplateToCourse($source, $evaluation->getCourseId(), $userId);
    }

    private function detachAnsweredTemplate(Evaluation $evaluation): void
    {
        if (!$evaluation->getId() || $this->hasUnpublishedQuestionChanges($evaluation)) {
            return;
        }
        $frozen = $evaluation->getTemplate();
        $copy = $this->copyTemplateToCourse($frozen, $evaluation->getCourseId(), (int) api_get_user_id());
        $this->db->update('plugin_course_evaluation_template', [
            'scope' => Template::SCOPE_VERSION,
            'title' => $evaluation->getTitle().' · Version '.$evaluation->getQuestionnaireVersion(),
        ], ['id' => $frozen->getId()]);
        $this->db->insert('plugin_course_evaluation_snapshot', [
            'evaluation_id' => $evaluation->getId(),
            'version' => $evaluation->getQuestionnaireVersion(),
            'template_id' => $frozen->getId(),
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
        $evaluation->setTemplate($copy);
        $this->saveEvaluation($evaluation);
    }

    private function archiveCurrentVersion(Evaluation $evaluation): void
    {
        if (!$evaluation->getId()) {
            return;
        }
        $version = $evaluation->getQuestionnaireVersion();
        $exists = $this->db->fetchOne(
            'SELECT id FROM plugin_course_evaluation_snapshot WHERE evaluation_id = :evaluation AND version = :version',
            ['evaluation' => $evaluation->getId(), 'version' => $version]
        );
        if ($exists) {
            return;
        }
        $copy = $this->copyTemplateToCourse($evaluation->getTemplate(), $evaluation->getCourseId(), (int) api_get_user_id());
        $this->db->update('plugin_course_evaluation_template', [
            'scope' => Template::SCOPE_VERSION,
            'title' => $evaluation->getTitle().' · Version '.$version,
        ], ['id' => $copy->getId()]);
        $this->db->insert('plugin_course_evaluation_snapshot', [
            'evaluation_id' => $evaluation->getId(),
            'version' => $version,
            'template_id' => $copy->getId(),
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function questionnaireVersion(int $templateId): string
    {
        $template = $this->findTemplate($templateId);
        if (!$template instanceof Template) {
            return '';
        }

        return $template->getTitle().' · '.$template->getCreatedAt()->format('j M Y');
    }

    public function findResponse(Evaluation $evaluation, int $submissionId): ?array
    {
        foreach ($this->responsesFor($evaluation) as $response) {
            if ((int) $response['id'] === $submissionId) {
                return $response;
            }
        }

        return null;
    }

    private function attachCertificateRequirement(Evaluation $evaluation): void
    {
        if (!$this->db->createSchemaManager()->tablesExist(['gradebook_category', 'gradebook_evaluation', 'gradebook_result'])) {
            throw new RuntimeException($this->certificateAssessmentMessage());
        }
        $categoryId = $this->gradebookCategoryId($evaluation->getCourseId(), $evaluation->getSessionId());
        if (null === $categoryId) {
            throw new RuntimeException($this->certificateAssessmentMessage());
        }

        $evaluationId = $evaluation->getGradebookEvaluationId();
        $stillThere = $evaluationId
            ? $this->db->fetchOne('SELECT id FROM gradebook_evaluation WHERE id = :id', ['id' => $evaluationId])
            : false;
        $values = [
            'title' => $evaluation->getTitle(),
            'description' => 'Completed when the learner submits the course evaluation.',
            'c_id' => $evaluation->getCourseId(),
            'category_id' => $categoryId,
            'weight' => 0,
            'max' => 1,
            'visible' => 1,
            'type' => 'evaluation',
            'locked' => 0,
            'min_score' => 1,
        ];
        if ($stillThere) {
            $this->db->update('gradebook_evaluation', $values, ['id' => (int) $evaluationId]);
        } else {
            $values['created_at'] = (new DateTime())->format('Y-m-d H:i:s');
            $this->db->insert('gradebook_evaluation', $values);
            $evaluationId = (int) $this->db->lastInsertId();
            $evaluation->setGradebookEvaluationId($evaluationId);
            $this->saveEvaluation($evaluation);
        }

        $userIds = $this->db->fetchFirstColumn(
            'SELECT user_id FROM plugin_course_evaluation_submission WHERE evaluation_id = :evaluation',
            ['evaluation' => $evaluation->getId()]
        );
        foreach ($userIds as $userId) {
            $this->ensureCertificateResult($evaluation, (int) $userId);
        }
    }

    private function clearCertificateRequirement(Evaluation $evaluation): void
    {
        $evaluationId = $evaluation->getGradebookEvaluationId();
        if (!$evaluationId || !$this->db->createSchemaManager()->tablesExist(['gradebook_evaluation'])) {
            return;
        }
        $this->db->update('gradebook_evaluation', [
            'min_score' => null,
            'visible' => 0,
        ], ['id' => $evaluationId]);
    }

    private function certificateAssessmentMessage(): string
    {
        if (class_exists(\CourseEvaluationPlugin::class)) {
            return \CourseEvaluationPlugin::create()->get_lang('CertificateNeedsAssessment');
        }

        return 'Open Assessments for this course and save the gradebook once, then turn this option on again.';
    }

    public function hasGradebookAssessment(int $courseId, int $sessionId): bool
    {
        return null !== $this->gradebookCategoryId($courseId, $sessionId);
    }

    private function gradebookCategoryId(int $courseId, int $sessionId): ?int
    {
        $candidates = [];
        if ($sessionId > 0) {
            $candidates[] = [$sessionId, true];
        }
        $candidates[] = [0, true];
        if ($sessionId > 0) {
            $candidates[] = [$sessionId, false];
        }
        $candidates[] = [0, false];
        foreach ($candidates as [$candidateSession, $mustGenerate]) {
            $id = $this->findGradebookCategoryId($courseId, $candidateSession, $mustGenerate);
            if (null !== $id) {
                return $id;
            }
        }

        return null;
    }

    private function findGradebookCategoryId(int $courseId, int $sessionId, bool $mustGenerate): ?int
    {
        $sessionSql = $sessionId > 0
            ? 'session_id = :session'
            : '(session_id IS NULL OR session_id = 0)';
        $params = ['course' => $courseId];
        if ($sessionId > 0) {
            $params['session'] = $sessionId;
        }
        $certificateSql = $mustGenerate ? ' AND generate_certificates = 1' : '';
        $id = $this->db->fetchOne(
            'SELECT id FROM gradebook_category
             WHERE c_id = :course AND '.$sessionSql.$certificateSql.'
             ORDER BY (parent_id IS NULL) DESC, id ASC
             LIMIT 1',
            $params
        );

        return $id ? (int) $id : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return '' === $text ? null : $text;
    }

    private function applyReportFilters($qb, array $filters, string $submissionAlias, string $evaluationAlias, ?string $questionAlias): void
    {
        if (!empty($filters['from'])) {
            $qb->andWhere($submissionAlias.'.submitted_at >= :from')
                ->setParameter('from', $filters['from'].' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $qb->andWhere($submissionAlias.'.submitted_at <= :to')
                ->setParameter('to', $filters['to'].' 23:59:59');
        }
        if (!empty($filters['course_id'])) {
            $qb->andWhere($evaluationAlias.'.course_id = :courseId')
                ->setParameter('courseId', (int) $filters['course_id']);
        }
        if (!empty($filters['filter_session'])) {
            $qb->andWhere($evaluationAlias.'.session_id = :sessionId')
                ->setParameter('sessionId', (int) ($filters['session_id'] ?? 0));
        } elseif ('self_paced' === ($filters['scope'] ?? '')) {
            $qb->andWhere('('.$evaluationAlias.'.session_id IS NULL OR '.$evaluationAlias.'.session_id = 0)');
        } elseif ('session' === ($filters['scope'] ?? '')) {
            $qb->andWhere($evaluationAlias.'.session_id > 0');
        }
        if (!empty($filters['instructor_id'])) {
            $qb->andWhere($evaluationAlias.'.instructor_id = :instructorId')
                ->setParameter('instructorId', (int) $filters['instructor_id']);
        }
        $coachId = (int) ($filters['coach_id'] ?? 0);
        if ($coachId > 0) {
            $qb->andWhere(
                '(('.$evaluationAlias.'.session_id > 0 AND '.$evaluationAlias.'.instructor_id = :coachId)
                  OR (('.$evaluationAlias.'.session_id IS NULL OR '.$evaluationAlias.'.session_id = 0)
                      AND EXISTS (
                          SELECT 1 FROM plugin_course_evaluation_answer coach_answer
                          INNER JOIN plugin_course_evaluation_question coach_question
                              ON coach_question.id = coach_answer.question_id
                          WHERE coach_answer.submission_id = '.$submissionAlias.'.id
                            AND coach_question.type = :coachQuestionType
                            AND CONCAT(\',\', coach_answer.text_value, \',\') LIKE :coachLike
                      )))'
            )
                ->setParameter('coachId', $coachId)
                ->setParameter('coachQuestionType', 'instructor')
                ->setParameter('coachLike', '%,'.$coachId.',%');
        }
        if ($questionAlias && !empty($filters['category'])) {
            $qb->andWhere($questionAlias.'.category = :category')
                ->setParameter('category', $filters['category']);
        }
    }

    public function deleteForCourse(int $courseId): void
    {
        if ($courseId <= 0 || !$this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_evaluation'])) {
            return;
        }
        $evaluationIds = array_map('intval', $this->db->fetchFirstColumn(
            'SELECT id FROM plugin_course_evaluation_evaluation WHERE course_id = :course',
            ['course' => $courseId]
        ));
        $this->deleteEvaluations($evaluationIds);
        if (!$this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_template'])) {
            return;
        }
        $templateIds = array_map('intval', $this->db->fetchFirstColumn(
            'SELECT id FROM plugin_course_evaluation_template WHERE course_id = :course AND scope <> :global',
            ['course' => $courseId, 'global' => Template::SCOPE_GLOBAL]
        ));
        $this->deleteTemplates($templateIds);
    }

    public function deleteForSession(int $sessionId): void
    {
        if ($sessionId <= 0 || !$this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_evaluation'])) {
            return;
        }
        $evaluationIds = array_map('intval', $this->db->fetchFirstColumn(
            'SELECT id FROM plugin_course_evaluation_evaluation WHERE session_id = :session',
            ['session' => $sessionId]
        ));
        $this->deleteEvaluations($evaluationIds);
    }

    /**
     * @param list<int> $evaluationIds
     */
    private function deleteEvaluations(array $evaluationIds): void
    {
        $evaluationIds = array_values(array_filter(array_map('intval', $evaluationIds)));
        if (!$evaluationIds) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($evaluationIds), '?'));
        $gradebookIds = array_map('intval', $this->db->fetchFirstColumn(
            'SELECT gradebook_evaluation_id FROM plugin_course_evaluation_evaluation
             WHERE id IN ('.$placeholders.') AND gradebook_evaluation_id IS NOT NULL AND gradebook_evaluation_id > 0',
            $evaluationIds
        ));
        $this->deleteGradebookItems($gradebookIds);
        $templateIds = array_map('intval', $this->db->fetchFirstColumn(
            'SELECT template_id FROM plugin_course_evaluation_evaluation WHERE id IN ('.$placeholders.')',
            $evaluationIds
        ));
        if ($this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_snapshot'])) {
            $templateIds = array_merge($templateIds, array_map('intval', $this->db->fetchFirstColumn(
                'SELECT template_id FROM plugin_course_evaluation_snapshot WHERE evaluation_id IN ('.$placeholders.')',
                $evaluationIds
            )));
        }
        $this->deleteByEvaluationId('plugin_course_evaluation_answer', $evaluationIds, true);
        $this->deleteByEvaluationId('plugin_course_evaluation_submission', $evaluationIds, false);
        $this->deleteByEvaluationId('plugin_course_evaluation_extension', $evaluationIds, false);
        $this->deleteByEvaluationId('plugin_course_evaluation_start', $evaluationIds, false);
        $this->deleteByEvaluationId('plugin_course_evaluation_snapshot', $evaluationIds, false);
        $this->db->executeStatement(
            'DELETE FROM plugin_course_evaluation_evaluation WHERE id IN ('.$placeholders.')',
            $evaluationIds
        );
        $this->deleteTemplates($templateIds);
    }

    /**
     * @param list<int> $evaluationIds
     */
    private function deleteByEvaluationId(string $table, array $evaluationIds, bool $throughSubmission): void
    {
        if (!$this->db->createSchemaManager()->tablesExist([$table])) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($evaluationIds), '?'));
        if ($throughSubmission) {
            if (!$this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_submission'])) {
                return;
            }
            $this->db->executeStatement(
                'DELETE a FROM '.$table.' a
                 INNER JOIN plugin_course_evaluation_submission s ON s.id = a.submission_id
                 WHERE s.evaluation_id IN ('.$placeholders.')',
                $evaluationIds
            );

            return;
        }
        $this->db->executeStatement(
            'DELETE FROM '.$table.' WHERE evaluation_id IN ('.$placeholders.')',
            $evaluationIds
        );
    }

    /**
     * @param list<int> $templateIds
     */
    private function deleteTemplates(array $templateIds): void
    {
        $templateIds = array_values(array_unique(array_filter(array_map('intval', $templateIds))));
        if (!$templateIds || !$this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_template'])) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
        if ($this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_evaluation'])) {
            $stillUsed = array_map('intval', $this->db->fetchFirstColumn(
                'SELECT template_id FROM plugin_course_evaluation_evaluation WHERE template_id IN ('.$placeholders.')',
                $templateIds
            ));
            $templateIds = array_values(array_diff($templateIds, $stillUsed));
        }
        if (!$templateIds) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
        if ($this->db->createSchemaManager()->tablesExist(['plugin_course_evaluation_question'])) {
            $this->db->executeStatement(
                'DELETE q FROM plugin_course_evaluation_question q
                 INNER JOIN plugin_course_evaluation_template t ON t.id = q.template_id
                 WHERE q.template_id IN ('.$placeholders.') AND t.scope <> ?',
                array_merge($templateIds, [Template::SCOPE_GLOBAL])
            );
        }
        $this->db->executeStatement(
            'DELETE FROM plugin_course_evaluation_template WHERE id IN ('.$placeholders.') AND scope <> ?',
            array_merge($templateIds, [Template::SCOPE_GLOBAL])
        );
    }

    /**
     * @param list<int> $gradebookIds
     */
    private function deleteGradebookItems(array $gradebookIds): void
    {
        $gradebookIds = array_values(array_unique(array_filter(array_map('intval', $gradebookIds))));
        if (!$gradebookIds) {
            return;
        }
        $schema = $this->db->createSchemaManager();
        if (!$schema->tablesExist(['gradebook_evaluation'])) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($gradebookIds), '?'));
        if ($schema->tablesExist(['gradebook_result', 'gradebook_result_attempt'])) {
            $this->db->executeStatement(
                'DELETE a FROM gradebook_result_attempt a
                 INNER JOIN gradebook_result r ON r.id = a.result_id
                 WHERE r.evaluation_id IN ('.$placeholders.')',
                $gradebookIds
            );
        }
        if ($schema->tablesExist(['gradebook_result_log'])) {
            $this->db->executeStatement(
                'DELETE FROM gradebook_result_log WHERE evaluation_id IN ('.$placeholders.')',
                $gradebookIds
            );
        }
        if ($schema->tablesExist(['gradebook_result'])) {
            $this->db->executeStatement(
                'DELETE FROM gradebook_result WHERE evaluation_id IN ('.$placeholders.')',
                $gradebookIds
            );
        }
        $this->db->executeStatement(
            'DELETE FROM gradebook_evaluation WHERE id IN ('.$placeholders.')',
            $gradebookIds
        );
    }
}
