<?php

declare(strict_types=1);

require_once __DIR__.'/../../main/inc/global.inc.php';
require_once __DIR__.'/src/CourseEvaluationPlugin.php';
require_once __DIR__.'/src/Entity/Template.php';
require_once __DIR__.'/src/Entity/Question.php';
require_once __DIR__.'/src/Entity/Evaluation.php';
require_once __DIR__.'/src/Entity/Submission.php';
require_once __DIR__.'/src/Entity/Answer.php';
require_once __DIR__.'/src/EvaluationManager.php';
require_once __DIR__.'/src/EvaluationView.php';

use Chamilo\PluginBundle\CourseEvaluation\Entity\Question;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Template;
use Chamilo\PluginBundle\CourseEvaluation\EvaluationManager;
use Chamilo\PluginBundle\CourseEvaluation\EvaluationView;

api_protect_course_script(true);

try {
$plugin = CourseEvaluationPlugin::create();
if (!$plugin->isEnabled()) {
    api_not_allowed(true);
}
$manager = new EvaluationManager();
$view = new EvaluationView($plugin);
$courseId = (int) api_get_course_int_id();
$sessionId = (int) api_get_session_id();
$userId = (int) api_get_user_id();
$action = (string) ($_REQUEST['action'] ?? 'home');
$canManage = $manager->canManageCourse();
$message = '';
$level = 'success';

if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
    if (class_exists('Security') && !Security::check_token('post')) {
        api_not_allowed(true);
    }
    $do = (string) ($_POST['do'] ?? '');
    try {
        $message = handlePost($do, $manager, $view, $courseId, $sessionId, $userId, $canManage);
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        $level = 'danger';
    }
    Security::clear_token();
}

$evaluation = $manager->findEvaluation($courseId, $sessionId);
$globalTemplates = $manager->globalTemplates(true);

$breadcrumbTitle = $plugin->get_lang('CourseEvaluation');
if (in_array($action, ['edit', 'questions', 'add'], true)) {
    $interbreadcrumb[] = ['url' => $view->courseUrl('home'), 'name' => $plugin->get_lang('CourseEvaluation')];
    $breadcrumbTitle = $plugin->get_lang('CourseEvaluationQuestions');
} elseif ('responses' === $action) {
    $interbreadcrumb[] = ['url' => $view->courseUrl('home'), 'name' => $plugin->get_lang('CourseEvaluation')];
    $breadcrumbTitle = $plugin->get_lang('ViewResponses');
} elseif ('response' === $action) {
    $interbreadcrumb[] = ['url' => $view->courseUrl('home'), 'name' => $plugin->get_lang('CourseEvaluation')];
    $interbreadcrumb[] = ['url' => $view->courseUrl('responses'), 'name' => $plugin->get_lang('ViewResponses')];
    $breadcrumbTitle = $plugin->get_lang('ViewResponses');
} elseif ('report' === $action) {
    $interbreadcrumb[] = ['url' => $view->courseUrl('home'), 'name' => $plugin->get_lang('CourseEvaluation')];
    $reportTab = (string) ($_GET['tab'] ?? 'results');
    $reportTabTitle = [
        'mentors' => 'MentorPerformance',
        'sessions' => 'Sessions',
        'responses' => 'ViewResponses',
    ][$reportTab] ?? '';
    if ('' !== $reportTabTitle) {
        $interbreadcrumb[] = ['url' => $view->courseUrl('report'), 'name' => $plugin->get_lang('CourseEvaluationReport')];
        $breadcrumbTitle = $plugin->get_lang($reportTabTitle);
    } else {
        $breadcrumbTitle = $plugin->get_lang('CourseEvaluationReport');
    }
} elseif ('mine' === $action) {
    $interbreadcrumb[] = ['url' => $view->courseUrl('home'), 'name' => $plugin->get_lang('CourseEvaluation')];
    $breadcrumbTitle = $plugin->get_lang('ViewYourEvaluation');
}
Display::display_header($breadcrumbTitle);
echo $view->styles();
echo '<div id="ce-page">';
echo $view->courseToolbar('home' === $action ? null : $view->courseUrl('home'));
echo '<div class="ce-wrap" id="ce-root">';
$pageTitle = in_array($action, ['edit', 'questions', 'add'], true)
    ? 'CourseEvaluationQuestions'
    : 'CourseEvaluation';
$showReportHeader = 'report' === $action && $canManage;
if (!$showReportHeader) {
    echo '<h1>'.$view->e($plugin->get_lang($pageTitle)).'</h1>';
}
$studentFinished = !$canManage && $evaluation && $manager->findSubmission($evaluation, $userId);
echo $view->flash($message, $level);
if (!$showReportHeader && !$studentFinished) {
    echo contextSummary($view, $manager, $courseId, $sessionId, $evaluation, !$canManage);
}

if (in_array($action, ['edit', 'questions', 'add'], true) && $canManage) {
    echo evaluationEditor($view, $manager, $globalTemplates, $evaluation, $courseId, $sessionId);
} elseif ('response' === $action && $canManage) {
    echo responseDetail($view, $manager, $courseId, $sessionId);
} elseif ('responses' === $action && $canManage) {
    echo responsesScreen($view, $manager, $evaluation, $courseId, $sessionId);
} elseif ('report' === $action && $canManage) {
    echo reportScreen($view, $manager, $courseId, $sessionId);
} elseif ('mine' === $action && !$canManage) {
    echo studentResponse($view, $manager, $evaluation, $userId);
} elseif ($canManage) {
    echo teacherHome($view, $manager, $evaluation, $courseId, $sessionId);
} else {
    echo studentHome($view, $manager, $evaluation, $userId, $courseId, $sessionId, $message, $level);
}
echo '</div></div>';
Display::display_footer();
} catch (Throwable $exception) {
    if ($exception instanceof \Chamilo\CoreBundle\Exception\NotAllowedException) {
        throw $exception;
    }
    if (class_exists(EvaluationView::class)) {
        EvaluationView::renderFailure($exception);
    }
    throw $exception;
}

