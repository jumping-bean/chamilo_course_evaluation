<?php

declare(strict_types=1);

$cidReset = true;

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

api_protect_admin_script(true);

try {
$plugin = CourseEvaluationPlugin::create();
if (!$plugin->isEnabled()) {
    api_not_allowed(true);
}
$manager = new EvaluationManager();
$view = new EvaluationView($plugin);
$action = (string) ($_REQUEST['action'] ?? 'templates');
$message = '';
$level = 'success';

if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
    if (class_exists('Security') && !Security::check_token('post')) {
        api_not_allowed(true);
    }
    try {
        $redirect = handleAdminPost((string) ($_POST['do'] ?? ''), $manager, $view, $plugin);
        Security::clear_token();
        header('Location: '.$redirect);
        exit;
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        $level = 'danger';
        Security::clear_token();
    }
}

if (isset($_GET['saved'])) {
    $message = $view->t('Saved');
} elseif (isset($_GET['deleted'])) {
    $message = $view->t('Deleted');
} elseif (isset($_GET['tools'])) {
    $message = sprintf($view->t('AddedToExistingCourses'), (int) $_GET['tools']);
}

if ('export' === $action) {
    adminExport($manager);
}

$pluginsUrl = api_get_path(WEB_CODE_PATH).'admin/settings.php?category=Plugins';
$interbreadcrumb[] = [
    'url' => api_get_path(WEB_CODE_PATH).'admin/index.php',
    'name' => get_lang('Administration'),
];
$interbreadcrumb[] = [
    'url' => $pluginsUrl,
    'name' => get_lang('Plugins'),
];
$breadcrumbTitle = $plugin->get_lang('QuestionTemplates');
$reportTitles = [
    'courses' => 'CourseReport',
    'report' => 'InstructorReport',
];
if (isset($reportTitles[$action])) {
    $interbreadcrumb[] = [
        'url' => 'admin.php?action=templates',
        'name' => $plugin->get_lang('QuestionTemplates'),
    ];
    $breadcrumbTitle = $plugin->get_lang($reportTitles[$action]);
} elseif ('new' === $action || (int) ($_GET['template_id'] ?? 0) > 0) {
    $interbreadcrumb[] = [
        'url' => 'admin.php?action=templates',
        'name' => $plugin->get_lang('QuestionTemplates'),
    ];
    $editing = $manager->findTemplate((int) ($_GET['template_id'] ?? 0));
    $breadcrumbTitle = $editing ? $editing->getTitle() : $plugin->get_lang('NewTemplate');
}
Display::display_header($breadcrumbTitle);
echo $view->styles();
$editingTemplate = 'new' === $action || (int) ($_GET['template_id'] ?? 0) > 0;
$backUrl = $editingTemplate || isset($reportTitles[$action])
    ? 'admin.php?action=templates'
    : api_get_path(WEB_CODE_PATH).'admin/settings.php?category=Plugins';
