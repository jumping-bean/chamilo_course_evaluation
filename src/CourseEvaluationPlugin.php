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

class CourseEvaluationPlugin extends Plugin
{
    public const VERSION = '1.1.0';

    public function __construct()
    {
        parent::__construct(
            self::VERSION,
            'Course evaluation plugin',
            [
                'anonymous_by_default' => 'boolean',
                'allow_delete_completed' => 'boolean',
            ]
        );

        $this->isAdminPlugin = true;
        $this->isCoursePlugin = true;
        $this->addCourseTool = true;
    }

    public static function create(): static
    {
        static $instance = null;

        return $instance ??= new static();
    }

    public function get_info()
    {
        $info = parent::get_info();
        $info['title'] = $this->get_lang('plugin_title');
        $info['comment'] = $this->get_lang('plugin_comment');

        return $info;
    }

    /**
     * The course-home tool list calls isEnabled(true). The base method takes
     * no argument, and that error makes Chamilo skip this card.
     */
    public function isEnabled(bool $checkAccessUrl = true): bool
    {
        return parent::isEnabled();
    }

    /**
     * Course-home card. chart-box is ToolIcon::TRACKING, the reporting chart.
     */
    public function getCourseToolIcon(): string
    {
        return 'mdi-chart-box';
    }

    public function install(): void
    {
        $this->update();
        $this->addToolToEveryCourse();
        $this->seedDefaultTemplate((int) api_get_user_id());
    }

    /**
     * Apply any schema steps newer than the recorded version.
     * Safe to run on every request. A release that changes a table adds a
     * method here and a newer version key; the CREATE TABLE text alone does not.
     */
    public function update(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $this->ensureSchemaTable();
        $current = $this->appliedSchemaVersion();
        foreach ($this->schemaSteps() as $version => $method) {
            if (version_compare($current, $version, '>=')) {
                continue;
            }
            $this->{$method}();
            $this->setSchemaVersion($version);
            $current = $version;
        }
    }

    /**
     * @return array<string, string>
     */
    private function schemaSteps(): array
    {
        return [
            '1.0.0' => 'migrate100',
            '1.1.0' => 'migrate110',
        ];
    }

    private function migrate100(): void
    {
        $this->renameCampaignTable();
        $connection = Database::getManager()->getConnection();
        $schema = $connection->createSchemaManager();
        $tables = [
            'plugin_course_evaluation_template' => $this->templateTableSql(),
            'plugin_course_evaluation_question' => $this->questionTableSql(),
            'plugin_course_evaluation_evaluation' => $this->evaluationTableSql(),
            'plugin_course_evaluation_submission' => $this->submissionTableSql(),
            'plugin_course_evaluation_answer' => $this->answerTableSql(),
            'plugin_course_evaluation_extension' => $this->extensionTableSql(),
            'plugin_course_evaluation_start' => $this->startTableSql(),
            'plugin_course_evaluation_snapshot' => $this->snapshotTableSql(),
        ];
        foreach ($tables as $name => $sql) {
            if (!$schema->tablesExist([$name])) {
                $connection->executeStatement($sql);
                $schema = $connection->createSchemaManager();
            }
        }

        if ($schema->tablesExist(['plugin_course_evaluation_evaluation'])) {
            $columns = $schema->listTableColumns('plugin_course_evaluation_evaluation');
            if (!isset($columns['require_certificate'])) {
                $connection->executeStatement(
                    'ALTER TABLE plugin_course_evaluation_evaluation ADD require_certificate TINYINT(1) NOT NULL DEFAULT 0'
                );
            }
            if (!isset($columns['gradebook_evaluation_id'])) {
                $connection->executeStatement(
                    'ALTER TABLE plugin_course_evaluation_evaluation ADD gradebook_evaluation_id INT DEFAULT NULL'
                );
            }
            if (!isset($columns['questionnaire_version'])) {
                $connection->executeStatement(
                    'ALTER TABLE plugin_course_evaluation_evaluation ADD questionnaire_version INT NOT NULL DEFAULT 1'
                );
            }
        }

        if ($schema->tablesExist(['plugin_course_evaluation_submission'])) {
            $columns = $schema->listTableColumns('plugin_course_evaluation_submission');
            if (!isset($columns['questionnaire_version'])) {
                $connection->executeStatement(
                    'ALTER TABLE plugin_course_evaluation_submission ADD questionnaire_version INT DEFAULT NULL'
                );
                $connection->executeStatement(
                    'UPDATE plugin_course_evaluation_submission s
                     INNER JOIN plugin_course_evaluation_evaluation c ON c.id = s.evaluation_id
                     SET s.questionnaire_version = c.questionnaire_version
                     WHERE s.questionnaire_version IS NULL'
                );
            }
            if (!isset($columns['template_id'])) {
                $connection->executeStatement(
                    'ALTER TABLE plugin_course_evaluation_submission ADD template_id INT DEFAULT NULL'
                );
                $connection->executeStatement(
                    'UPDATE plugin_course_evaluation_submission s
                     INNER JOIN plugin_course_evaluation_answer a ON a.submission_id = s.id
                     INNER JOIN plugin_course_evaluation_question q ON q.id = a.question_id
                     SET s.template_id = q.template_id
                     WHERE s.template_id IS NULL'
                );
                $connection->executeStatement(
                    'UPDATE plugin_course_evaluation_submission s
                     INNER JOIN plugin_course_evaluation_evaluation c ON c.id = s.evaluation_id
                     SET s.template_id = c.template_id
                     WHERE s.template_id IS NULL'
                );
            }
        }
    }