function handlePost(
    string $do,
    EvaluationManager $manager,
    EvaluationView $view,
    int $courseId,
    int $sessionId,
    int $userId,
    bool $canManage
): string {
    if ('submit_evaluation' === $do) {
        return submitEvaluation($manager, $view, $courseId, $sessionId, $userId);
    }
    if (!$canManage) {
        throw new RuntimeException($view->t('StudentsOnly'));
    }

    return match ($do) {
        'copy_template' => copyTemplate($manager, $view, $courseId, $userId),
        'save_question' => saveQuestion($manager, $view, $courseId),
        'delete_question' => deleteQuestion($manager, $view, $courseId),
        'move_question' => moveQuestion($manager, $courseId),
        'publish_questions' => publishQuestions($manager, $view, $courseId, $sessionId),
        'discard_questions' => discardQuestions($manager, $view, $courseId, $sessionId),
        'open_evaluation' => openEvaluation($manager, $view, $courseId, $sessionId),
        'copy_session_evaluation' => copySessionEvaluation($manager, $view, $courseId, $sessionId, $userId),
        'grant_extension' => grantExtension($manager, $view, $courseId, $sessionId, $userId),
        'revoke_extension' => revokeExtension($manager, $view, $courseId, $sessionId),
        default => '',
    };
}

function copyTemplate(EvaluationManager $manager, EvaluationView $view, int $courseId, int $userId): string
{
    $source = $manager->findTemplate((int) ($_POST['template_id'] ?? 0));
    if (!$source instanceof Template || Template::SCOPE_GLOBAL !== $source->getScope() || !$source->isActive()) {
        throw new RuntimeException($view->t('ChooseQuestionnaire'));
    }
    $copy = $manager->copyTemplateToCourse($source, $courseId, $userId);
    header('Location: '.$view->courseUrl('edit', ['template_id' => (int) $copy->getId()]));
    exit;
}

function saveQuestion(EvaluationManager $manager, EvaluationView $view, int $courseId): string
{
    $template = ownedTemplate($manager, $courseId, (int) ($_POST['template_id'] ?? 0));
    if (sessionQuestionsLocked($manager, $courseId)) {
        throw new RuntimeException($view->t('LockedQuestionnaire'));
    }
    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    $type = (string) ($_POST['type'] ?? '');
    $category = (string) ($_POST['category'] ?? '');
    if ('' === $prompt || '' === $type || '' === $category) {
        throw new RuntimeException($view->t('MissingRequired'));
    }
    if (Question::TYPE_SCALE === $type && ('' === trim((string) ($_POST['scale_low_label'] ?? '')) || '' === trim((string) ($_POST['scale_high_label'] ?? '')))) {
        throw new RuntimeException($view->t('ScaleLabelsRequired'));
    }
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $question = $questionId > 0 ? $manager->findQuestion($questionId) : null;
    if ($question && $question->getTemplate()->getId() !== $template->getId()) {
        throw new RuntimeException($view->t('LockedQuestionnaire'));
    }
    $sessionId = (int) api_get_session_id();
    $evaluation = $manager->findEvaluation($courseId, $sessionId);
    $answered = $evaluation && $manager->templateHasSubmissions($template);
    if ($questionId <= 0 || ($answered && $sessionId <= 0)) {
        $manager->beginQuestionBatch($template);
        $evaluation = $manager->findEvaluation($courseId, $sessionId);
        if ($evaluation) {
            $template = $evaluation->getTemplate();
            if ($question instanceof Question) {
                $question = $manager->questionOnTemplate($template, $question);
            }
        }
    }
    $manager->saveQuestion($template, $question, $_POST);

    return $view->t('Saved');
}

function deleteQuestion(EvaluationManager $manager, EvaluationView $view, int $courseId): string
{
    $question = $manager->findQuestion((int) ($_POST['question_id'] ?? 0));
    if (!$question || (int) $question->getTemplate()->getCourseId() !== $courseId) {
        throw new RuntimeException($view->t('NoQuestions'));
    }
    if (sessionQuestionsLocked($manager, $courseId)) {
        throw new RuntimeException($view->t('LockedQuestionnaire'));
    }
    $template = $question->getTemplate();
    $manager->beginQuestionBatch($template);
    $evaluation = $manager->findEvaluation($courseId, (int) api_get_session_id());
    if ($evaluation && $evaluation->getTemplate()->getId() !== $template->getId()) {
        $question = $manager->questionOnTemplate($evaluation->getTemplate(), $question);
    }
    if ($question instanceof Question) {
        $manager->deleteQuestion($question);
    }

    return $view->t('Deleted');
}

function publishQuestions(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId): string
{
    $evaluation = $manager->findEvaluation($courseId, $sessionId);
    if (!$evaluation || (int) $evaluation->getTemplate()->getCourseId() !== $courseId) {
        throw new RuntimeException($view->t('NoQuestions'));
    }
    $manager->publishQuestionBatch($evaluation);

    return $view->t('QuestionBatchSaved');
}

function discardQuestions(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId): string
{
    $evaluation = $manager->findEvaluation($courseId, $sessionId);
    if (!$evaluation || (int) $evaluation->getTemplate()->getCourseId() !== $courseId) {
        throw new RuntimeException($view->t('NoQuestions'));
    }
    $manager->discardQuestionBatch($evaluation);

    return $view->t('QuestionBatchDiscarded');
}