$backLabel = $view->t('Back');
echo '<div id="ce-screen">';
echo Display::toolbarAction('ce-toolbar', [
    Display::url(
        Display::getMdiIcon('arrow-left-bold-box', 'ch-tool-icon', null, ICON_SIZE_MEDIUM, $backLabel),
        $backUrl,
        ['title' => $backLabel]
    ),
]);
echo adminNav($view, $action, []);
echo '<div class="ce-wrap" id="ce-root">';
try {
echo $view->flash($message, $level);

if ('courses' === $action) {
    echo courseReport($view, $manager);
} elseif ('report' === $action) {
    echo instructorReport($view, $manager);
} elseif ('new' === $action || (int) ($_GET['template_id'] ?? 0) > 0) {
    echo templateEditor($view, $manager);
} else {
    echo templateList($view, $manager);
}
} catch (Throwable $exception) {
    EvaluationView::logFailure($exception);
    echo $view->flash($view->t('UnexpectedError'), 'danger');
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

function handleAdminPost(string $do, EvaluationManager $manager, EvaluationView $view, CourseEvaluationPlugin $plugin): string
{
    return match ($do) {
        'save_template' => templateUrl(saveTemplate($manager, $view)).'&saved=1',
        'delete_template' => deleteTemplate($manager, $view),
        'save_question' => templateUrl(saveAdminQuestion($manager, $view)).'&saved=1',
        'delete_question' => templateUrl(deleteAdminQuestion($manager, $view)).'&deleted=1',
        'move_question' => templateUrl(moveAdminQuestion($manager)),
        'add_to_existing_courses' => 'admin.php?action=templates&tools='.$plugin->addToolToEveryCourse(),
        default => 'admin.php?action=templates',
    };
}

function adminNav(EvaluationView $view, string $action, array $filters): string
{
    $editing = 'new' === $action || (int) ($_GET['template_id'] ?? 0) > 0;
    $reporting = in_array($action, ['courses', 'report'], true);
    $button = $editing || $reporting
        ? null
        : [
            'label' => $view->t('NewTemplate'),
            'url' => 'admin.php?action=new',
            'icon' => 'mdi mdi-plus-box',
            'tone' => 'success',
        ];
    $title = match ($action) {
        'courses' => 'CourseReport',
        'report' => 'InstructorReport',
        default => 'QuestionTemplates',
    };
    $tabs = $editing ? [] : [
        ['label' => $view->t('QuestionTemplates'), 'url' => 'admin.php?action=templates', 'icon' => 'mdi mdi-format-list-bulleted', 'active' => !$reporting],
        ['label' => $view->t('CourseReport'), 'url' => 'admin.php?action=courses', 'icon' => 'mdi mdi-chart-box', 'active' => 'courses' === $action],
        ['label' => $view->t('InstructorReport'), 'url' => 'admin.php?action=report', 'icon' => 'mdi mdi-account', 'active' => 'report' === $action],
    ];

    return $view->listChrome($view->t($title), $tabs, $button);
}

function globalReportFilters(EvaluationView $view, string $action): array
{
    $filters = [
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '',
        'scope' => in_array((string) ($_GET['scope'] ?? ''), ['self_paced', 'session'], true) ? (string) $_GET['scope'] : '',
    ];
    $scope = (string) $filters['scope'];
    $fields = $view->filterField(
        $view->t('ReportType'),
        '<select class="form-select" name="scope">'
        .'<option value="">'.$view->e($view->t('All')).'</option>'
        .'<option value="self_paced"'.('self_paced' === $scope ? ' selected' : '').'>'.$view->e($view->t('SelfPaced')).'</option>'
        .'<option value="session"'.('session' === $scope ? ' selected' : '').'>'.$view->e($view->t('Sessions')).'</option>'
        .'</select>'
    );
    $fields .= $view->filterField($view->t('From'), '<input class="form-control" type="date" name="from" value="'.$view->e($filters['from']).'">');
    $fields .= $view->filterField($view->t('To'), '<input class="form-control" type="date" name="to" value="'.$view->e($filters['to']).'">');
    $open = '' !== $filters['from'] || '' !== $filters['to'] || '' !== $filters['scope'];
    $filters['form'] = '<form method="get" action="admin.php" class="ce-report-filters">'
        .'<input type="hidden" name="action" value="'.$view->e($action).'">'
        .$view->searchPanel($fields, $open)
        .'</form>';

    return $filters;
}

function courseReport(EvaluationView $view, EvaluationManager $manager): string
{
    $filters = globalReportFilters($view, 'courses');

    return '<div id="ce-report">'.$filters['form'].$view->categoryCourseComparison($manager, $filters).'</div>';
}

function instructorReport(EvaluationView $view, EvaluationManager $manager): string
{
    $filters = globalReportFilters($view, 'report');
    $html = '<div id="ce-report">'.$filters['form'];

    $summary = [];
    foreach ($manager->mentorPerformance($filters) as $row) {
        $summary[] = [
            $view->e($manager->userName((int) $row['mentor_id'])),
            $view->e($view->formatAverage($row['average_score'])),
            (string) (int) $row['session_count'],
            (string) (int) $row['self_paced_count'],
        ];
    }
    $html .= '<h3>'.$view->e($view->t('MentorPerformance')).'</h3>'
        .'<p class="text-muted">'.$view->e($view->t('InstructorRoleHelp')).'</p>'
        .$view->dataTable(
            [$view->t('Coach'), $view->t('Average'), $view->t('SessionCoach'), $view->t('SelfPacedMentor')],
            $summary,
            $view->t('NoResponses'),
            2
        );

    $contexts = [];
    foreach ($manager->mentorContexts($filters) as $row) {
        $sessionId = (int) $row['session_id'];
        $contexts[] = [
            $view->e($manager->userName((int) $row['mentor_id'])),
            $view->e($manager->courseTitle((int) $row['course_id'])),
            $view->e($sessionId > 0 ? $manager->sessionTitle($sessionId) : $view->t('SelfPaced')),
            $view->e($view->formatAverage($row['average_score'])),
            (string) (int) $row['response_count'],
        ];
    }
    $html .= '<h3>'.$view->e($view->t('ByInstructor')).'</h3>'
        .'<p class="text-muted">'.$view->e($view->t('AcrossCourses')).'</p>'
        .$view->dataTable(
            [$view->t('Coach'), $view->t('Course'), $view->t('Session'), $view->t('Average'), $view->t('Responses')],
            $contexts,
            $view->t('NoResponses'),
            4
        );

    return $html.'</div>';
}

function templateUrl(int $templateId): string
{
    return 'admin.php?action=templates&template_id='.$templateId;
}

function saveTemplate(EvaluationManager $manager, EvaluationView $view): int
{
    $title = trim((string) ($_POST['title'] ?? ''));
    if ('' === $title) {
        throw new RuntimeException($view->t('MissingRequired'));
    }
    $existing = $manager->findTemplate((int) ($_POST['template_id'] ?? 0));
    if ($existing && Template::SCOPE_GLOBAL !== $existing->getScope()) {
        throw new RuntimeException($view->t('TemplateInUse'));
    }
    $saved = $manager->saveTemplate(
        $existing && Template::SCOPE_GLOBAL === $existing->getScope() ? $existing : null,
        $title,
        trim((string) ($_POST['description'] ?? '')) ?: null,
        !empty($_POST['is_active'])
    );

    return (int) $saved->getId();
}

function deleteTemplate(EvaluationManager $manager, EvaluationView $view): string
{
    $template = globalTemplate($manager, (int) ($_POST['template_id'] ?? 0));
    if ($manager->templateHasSubmissions($template)) {
        throw new RuntimeException($view->t('TemplateInUse'));
    }
    $manager->deleteTemplate($template);

    return 'admin.php?action=templates&deleted=1';
}

function saveAdminQuestion(EvaluationManager $manager, EvaluationView $view): int
{
    $template = globalTemplate($manager, (int) ($_POST['template_id'] ?? 0));
    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    $type = (string) ($_POST['type'] ?? '');
    $category = (string) ($_POST['category'] ?? '');
    if ('' === $prompt || '' === $type || '' === $category) {
        throw new RuntimeException($view->t('MissingRequired'));
    }
    if (\Chamilo\PluginBundle\CourseEvaluation\Entity\Question::TYPE_SCALE === $type && ('' === trim((string) ($_POST['scale_low_label'] ?? '')) || '' === trim((string) ($_POST['scale_high_label'] ?? '')))) {
        throw new RuntimeException($view->t('ScaleLabelsRequired'));
    }
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $question = $questionId > 0 ? $manager->findQuestion($questionId) : null;
    if ($question && $question->getTemplate()->getId() !== $template->getId()) {
        throw new RuntimeException($view->t('NoQuestions'));
    }
    $manager->saveQuestion($template, $question, $_POST);

    return (int) $template->getId();
}

function deleteAdminQuestion(EvaluationManager $manager, EvaluationView $view): int
{
    $question = $manager->findQuestion((int) ($_POST['question_id'] ?? 0));
    if (!$question || Template::SCOPE_GLOBAL !== $question->getTemplate()->getScope()) {
        throw new RuntimeException($view->t('NoQuestions'));
    }
    $templateId = (int) $question->getTemplate()->getId();
    $manager->deleteQuestion($question);

    return $templateId;
}

function moveAdminQuestion(EvaluationManager $manager): int
{
    $question = $manager->findQuestion((int) ($_POST['question_id'] ?? 0));
    if (!$question || Template::SCOPE_GLOBAL !== $question->getTemplate()->getScope()) {
        return (int) ($_POST['template_id'] ?? 0);
    }
    $manager->moveQuestion($question, (int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1);

    return (int) $question->getTemplate()->getId();
}

function globalTemplate(EvaluationManager $manager, int $id): Template
{
    $template = $manager->findTemplate($id);
    if (!$template instanceof Template || Template::SCOPE_GLOBAL !== $template->getScope()) {
        throw new RuntimeException('Template not found.');
    }

    return $template;
}

function templateList(EvaluationView $view, EvaluationManager $manager): string
{
    $rows = [];
    foreach ($manager->globalTemplates() as $template) {
        $rows[] = [
            '<a href="admin.php?action=templates&template_id='.(int) $template->getId().'">'.$view->e($template->getTitle()).'</a>',
            $view->e($template->isActive() ? $view->t('Yes') : $view->t('No')),
            (string) count($manager->questionsFor($template)),
            '<div class="ce-actions">'
            .$view->iconLink($view->t('Edit'), 'admin.php?action=templates&template_id='.(int) $template->getId(), 'mdi mdi-pencil')
            .'<form method="post" onsubmit="return confirm(\''.$view->e($view->t('ConfirmDelete')).'\');">'
            .$view->tokenField()
            .'<input type="hidden" name="do" value="delete_template">'
            .'<input type="hidden" name="template_id" value="'.(int) $template->getId().'">'
            .$view->iconButton($view->t('Delete'), 'mdi mdi-delete', 'danger')
            .'</form></div>',
        ];
    }
    $html = '<div class="rounded border border-info/30 bg-support-1 px-4 py-4 text-support-4" role="status">'
        .'<form method="post" class="flex flex-wrap items-center justify-between gap-3">'
        .$view->tokenField()
        .'<input type="hidden" name="do" value="add_to_existing_courses">'
        .'<span class="text-sm">'.$view->e($view->t('AddToExistingCoursesHelp')).'</span>'
        .'<button class="p-button p-component p-button-sm p-button-text" type="submit">'
        .'<span class="p-button-icon mdi mdi-plus"></span>'
        .'<span class="p-button-label">'.$view->e($view->t('AddToExistingCourses')).'</span>'
        .'</button></form></div>'
        .$view->dataTable(
            [$view->t('Title'), $view->t('Active'), $view->t('Questions'), ''],
            $rows
        );

    return $html;
}

function templateEditor(EvaluationView $view, EvaluationManager $manager): string
{
    $selectedId = (int) ($_GET['template_id'] ?? 0);
    $selected = $selectedId > 0 ? $manager->findTemplate($selectedId) : null;
    if ($selected && Template::SCOPE_GLOBAL !== $selected->getScope()) {
        $selected = null;
    }
    $editingQuestion = null;
    $questionId = (int) ($_GET['question_id'] ?? 0);
    if ($selected && $questionId > 0) {
        $editingQuestion = $manager->findQuestion($questionId);
        if ($editingQuestion && $editingQuestion->getTemplate()->getId() !== $selected->getId()) {
            $editingQuestion = null;
        }
    }

    $html = '<div class="ce-heading-row"><h2>'.$view->e($selected ? $selected->getTitle() : $view->t('NewTemplate')).'</h2>'
        .$view->dialogIcon(
            $selected ? $view->t('Edit') : $view->t('NewTemplate'),
            'ce-header-dialog',
            $selected ? 'mdi mdi-pencil' : 'mdi mdi-plus',
            !$selected
        )
        .'</div>';
    $description = trim((string) ($selected?->getDescription() ?? ''));
    if ('' !== $description) {
        $html .= '<p class="text-muted">'.$view->e($description).'</p>';
    }
    $cancel = $selected
        ? 'this.closest(\'dialog\').close()'
        : 'location.href=\'admin.php?action=templates\'';
    $html .= '<dialog id="ce-header-dialog" class="ce-dialog">'
        .'<form method="post" class="ce-form">'
        .$view->tokenField()
        .'<input type="hidden" name="do" value="save_template">'
        .'<input type="hidden" name="template_id" value="'.(int) ($selected?->getId() ?? 0).'">'
        .'<h3>'.$view->e($selected ? $view->t('Edit') : $view->t('NewTemplate')).'</h3>'
        .'<div class="mb-3"><label class="form-label">'.$view->e($view->t('Title')).'</label>'
        .'<input class="form-control" name="title" required value="'.$view->e($selected?->getTitle() ?? '').'"></div>'
        .'<div class="mb-3"><label class="form-label">'.$view->e($view->t('Description')).'</label>'
        .'<textarea class="form-control" name="description" rows="2">'.$view->e($selected?->getDescription() ?? '').'</textarea></div>'
        .'<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ce-active"'
        .(($selected?->isActive() ?? true) ? ' checked' : '').'>'
        .'<label class="form-check-label" for="ce-active">'.$view->e($view->t('Active')).'</label></div>'
        .'<div class="ce-dialog-actions">'
        .'<button class="p-button p-component p-button-success" type="submit"><span class="p-button-label">'.$view->e($view->t('Save')).'</span></button>'
        .'<button class="p-button p-component p-button-outlined p-button-secondary" type="button" onclick="'.$cancel.'"><span class="p-button-label">'.$view->e($view->t('Cancel')).'</span></button>'
        .'</div></form></dialog>';
    if (!$selected) {
        return $html.'<script>document.getElementById("ce-header-dialog").showModal()</script>';
    }

    if ($selected) {
        $base = 'admin.php?action=templates&template_id='.(int) $selected->getId();
        $html .= '<h2>'.$view->e($view->t('Questions')).'</h2>'
            .'<div class="ce-tool-icons">'.$view->addQuestionButton($base).'</div>'
            .$view->questionForm($editingQuestion, $base, (int) $selected->getId(), null !== $editingQuestion || isset($_GET['add']))
            .$view->questionTable($manager->questionsFor($selected), $base, true);
    }

    return $html;
}

function adminExport(EvaluationManager $manager): void
{
    $filters = [
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '',
        'course_id' => (int) ($_GET['course_id'] ?? 0),
        'session_id' => (int) ($_GET['session_id'] ?? 0),
        'instructor_id' => (int) ($_GET['instructor_id'] ?? 0),
        'category' => (string) ($_GET['category'] ?? ''),
        'filter_session' => '' !== (string) ($_GET['session_id'] ?? ''),
    ];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="course-evaluation-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['instructor_id', 'course_id', 'average', 'responses']);
    foreach ($manager->instructorReport($filters) as $row) {
        fputcsv($out, [$row['instructor_id'], $row['course_id'], $row['average_score'], $row['response_count']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['category', 'prompt', 'average', 'scores', 'course_id', 'instructor_id']);
    foreach ($manager->questionReport($filters) as $row) {
        fputcsv($out, [$row['category'], $row['prompt'], $row['average_score'], $row['score_count'], $row['course_id'], $row['instructor_id']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['comment', 'course_id', 'session_id', 'instructor_id', 'submitted_at']);
    foreach ($manager->improvementComments($filters) as $row) {
        fputcsv($out, [$row['improvement_comment'], $row['course_id'], $row['session_id'], $row['instructor_id'], $row['submitted_at']]);
    }
    fclose($out);
    exit;
}