    /**
     * 1.0.0 created plugin_course_evaluation_campaign. This release names that table,
     * and the campaign_id columns that point at it, evaluation.
     */
    private function migrate110(): void
    {
        $this->renameCampaignTable();
    }

    private function renameCampaignTable(): void
    {
        $connection = Database::getManager()->getConnection();
        $schema = $connection->createSchemaManager();
        $old = 'plugin_course_evaluation_campaign';
        $new = 'plugin_course_evaluation_evaluation';
        $hasOld = $schema->tablesExist([$old]);
        $hasNew = $schema->tablesExist([$new]);
        if ($hasOld && !$hasNew) {
            $connection->executeStatement('RENAME TABLE '.$old.' TO '.$new);
            $schema = $connection->createSchemaManager();
        } elseif ($hasOld && $hasNew) {
            $oldRows = (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$old);
            $newRows = (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$new);
            if ($oldRows > 0 && 0 === $newRows) {
                $connection->executeStatement('DROP TABLE '.$new);
                $connection->executeStatement('RENAME TABLE '.$old.' TO '.$new);
                $schema = $connection->createSchemaManager();
            }
        }
        if (!$schema->tablesExist([$new])) {
            return;
        }
        foreach ([
            'plugin_course_evaluation_submission',
            'plugin_course_evaluation_extension',
            'plugin_course_evaluation_start',
            'plugin_course_evaluation_snapshot',
        ] as $table) {
            if (!$schema->tablesExist([$table])) {
                continue;
            }
            $columns = $schema->listTableColumns($table);
            if (isset($columns['campaign_id']) && !isset($columns['evaluation_id'])) {
                $connection->executeStatement('ALTER TABLE '.$table.' CHANGE campaign_id evaluation_id INT NOT NULL');
                $schema = $connection->createSchemaManager();
            }
        }
    }