function moveQuestion(EvaluationManager $manager, int $courseId): string
{
    $question = $manager->findQuestion((int) ($_POST['question_id'] ?? 0));
    if ($question && (int) $question->getTemplate()->getCourseId() === $courseId && !sessionQuestionsLocked($manager, $courseId)) {
        $template = $question->getTemplate();
        if ((int) api_get_session_id() <= 0 && $manager->templateHasSubmissions($template)) {
            $manager->beginQuestionBatch($template);
            $evaluation = $manager->findEvaluation($courseId, 0);
            if ($evaluation && $evaluation->getTemplate()->getId() !== $template->getId()) {
                $question = $manager->questionOnTemplate($evaluation->getTemplate(), $question);
            }
        }
        if ($question instanceof Question) {
            $manager->moveQuestion($question, (int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1);
        }
    }

    return '';
}

function openEvaluation(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId): string
{
    $globalId = (int) ($_POST['global_template_id'] ?? 0);
    $templateId = (int) ($_POST['template_id'] ?? 0);
    $restoreVersion = (int) ($_POST['restore_version'] ?? 0);
    $source = (string) ($_POST['question_source'] ?? '');
    if (str_starts_with($source, 'version:')) {
        $restoreVersion = (int) substr($source, 8);
        $globalId = 0;
    } elseif (str_starts_with($source, 'template:')) {
        $globalId = (int) substr($source, 9);
        $restoreVersion = 0;
    }
    $existing = $manager->findEvaluation($courseId, $sessionId);
    if ($restoreVersion > 0 && $existing) {
        $template = $manager->restoreQuestionnaireVersion($existing, $restoreVersion, (int) api_get_user_id());
    } elseif ($globalId > 0) {
        $source = $manager->findTemplate($globalId);
        if (!$source instanceof Template || Template::SCOPE_GLOBAL !== $source->getScope() || !$source->isActive()) {
            throw new RuntimeException($view->t('ChooseQuestionnaire'));
        }
        $template = $manager->copyTemplateToCourse($source, $courseId, (int) api_get_user_id());
    } elseif ($templateId > 0) {
        $template = ownedTemplate($manager, $courseId, $templateId);
    } else {
        throw new RuntimeException($view->t('ChooseQuestionnaire'));
    }
    if (!$manager->questionsFor($template)) {
        throw new RuntimeException($view->t('NoQuestions'));
    }
    if (
        $existing
        && $sessionId > 0
        && $manager->submissionCount($existing) > 0
        && ($restoreVersion > 0 || $existing->getTemplate()->getId() !== $template->getId())
    ) {
        throw new RuntimeException($view->t('LockedQuestionnaire'));
    }
    $instructorId = 0;
    if ($sessionId > 0) {
        $allowed = array_map(
            static fn (array $choice): int => (int) $choice['id'],
            $manager->instructorChoices($courseId, $sessionId)
        );
        $instructorId = (int) ($_POST['instructor_id'] ?? 0);
        if ($instructorId <= 0) {
            $instructorId = (int) $manager->resolveInstructorId($courseId, $sessionId);
        }
        if ($instructorId > 0 && !in_array($instructorId, $allowed, true)) {
            throw new RuntimeException($view->t('InstructorNotOnCourse'));
        }
    }
    $title = $view->t('CourseEvaluation');
    if ($sessionId > 0) {
        $opensAt = parseDate($_POST['opens_at'] ?? null, false);
        $closesAt = parseDate($_POST['closes_at'] ?? null, true);
        if (!$opensAt || !$closesAt || $closesAt < $opensAt) {
            throw new RuntimeException($view->t('DateRangeRequired'));
        }
    } else {
        $opensAt = null;
        $closesAt = null;
    }
    $manager->openEvaluation(
        $template,
        $courseId,
        $sessionId,
        $instructorId > 0 ? $instructorId : null,
        $title,
        !empty($_POST['anonymous']),
        $opensAt,
        $closesAt,
        !empty($_POST['require_certificate'])
    );

    return $view->t($sessionId > 0 ? 'EvaluationOpened' : 'EvaluationSaved');
}

function copySessionEvaluation(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId, int $userId): string
{
    if ($sessionId <= 0) {
        throw new RuntimeException($view->t('NoSessionEvaluation'));
    }
    $evaluation = $manager->copyCourseEvaluationToSession($courseId, $sessionId, $userId, $view->t('CourseEvaluation'));
    if (!$evaluation) {
        throw new RuntimeException($view->t('NoCourseEvaluation'));
    }

    return $view->t('EvaluationOpened');
}

function submitEvaluation(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId, int $userId): string
{
    if ($sessionId > 0 && $manager->isDirectCourseStudent($userId, $courseId) && !$manager->isSessionLearner($userId, $courseId, $sessionId)) {
        $sessionId = 0;
    }
    $evaluation = $manager->findEvaluation($courseId, $sessionId);
    if (!$evaluation || !$manager->learnerCanSubmit($evaluation, $userId)) {
        throw new RuntimeException($view->t('NotOpen'));
    }
    if (!$manager->isStudentInContext($userId, $courseId, $sessionId)) {
        throw new RuntimeException($view->t('StudentsOnly'));
    }
    if ($manager->findSubmission($evaluation, $userId)) {
        return $view->t('AlreadySubmitted');
    }

    $posted = is_array($_POST['answer'] ?? null) ? $_POST['answer'] : [];
    $answers = [];
    foreach ($manager->questionsFor($evaluation->getTemplate()) as $question) {
        $row = $posted[$question->getId()] ?? $posted[(string) $question->getId()] ?? [];
        $score = isset($row['score']) && '' !== (string) $row['score'] ? (int) $row['score'] : null;
        $text = isset($row['text']) ? trim(is_array($row['text']) ? implode(',', $row['text']) : (string) $row['text']) : null;
        if (Question::TYPE_INSTRUCTOR === $question->getType()) {
            $allowed = array_map(static fn (array $choice): int => (int) $choice['id'], $manager->availableInstructors($courseId, $sessionId));
            $picked = [];
            foreach (array_filter(array_map('intval', explode(',', (string) $text))) as $pickedId) {
                if (in_array($pickedId, $allowed, true)) {
                    $picked[] = $pickedId;
                }
            }
            $text = $picked ? implode(',', $picked) : null;
            $score = null;
        }
        if ($question->isRequired()) {
            $missing = Question::TYPE_TEXT === $question->getType() || Question::TYPE_INSTRUCTOR === $question->getType()
                ? ('' === (string) $text)
                : null === $score;
            if ($missing) {
                throw new RuntimeException($view->t('MissingRequired'));
            }
        }
        if (null !== $score && Question::TYPE_INSTRUCTOR !== $question->getType()) {
            $max = Question::TYPE_YES_NO === $question->getType() ? 1 : $question->getScaleMax();
            if ($score < $question->getScaleMin() || $score > $max) {
                throw new RuntimeException($view->t('MissingRequired'));
            }
        }
        $answers[(int) $question->getId()] = ['score' => $score, 'text' => $text];
    }

    $manager->submit($evaluation, $userId, $answers, (string) ($_POST['improvement_comment'] ?? ''));

    return $view->t('Submitted');
}

function sessionQuestionsLocked(EvaluationManager $manager, int $courseId): bool
{
    $sessionId = (int) api_get_session_id();
    if ($sessionId <= 0) {
        return false;
    }
    $evaluation = $manager->findEvaluation($courseId, $sessionId);

    return $evaluation && $manager->templateHasSubmissions($evaluation->getTemplate());
}

function ownedTemplate(EvaluationManager $manager, int $courseId, int $templateId): Template
{
    $template = $manager->findTemplate($templateId);
    if (!$template instanceof Template || Template::SCOPE_COURSE !== $template->getScope() || (int) $template->getCourseId() !== $courseId) {
        throw new RuntimeException('Choose a questionnaire that belongs to this course.');
    }

    return $template;
}

function parseDate(mixed $value, bool $endOfDay): ?DateTime
{
    $value = trim((string) $value);
    if ('' === $value) {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date) {
        return null;
    }
    $date->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);

    return $date;
}

function contextSummary(EvaluationView $view, EvaluationManager $manager, int $courseId, int $sessionId, $evaluation, bool $forStudent): string
{
    $instructorId = $evaluation?->getInstructorId() ?: $manager->resolveInstructorId($courseId, $sessionId);
    $parts = [
        $sessionId > 0 ? $manager->sessionTitle($sessionId) : $view->t('SelfPaced'),
    ];
    if ($evaluation && $evaluation->getOpensAt() && $evaluation->getClosesAt()) {
        $parts[] = $evaluation->getOpensAt()->format('j M Y').' – '.$evaluation->getClosesAt()->format('j M Y');
    }
    if ($sessionId > 0) {
        $parts[] = $view->t('Instructor').': '.($instructorId ? $manager->userName((int) $instructorId) : $view->t('NoInstructor'));
    }
    if (!$forStudent && $evaluation) {
        $parts[] = $view->t('CurrentStatus').': '.$view->statusLabel($evaluation->getStatus());
        $parts[] = $view->t('Submissions').': '.$manager->submissionCountForContext($courseId, $sessionId, $evaluation);
    } elseif ($forStudent && $evaluation) {
        $parts[] = $view->t('Version').' '.$evaluation->getQuestionnaireVersion();
    }

    return '<p class="ce-meta">'.implode(' · ', array_map([$view, 'e'], $parts)).'</p>';
}

function teacherHome(
    EvaluationView $view,
    EvaluationManager $manager,
    $evaluation,
    int $courseId,
    int $sessionId
): string {
    $items = [];
    if (!$evaluation) {
        $items[] = [
            'label' => $view->t('AddEvaluation'),
            'url' => $view->courseUrl('edit'),
            'icon' => 'mdi mdi-plus-box',
            'primary' => true,
        ];
    }
    $items[] = [
        'label' => $view->t('ViewResponses'),
        'url' => $view->courseUrl('responses'),
        'icon' => 'mdi mdi-eye',
        'primary' => true,
    ];
    $items[] = [
        'label' => $view->t('Reports'),
        'url' => $view->courseUrl('report', ['this_session' => 1]),
        'icon' => 'mdi mdi-chart-box',
        'primary' => true,
    ];
    $end = $evaluation ? [[
        'label' => $view->t('EditEvaluation'),
        'url' => $view->courseUrl('edit'),
        'icon' => 'mdi mdi-pencil',
    ]] : [];
    $html = $view->iconMenu('ce-home-menu', $items, $end);
    if (!$evaluation) {
        if ($sessionId > 0 && $manager->findEvaluation($courseId, 0)) {
            $html .= '<form method="post" class="mb-3">'
                .$view->tokenField()
                .'<input type="hidden" name="do" value="copy_session_evaluation">'
                .'<p class="text-muted">'.$view->e($view->t('UseCourseEvaluationHelp')).'</p>'
                .'<button class="p-button p-component p-button-success" type="submit"><span class="p-button-label">'.$view->e($view->t('UseCourseEvaluation')).'</span></button>'
                .'</form>';
        }

        return $html.'<p class="text-muted">'.$view->e($sessionId > 0 ? $view->t('NoSessionEvaluation') : $view->t('NoCourseEvaluation')).'</p>';
    }

    $html .= '<p class="ce-version">'.$view->e($view->t('Version').' '.$evaluation->getQuestionnaireVersion()).'</p>';
    if ($sessionId > 0 && $evaluation->getOpensAt() && $evaluation->getClosesAt()) {
        $html .= '<p class="text-muted">'.$view->e($evaluation->getOpensAt()->format('j M Y').' – '.$evaluation->getClosesAt()->format('j M Y')).'</p>';
    }
    $html .= learnerStatusTable($view, $manager, $evaluation, $courseId, $sessionId);
    if ($sessionId > 0) {
        $html .= extensionForm($view, $manager, $evaluation, $courseId, $sessionId);
    }

    return $html;
}