    private function ensureSchemaTable(): void
    {
        $connection = Database::getManager()->getConnection();
        if ($connection->createSchemaManager()->tablesExist(['plugin_course_evaluation_schema'])) {
            return;
        }
        $connection->executeStatement(
            'CREATE TABLE plugin_course_evaluation_schema (
                id INT NOT NULL,
                version VARCHAR(16) NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    private function appliedSchemaVersion(): string
    {
        $connection = Database::getManager()->getConnection();
        if (!$connection->createSchemaManager()->tablesExist(['plugin_course_evaluation_schema'])) {
            return '0';
        }
        $version = $connection->fetchOne('SELECT version FROM plugin_course_evaluation_schema WHERE id = 1');

        return is_string($version) && '' !== $version ? $version : '0';
    }

    private function setSchemaVersion(string $version): void
    {
        $connection = Database::getManager()->getConnection();
        $existing = $connection->fetchOne('SELECT id FROM plugin_course_evaluation_schema WHERE id = 1');
        if ($existing) {
            $connection->update('plugin_course_evaluation_schema', ['version' => $version], ['id' => 1]);

            return;
        }
        $connection->insert('plugin_course_evaluation_schema', ['id' => 1, 'version' => $version]);
    }

    /**
     * Place the course-home icon on every course. Teachers then show or hide
     * it with the eye on the course home, the same way as Survey.
     */
    public function addToolToEveryCourse(): int
    {
        $table = Database::get_main_table(TABLE_MAIN_COURSE);
        $result = Database::query('SELECT id FROM '.$table.' ORDER BY id');
        $count = 0;
        while ($row = Database::fetch_assoc($result)) {
            $this->install_course_fields((int) $row['id'], true);
            ++$count;
        }

        return $count;
    }

    public function uninstall(): void
    {
        $this->removeCertificateAssessments();
        $connection = Database::getManager()->getConnection();
        foreach ([
            'plugin_course_evaluation_answer',
            'plugin_course_evaluation_start',
            'plugin_course_evaluation_extension',
            'plugin_course_evaluation_snapshot',
            'plugin_course_evaluation_submission',
            'plugin_course_evaluation_evaluation',
            'plugin_course_evaluation_campaign',
            'plugin_course_evaluation_question',
            'plugin_course_evaluation_template',
            'plugin_course_evaluation_schema',
        ] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->uninstall_course_fields_in_all_courses();
    }

    private function removeCertificateAssessments(): void
    {
        $connection = Database::getManager()->getConnection();
        $schema = $connection->createSchemaManager();
        $evaluationTable = null;
        foreach (['plugin_course_evaluation_evaluation', 'plugin_course_evaluation_campaign'] as $candidate) {
            if ($schema->tablesExist([$candidate])) {
                $evaluationTable = $candidate;
                break;
            }
        }
        if (null === $evaluationTable || !$schema->tablesExist(['gradebook_evaluation'])) {
            return;
        }
        $columns = $schema->listTableColumns($evaluationTable);
        if (!isset($columns['gradebook_evaluation_id'])) {
            return;
        }
        $ids = array_values(array_filter(array_map(
            'intval',
            $connection->fetchFirstColumn(
                'SELECT gradebook_evaluation_id FROM '.$evaluationTable.'
                 WHERE gradebook_evaluation_id IS NOT NULL AND gradebook_evaluation_id > 0'
            )
        )));
        if (!$ids) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($schema->tablesExist(['gradebook_result', 'gradebook_result_attempt'])) {
            $connection->executeStatement(
                'DELETE a FROM gradebook_result_attempt a
                 INNER JOIN gradebook_result r ON r.id = a.result_id
                 WHERE r.evaluation_id IN ('.$placeholders.')',
                $ids
            );
        }
        if ($schema->tablesExist(['gradebook_result_log'])) {
            $connection->executeStatement(
                'DELETE FROM gradebook_result_log WHERE evaluation_id IN ('.$placeholders.')',
                $ids
            );
        }
        if ($schema->tablesExist(['gradebook_result'])) {
            $connection->executeStatement(
                'DELETE FROM gradebook_result WHERE evaluation_id IN ('.$placeholders.')',
                $ids
            );
        }
        $connection->executeStatement(
            'DELETE FROM gradebook_evaluation WHERE id IN ('.$placeholders.')',
            $ids
        );
    }

    private function seedDefaultTemplate(int $createdBy): void
    {
        $connection = Database::getManager()->getConnection();
        $existing = $connection->fetchOne(
            'SELECT id FROM plugin_course_evaluation_template WHERE scope = :scope AND title = :title',
            ['scope' => 'global', 'title' => 'Standard course evaluation']
        );
        if ($existing) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $connection->insert('plugin_course_evaluation_template', [
            'title' => 'Standard course evaluation',
            'description' => 'Default questionnaire for instructor, content, delivery, and course improvement.',
            'scope' => 'global',
            'course_id' => null,
            'source_template_id' => null,
            'is_active' => 1,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = (int) $connection->lastInsertId();

        $questions = [
            ['instructor', 'instructor', 'Which instructors did you consult?', 0],
            ['instructor', 'scale', 'The instructor explained the objectives clearly.', 1],
            ['instructor', 'scale', 'The instructor was knowledgeable about the subject.', 1],
            ['instructor', 'scale', 'The instructor encouraged participation and questions.', 1],
            ['instructor', 'scale', 'The instructor gave useful feedback.', 1],
            ['content', 'scale', 'The content matched the stated objectives.', 1],
            ['content', 'scale', 'The materials were clear and well organized.', 1],
            ['content', 'scale', 'The activities and exercises helped me learn.', 1],
            ['content', 'scale', 'The level of difficulty was appropriate.', 1],
            ['delivery', 'scale', 'The pace of the course was appropriate.', 1],
            ['delivery', 'scale', 'The course was well organized.', 1],
            ['delivery', 'scale', 'The learning environment supported my learning.', 1],
            ['organization', 'scale', 'I knew what was expected of me.', 1],
            ['organization', 'yes_no', 'I would recommend this course to a colleague.', 1],
            ['improvement', 'text', 'What should we keep doing?', 0],
            ['improvement', 'text', 'What should we change to improve this course?', 0],
        ];

        $position = 1;
        foreach ($questions as [$category, $type, $prompt, $required]) {
            $connection->insert('plugin_course_evaluation_question', [
                'template_id' => $templateId,
                'category' => $category,
                'type' => $type,
                'prompt' => $prompt,
                'help_text' => 'instructor' === $type ? 'Select the instructors you asked for help. Leave this empty if you did not consult one.' : null,
                'required' => $required,
                'position' => $position,
                'scale_min' => 1,
                'scale_max' => 'yes_no' === $type ? 1 : 5,
                'scale_low_label' => 'scale' === $type ? 'Strongly disagree' : null,
                'scale_high_label' => 'scale' === $type ? 'Strongly agree' : null,
            ]);
            ++$position;
        }
    }

    private function templateTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_template (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            scope VARCHAR(16) NOT NULL,
            course_id INT DEFAULT NULL,
            source_template_id INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL,
            created_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function questionTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_question (
            id INT AUTO_INCREMENT NOT NULL,
            template_id INT NOT NULL,
            category VARCHAR(32) NOT NULL,
            type VARCHAR(16) NOT NULL,
            prompt LONGTEXT NOT NULL,
            help_text LONGTEXT DEFAULT NULL,
            required TINYINT(1) NOT NULL,
            position INT NOT NULL,
            scale_min INT NOT NULL,
            scale_max INT NOT NULL,
            scale_low_label VARCHAR(64) DEFAULT NULL,
            scale_high_label VARCHAR(64) DEFAULT NULL,
            INDEX IDX_CE_QUESTION_TEMPLATE (template_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CE_QUESTION_TEMPLATE FOREIGN KEY (template_id)
                REFERENCES plugin_course_evaluation_template (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function evaluationTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_evaluation (
            id INT AUTO_INCREMENT NOT NULL,
            template_id INT NOT NULL,
            course_id INT NOT NULL,
            session_id INT NOT NULL,
            instructor_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            questionnaire_version INT NOT NULL DEFAULT 1,
            status VARCHAR(16) NOT NULL,
            anonymous TINYINT(1) NOT NULL,
            opens_at DATETIME DEFAULT NULL,
            closes_at DATETIME DEFAULT NULL,
            require_certificate TINYINT(1) NOT NULL DEFAULT 0,
            gradebook_evaluation_id INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_CE_EVALUATION_TEMPLATE (template_id),
            UNIQUE INDEX uniq_course_session (course_id, session_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CE_EVALUATION_TEMPLATE FOREIGN KEY (template_id)
                REFERENCES plugin_course_evaluation_template (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function submissionTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_submission (
            id INT AUTO_INCREMENT NOT NULL,
            evaluation_id INT NOT NULL,
            user_id INT NOT NULL,
            template_id INT DEFAULT NULL,
            questionnaire_version INT DEFAULT NULL,
            improvement_comment LONGTEXT DEFAULT NULL,
            submitted_at DATETIME NOT NULL,
            INDEX IDX_CE_SUBMISSION_EVALUATION (evaluation_id),
            UNIQUE INDEX uniq_evaluation_user (evaluation_id, user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CE_SUBMISSION_EVALUATION FOREIGN KEY (evaluation_id)
                REFERENCES plugin_course_evaluation_evaluation (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function snapshotTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_snapshot (
            id INT AUTO_INCREMENT NOT NULL,
            evaluation_id INT NOT NULL,
            version INT NOT NULL,
            template_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_evaluation_version (evaluation_id, version),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function startTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_start (
            id INT AUTO_INCREMENT NOT NULL,
            evaluation_id INT NOT NULL,
            user_id INT NOT NULL,
            started_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_ce_start_user (evaluation_id, user_id),
            INDEX IDX_CE_START_EVALUATION (evaluation_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function extensionTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_extension (
            id INT AUTO_INCREMENT NOT NULL,
            evaluation_id INT NOT NULL,
            user_id INT NOT NULL,
            closes_at DATETIME NOT NULL,
            granted_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_evaluation_user_extension (evaluation_id, user_id),
            INDEX IDX_CE_EXTENSION_EVALUATION (evaluation_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CE_EXTENSION_EVALUATION FOREIGN KEY (evaluation_id)
                REFERENCES plugin_course_evaluation_evaluation (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function answerTableSql(): string
    {
        return 'CREATE TABLE plugin_course_evaluation_answer (
            id INT AUTO_INCREMENT NOT NULL,
            submission_id INT NOT NULL,
            question_id INT NOT NULL,
            score INT DEFAULT NULL,
            text_value LONGTEXT DEFAULT NULL,
            INDEX IDX_CE_ANSWER_SUBMISSION (submission_id),
            INDEX IDX_CE_ANSWER_QUESTION (question_id),
            UNIQUE INDEX uniq_submission_question (submission_id, question_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CE_ANSWER_SUBMISSION FOREIGN KEY (submission_id)
                REFERENCES plugin_course_evaluation_submission (id) ON DELETE CASCADE,
            CONSTRAINT FK_CE_ANSWER_QUESTION FOREIGN KEY (question_id)
                REFERENCES plugin_course_evaluation_question (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }
}