function evaluationEditor(
    EvaluationView $view,
    EvaluationManager $manager,
    array $globalTemplates,
    $evaluation,
    int $courseId,
    int $sessionId
): string {
    $instructorId = $evaluation?->getInstructorId() ?: $manager->resolveInstructorId($courseId, $sessionId);
    $locked = $evaluation && $sessionId > 0 && $manager->templateHasSubmissions($evaluation->getTemplate());
    $html = '<div class="ce-heading-row">'
        .($evaluation ? '<p class="ce-version">'.$view->e($view->t('Version').' '.$evaluation->getQuestionnaireVersion()).'</p>' : '')
        .$view->dialogIcon(
            $evaluation ? $view->t('Edit') : $view->t('AddEvaluation'),
            'ce-header-dialog',
            $evaluation ? 'mdi mdi-pencil' : 'mdi mdi-plus',
            !$evaluation
        )
        .'</div>';
    if ($sessionId > 0 && $evaluation && $evaluation->getOpensAt() && $evaluation->getClosesAt()) {
        $html .= '<p class="text-muted">'.$view->e($evaluation->getOpensAt()->format('j M Y').' – '.$evaluation->getClosesAt()->format('j M Y')).'</p>';
    }
    $html .= '<dialog id="ce-header-dialog" class="ce-dialog">'
        .'<form method="post" action="'.$view->e($view->courseUrl('edit')).'" class="ce-form">'
        .$view->tokenField()
        .'<input type="hidden" name="do" value="open_evaluation">'
        .'<h3>'.$view->e($evaluation ? $view->t('EditEvaluation') : $view->t('AddEvaluation')).'</h3>'
        .'<p class="text-muted">'.$view->e($sessionId > 0 ? $view->t('DateRangeHelp') : $view->t('SelfPacedAlwaysOpen')).'</p>';
    if ($evaluation) {
        $html .= '<input type="hidden" name="template_id" value="'.(int) $evaluation->getTemplate()->getId().'">';
    } else {
        $html .= '<input type="hidden" name="template_id" value="0">';
    }
    $snapshots = $evaluation && $sessionId <= 0 ? array_values(array_filter(
        $manager->questionnaireSnapshots($evaluation),
        static fn (array $snapshot): bool => (int) $snapshot['version'] < $evaluation->getQuestionnaireVersion()
    )) : [];
    if ($evaluation && !$locked && ($globalTemplates || $snapshots)) {
        $html .= '<div class="mb-3"><label class="form-label">'.$view->e($view->t('QuestionSource')).'</label>'
            .'<select class="form-select" name="question_source">'
            .'<option value="">'.$view->e($view->t('KeepQuestions')).'</option>';
        if ($globalTemplates) {
            $html .= '<optgroup label="'.$view->e($view->t('ReplaceWithTemplate')).'">';
            foreach ($globalTemplates as $template) {
                $html .= '<option value="template:'.(int) $template->getId().'">'.$view->e($template->getTitle()).'</option>';
            }
            $html .= '</optgroup>';
        }
        if ($snapshots) {
            $html .= '<optgroup label="'.$view->e($view->t('EarlierVersion')).'">';
            foreach ($snapshots as $snapshot) {
                $html .= '<option value="version:'.(int) $snapshot['version'].'">'.$view->e($view->t('Version').' '.$snapshot['version']).'</option>';
            }
            $html .= '</optgroup>';
        }
        $html .= '</select><div class="text-muted">'.$view->e($view->t('QuestionSourceHelp')).'</div></div>';
    } elseif ($globalTemplates && !$locked) {
        $html .= '<div class="mb-3"><label class="form-label">'.$view->e($view->t('GlobalTemplates')).'</label>'
            .'<select class="form-select" name="global_template_id">';
        foreach ($globalTemplates as $template) {
            $html .= '<option value="'.(int) $template->getId().'">'.$view->e($template->getTitle()).'</option>';
        }
        $html .= '</select></div>';
    }
    if (!$globalTemplates && !$evaluation) {
        $html .= '<p>'.$view->e($view->t('ChooseQuestionnaire')).'</p>';
    }
    if ($sessionId > 0) {
        $html .= '<div class="mb-3"><label class="form-label">'.$view->e($view->t('Instructor')).'</label>'
            .'<select class="form-select" name="instructor_id">';
        foreach ($manager->instructorChoices($courseId, $sessionId) as $choice) {
            $html .= '<option value="'.(int) $choice['id'].'"'.((int) $choice['id'] === (int) $instructorId ? ' selected' : '').'>'
                .$view->e($choice['name']).'</option>';
        }
        $html .= '</select></div>';
    }
    if ($sessionId > 0) {
        $html .= '<div class="row"><div class="col-md-6 mb-3"><label class="form-label">'.$view->e($view->t('OpensAt')).'</label>'
            .'<input class="form-control" type="date" name="opens_at" required value="'.$view->e($evaluation?->getOpensAt()?->format('Y-m-d') ?? '').'"></div>'
            .'<div class="col-md-6 mb-3"><label class="form-label">'.$view->e($view->t('ClosesAt')).'</label>'
            .'<input class="form-control" type="date" name="closes_at" required value="'.$view->e($evaluation?->getClosesAt()?->format('Y-m-d') ?? '').'"></div></div>';
    }
    $hasAssessment = $manager->hasGradebookAssessment($courseId, $sessionId);
    $html .= '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="anonymous" value="1" id="ce-anon"'
        .(($evaluation ? $evaluation->isAnonymous() : $view->anonymousByDefault()) ? ' checked' : '').'>'
        .'<label class="form-check-label" for="ce-anon">'.$view->e($view->t('AnonymousResponses')).'</label></div>'
        .'<div class="form-check mb-3'.($hasAssessment ? '' : ' ce-check-disabled').'"><input class="form-check-input" type="checkbox" name="require_certificate" value="1" id="ce-cert"'
        .($hasAssessment && ($evaluation?->requiresCertificate() ?? false) ? ' checked' : '')
        .($hasAssessment ? '' : ' disabled').'>'
        .'<label class="form-check-label" for="ce-cert">'.$view->e($view->t('RequireCertificate')).'</label></div>'
        .'<p class="text-muted">'.$view->e($view->t($hasAssessment ? 'RequireCertificateHelp' : 'RequireCertificateUnavailable')).'</p>'
        .'<div class="ce-dialog-actions">'
        .'<button class="p-button p-component p-button-success" type="submit"><span class="p-button-label">'.$view->e($view->t('Save')).'</span></button>'
        .'<button class="p-button p-component p-button-outlined p-button-secondary" type="button" onclick="this.closest(\'dialog\').close()"><span class="p-button-label">'.$view->e($view->t('Cancel')).'</span></button>'
        .'</div></form></dialog>';
    if (!$evaluation) {
        $html .= '<script>document.getElementById("ce-header-dialog").showModal()</script>';
    }
    $html .= '<h2>'.$view->e($view->t('Questions')).'</h2>';
    if ($evaluation) {
        $template = $evaluation->getTemplate();
        if ($sessionId <= 0) {
            $manager->ensureSupportQuestion($template);
        }
        $base = $view->courseUrl('edit', ['template_id' => (int) $template->getId()]);
        $editing = null;
        $questionId = (int) ($_GET['question_id'] ?? 0);
        if ($questionId > 0) {
            $editing = $manager->findQuestion($questionId);
            if ($editing && $editing->getTemplate()->getId() !== $template->getId()) {
                $editing = null;
            }
        }
        if ($locked) {
            $html .= '<div class="alert alert-info">'.$view->e($view->t('LockedQuestionnaire')).'</div>';
        } else {
            if ($manager->hasUnpublishedQuestionChanges($evaluation)) {
                $html .= '<p class="text-muted">'.$view->e($view->t('QuestionBatchHelp')).'</p>'
                    .'<div class="ce-tool-icons"><form method="post" action="'.$view->e($base).'">'
                    .$view->tokenField()
                    .'<input type="hidden" name="do" value="publish_questions">'
                    .'<button class="p-button p-component p-button-success" type="submit"><span class="p-button-label">'.$view->e($view->t('SaveChanges')).'</span></button>'
                    .'</form><form method="post" action="'.$view->e($base).'">'
                    .$view->tokenField()
                    .'<input type="hidden" name="do" value="discard_questions">'
                    .'<button class="p-button p-component p-button-outlined p-button-secondary" type="submit"><span class="p-button-label">'.$view->e($view->t('DiscardChanges')).'</span></button>'
                    .'</form></div>';
            }
            $html .= '<div class="ce-tool-icons">'.$view->addQuestionButton($base).'</div>'
                .$view->questionForm($editing, $base, (int) $template->getId(), null !== $editing || isset($_GET['add']));
        }

        return $html.$view->questionTable($manager->questionsFor($template), $base, !$locked);
    }
    if (!$globalTemplates) {
        return $html;
    }
    $previewId = (int) ($_GET['global_template_id'] ?? $globalTemplates[0]->getId());
    $html .= '<p class="text-muted">'.$view->e($view->t('QuestionsFromTemplate')).'</p>';
    foreach ($globalTemplates as $template) {
        $id = (int) $template->getId();
        $html .= '<div class="ce-template-questions" data-template="'.$id.'"'.($id === $previewId ? '' : ' hidden').'>'
            .$view->questionTable($manager->questionsFor($template), $view->courseUrl('edit'), false)
            .'</div>';
    }

    return $html;
}

function learnerStatusTable(EvaluationView $view, EvaluationManager $manager, $evaluation, int $courseId, int $sessionId): string
{
    $rows = [];
    foreach ($manager->learnerStatuses($evaluation, $courseId, $sessionId) as $learner) {
        if ($learner['submitted_at']) {
            $status = $view->t('SubmittedStatus');
            $date = !empty($learner['anonymous']) ? '' : substr($learner['submitted_at'], 0, 16);
        } elseif ($learner['extension']) {
            $status = $view->t('ExtensionOpen');
            $date = substr($learner['extension'], 0, 10);
        } else {
            $status = $view->t('NotSubmitted');
            $date = '';
        }
        $sessionLabel = (int) $learner['session_id'] > 0
            ? $manager->sessionTitle((int) $learner['session_id'])
            : $view->t('SelfPaced');
        $rows[] = [$view->e($learner['name']), $view->e($sessionLabel), $view->e($status), $view->e($date)];
    }

    return '<h2>'.$view->e($view->t('LearnerStatus')).'</h2>'
        .$view->dataTable(
            [$view->t('Learner'), $view->t('Session'), $view->t('Status'), $view->t('Date')],
            $rows,
            $view->t('NoLearners'),
            4
        );
}

function extensionForm(EvaluationView $view, EvaluationManager $manager, $evaluation, int $courseId, int $sessionId): string
{
    $html = '<h2>'.$view->e($view->t('StudentExtension')).'</h2>'
        .'<p class="text-muted">'.$view->e($view->t('StudentExtensionHelp')).'</p>';
    $learners = $manager->learnersWithoutSubmission($evaluation, $courseId, $sessionId);
    if ($learners) {
        $html .= '<form method="post" class="card card-body">'
            .$view->tokenField()
            .'<input type="hidden" name="do" value="grant_extension">'
            .'<div class="row"><div class="col-md-6 mb-3"><label class="form-label">'.$view->e($view->t('Learner')).'</label>'
            .'<select class="form-select" name="learner_id">';
        foreach ($learners as $learner) {
            $html .= '<option value="'.(int) $learner['id'].'">'.$view->e($learner['name']).'</option>';
        }
        $html .= '</select></div>'
            .'<div class="col-md-4 mb-3"><label class="form-label">'.$view->e($view->t('AllowUntil')).'</label>'
            .'<input class="form-control" type="date" name="closes_at" required></div></div>'
            .'<button class="btn btn-primary" type="submit">'.$view->e($view->t('Save')).'</button></form>';
    }
    $extensions = $manager->extensionsFor($evaluation);
    if (!$extensions) {
        return $html;
    }
    $rows = [];
    foreach ($extensions as $extension) {
        $rows[] = [
            $view->e($manager->userName((int) $extension['user_id'])),
            $view->e(substr((string) $extension['closes_at'], 0, 10)),
            '<div class="ce-actions"><form method="post">'.$view->tokenField()
            .'<input type="hidden" name="do" value="revoke_extension">'
            .'<input type="hidden" name="learner_id" value="'.(int) $extension['user_id'].'">'
            .$view->iconButton($view->t('Delete'), 'mdi mdi-delete', 'danger')
            .'</form></div>',
        ];
    }

    return $html.$view->dataTable(
        [$view->t('Learner'), $view->t('AllowUntil'), ''],
        $rows
    );
}

function grantExtension(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId, int $teacherId): string
{
    $evaluation = $manager->findEvaluation($courseId, $sessionId);
    if (!$evaluation) {
        throw new RuntimeException($view->t('NotOpen'));
    }
    $learnerId = (int) ($_POST['learner_id'] ?? 0);
    $closesAt = parseDate($_POST['closes_at'] ?? null, true);
    if ($learnerId <= 0 || !$closesAt) {
        throw new RuntimeException($view->t('DateRangeRequired'));
    }
    if ($manager->findSubmission($evaluation, $learnerId)) {
        throw new RuntimeException($view->t('AlreadySubmitted'));
    }
    if (!$manager->isCourseLearner($learnerId, $courseId, $sessionId)) {
        throw new RuntimeException($view->t('LearnerNotEnrolled'));
    }
    $manager->grantExtension($evaluation, $learnerId, $closesAt, $teacherId);

    return $view->t('ExtensionSaved');
}

function revokeExtension(EvaluationManager $manager, EvaluationView $view, int $courseId, int $sessionId): string
{
    $evaluation = $manager->findEvaluation($courseId, $sessionId);
    if ($evaluation) {
        $manager->revokeExtension($evaluation, (int) ($_POST['learner_id'] ?? 0));
    }

    return $view->t('ExtensionRemoved');
}

function responsesScreen(EvaluationView $view, EvaluationManager $manager, $evaluation, int $courseId, int $sessionId): string
{
    $html = '<h2>'.$view->e($view->t('ViewResponses')).'</h2>';
    if (!$evaluation) {
        return $html.$view->dataTable(
            [$view->t('Learner'), $view->t('SubmittedStatus'), $view->t('Version')],
            [],
            $view->t('NotOpen')
        );
    }
    $rows = [];
    foreach ($manager->contextSubmissions($courseId, $sessionId, $evaluation) as $response) {
        $who = !empty($response['anonymous']) ? $view->t('Anonymous') : $manager->userName((int) $response['user_id']);
        $url = $view->courseUrl('response', ['submission_id' => (int) $response['id']]);
        $responseSessionId = (int) ($response['session_id'] ?? 0);
        $sessionLabel = $responseSessionId > 0 ? $manager->sessionTitle($responseSessionId) : $view->t('SelfPaced');
        $rows[] = [
            '<a href="'.$view->e($url).'">'.$view->e($who).'</a>',
            $view->e($sessionLabel),
            $view->e(substr((string) $response['submitted_at'], 0, 16)),
            '<a href="'.$view->e($url).'">'.$view->e($view->t('Version').' '.(int) $response['version']).'</a>',
        ];
    }

    return $html.$view->dataTable(
        [$view->t('Learner'), $view->t('Session'), $view->t('SubmittedStatus'), $view->t('Version')],
        $rows,
        $view->t('NoResponses'),
        3
    );
}

function responseDetail(EvaluationView $view, EvaluationManager $manager, int $courseId, int $sessionId): string
{
    $html = '<h2>'.$view->e($view->t('ViewResponses')).'</h2>';
    $response = $manager->findCourseResponse($courseId, $sessionId, (int) ($_GET['submission_id'] ?? 0));
    if (!$response) {
        return $html.'<p class="text-muted">'.$view->e($view->t('NoResponses')).'</p>';
    }
    $template = $manager->findTemplate((int) $response['template_id']);
    $questions = $template ? $manager->questionsFor($template) : [];
    $who = !empty($response['anonymous']) ? $view->t('Anonymous') : $manager->userName((int) $response['user_id']);
    $html .= '<p class="ce-meta">'.$view->e($who.' · '.substr((string) $response['submitted_at'], 0, 16).' · '.$view->t('Version').' '.(int) $response['version']).'</p>';
    return $html.answerTable($view, $manager, $questions, $response);
}

function studentResponse(EvaluationView $view, EvaluationManager $manager, $evaluation, int $userId): string
{
    $html = '<h2>'.$view->e($view->t('ViewYourEvaluation')).'</h2>';
    if (!$evaluation || !$manager->isStudentInContext($userId, (int) $evaluation->getCourseId(), (int) $evaluation->getSessionId())) {
        return $html.'<p>'.$view->e($view->t('StudentsOnly')).'</p>';
    }
    $submission = $manager->findSubmission($evaluation, $userId);
    $response = $submission ? $manager->findResponse($evaluation, (int) $submission->getId()) : null;
    if (!$response) {
        return $html.'<p class="text-muted">'.$view->e($view->t('NoResponses')).'</p>';
    }
    $template = $manager->findTemplate((int) $response['template_id']);
    $questions = $template ? $manager->questionsFor($template) : [];
    $html .= '<p class="ce-meta">'.$view->e(substr((string) $response['submitted_at'], 0, 16).' · '.$view->t('Version').' '.(int) $response['version']).'</p>';

    return $html.answerTable($view, $manager, $questions, $response);
}

function answerTable(EvaluationView $view, EvaluationManager $manager, array $questions, array $response): string
{
    $rows = [];
    foreach ($questions as $question) {
        $answer = $response['answers'][(int) $question->getId()] ?? null;
        $value = '';
        if ($answer) {
            if (Question::TYPE_INSTRUCTOR === $question->getType()) {
                $names = [];
                foreach (array_filter(array_map('intval', explode(',', (string) ($answer['text_value'] ?? '')))) as $pickedId) {
                    $names[] = $manager->userName($pickedId);
                }
                $value = $names ? implode(', ', $names) : $view->t('NoInstructorConsulted');
            } elseif (Question::TYPE_TEXT === $question->getType()) {
                $value = (string) ($answer['text_value'] ?? '');
            } elseif (Question::TYPE_YES_NO === $question->getType() && null !== ($answer['score'] ?? null) && '' !== (string) $answer['score']) {
                $value = 1 === (int) $answer['score']
                    ? ($question->getScaleHighLabel() ?: $view->t('Yes'))
                    : ($question->getScaleLowLabel() ?: $view->t('No'));
            } else {
                $value = (string) ($answer['score'] ?? '');
            }
        }
        $rows[] = [$view->e($question->getPrompt()), nl2br($view->e($value))];
    }
    $comment = trim((string) $response['improvement_comment']);
    if ('' !== $comment) {
        $rows[] = [$view->e($view->t('ImprovementComments')), nl2br($view->e($comment))];
    }

    return $view->dataTable([$view->t('Prompt'), $view->t('Answers')], $rows, $view->t('NoResponses'));
}

function reportScreen(EvaluationView $view, EvaluationManager $manager, int $courseId, int $sessionId): string
{
    $filters = filtersFromRequest();
    $filters['course_id'] = $courseId;
    $filters['action'] = 'report';
    if ($sessionId > 0) {
        $filters['session_id'] = $sessionId;
        $filters['filter_session'] = true;
        $filters['by_session'] = false;
    } else {
        $filters['coach_id'] = (int) ($_GET['coach_id'] ?? 0);
    }
    $filters['tab'] = (string) ($_GET['tab'] ?? 'results');
    $export = $view->courseUrl('report');
    $html = '<div class="section-header section-header--h2">'
        .'<div class="section-header__title">'
        .'<h1>'.$view->e($view->t('CourseEvaluationReport')).'</h1>'
        .contextSummary($view, $manager, $courseId, $sessionId, $manager->findEvaluation($courseId, $sessionId), false)
        .'</div>'
        .'<div class="section-header__actions">'
        .'<a class="p-button p-component p-button-outlined p-button-sm" data-ce-export href="'.$view->e($export.'&export=csv&'.http_build_query($filters)).'" title="'.$view->e($view->t('ExportCsv')).'">'
        .'<span class="p-button-icon mdi mdi-file-delimited-outline"></span>'
        .'<span class="p-button-label">'.$view->e($view->t('ExportCsv')).'</span></a>'
        .'</div></div>';
    if (isset($_GET['export']) && 'csv' === $_GET['export']) {
        exportCsv($manager, $filters);
    }
    $html .= '<div id="ce-report">'
        .$view->reportMenu($filters)
        .$view->reportFilters($filters, 'start.php', true, $manager)
        .$view->reportTables($manager, $filters, false)
        .'</div>';

    return $html;
}

function studentHome(EvaluationView $view, EvaluationManager $manager, $evaluation, int $userId, int $courseId, int $sessionId, string $message = '', string $level = 'success'): string
{
    if (!$manager->isStudentInContext($userId, $courseId, $sessionId)) {
        return '<p>'.$view->e($view->t('StudentsOnly')).'</p>';
    }
    if ($evaluation && $manager->findSubmission($evaluation, $userId)) {
        $manager->ensureCertificateResult($evaluation, $userId);

        return studentResponse($view, $manager, $evaluation, $userId);
    }
    if (!$evaluation || !$manager->learnerCanSubmit($evaluation, $userId)) {
        return '<p>'.$view->e($view->t('NotOpen')).'</p>';
    }

    $notice = $evaluation->requiresCertificate()
        ? '<p class="text-muted">'.$view->e($view->t('CertificateRequiredNotice')).'</p>'
        : '';

    if ($sessionId <= 0) {
        $manager->ensureSupportQuestion($evaluation->getTemplate());
    }

    $showForm = isset($_GET['begin']) || ('danger' === $level && 'submit_evaluation' === (string) ($_POST['do'] ?? ''));
    if (!$showForm) {
        $label = $manager->hasEvaluationStarted($evaluation, $userId)
            ? 'ContinueEvaluation'
            : 'StartEvaluation';

        return $notice
            .'<p><a class="p-button p-component p-button-success" href="'.$view->e($view->courseUrl('home', ['begin' => 1])).'">'
            .'<span class="p-button-label">'.$view->e($view->t($label)).'</span></a></p>';
    }

    $manager->markEvaluationStarted($evaluation, $userId);

    return $notice
        .$view->studentForm(
            $evaluation,
            $manager->questionsFor($evaluation->getTemplate()),
            $view->courseUrl('home'),
            $manager->availableInstructors($courseId, $sessionId)
        );
}

function filtersFromRequest(): array
{
    return [
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '',
        'course_id' => (int) ($_GET['course_id'] ?? 0),
        'session_id' => (int) ($_GET['session_id'] ?? 0),
        'instructor_id' => (int) ($_GET['instructor_id'] ?? 0),
        'category' => (string) ($_GET['category'] ?? ''),
        'coach_id' => (int) ($_GET['coach_id'] ?? 0),
        'scope' => in_array((string) ($_GET['scope'] ?? ''), ['self_paced', 'session'], true) ? (string) $_GET['scope'] : '',
    ];
}

function exportCsv(EvaluationManager $manager, array $filters): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="course-evaluation.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['category', 'prompt', 'average', 'scores']);
    foreach ($manager->questionReport($filters) as $row) {
        fputcsv($out, [$row['category'], $row['prompt'], $row['average_score'], $row['score_count']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['comment', 'course_id', 'session_id', 'submitted_at']);
    foreach ($manager->improvementComments($filters) as $row) {
        fputcsv($out, [$row['improvement_comment'], $row['course_id'], $row['session_id'], $row['submitted_at']]);
    }
    fclose($out);
    exit;
}
