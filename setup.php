<?php

declare(strict_types=1);

require_once __DIR__.'/../../main/inc/global.inc.php';
require_once __DIR__.'/src/CourseEvaluationPlugin.php';
require_once __DIR__.'/src/EvaluationView.php';

use Chamilo\CoreBundle\Entity\Plugin as PluginEntity;
use Chamilo\CoreBundle\Framework\Container;

api_protect_admin_script(true);

$error = '';
$done = false;
$toolMessage = '';
$action = (string) ($_POST['do'] ?? '');

if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET') && 'install' === $action) {
    if (class_exists('Security') && !Security::check_token('post')) {
        $error = 'The form token expired. Submit the form again.';
    } else {
        try {
            CourseEvaluationPlugin::create()->install();

            $em = Database::getManager();
            $repository = $em->getRepository(PluginEntity::class);
            $pluginEntity = $repository->findOneBy(['title' => 'CourseEvaluation']);
            if (!$pluginEntity instanceof PluginEntity) {
                $pluginEntity = new PluginEntity();
            }

            $pluginEntity
                ->setTitle('CourseEvaluation')
                ->setInstalled(true)
                ->setInstalledVersion(CourseEvaluationPlugin::VERSION)
                ->setSource(PluginEntity::SOURCE_THIRD_PARTY)
                ->enable(Container::getAccessUrlUtil()->getCurrent());

            $em->persist($pluginEntity);
            $em->flush();
            $done = true;
        } catch (Throwable $exception) {
            \Chamilo\PluginBundle\CourseEvaluation\EvaluationView::logFailure($exception);
            $error = 'The course evaluation could not be installed. The details were written to the server log.';
        }
        if (class_exists('Security')) {
            Security::clear_token();
        }
    }
}

if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET') && 'add_tool' === $action) {
    if (class_exists('Security') && !Security::check_token('post')) {
        $error = 'The form token expired. Submit the form again.';
    } else {
        try {
            CourseEvaluationPlugin::create()->install_course_fields_in_all_courses(true);
            $toolMessage = 'The Course evaluation tool was added to every course on this site.';
        } catch (Throwable $exception) {
            \Chamilo\PluginBundle\CourseEvaluation\EvaluationView::logFailure($exception);
            $error = 'The course evaluation could not be installed. The details were written to the server log.';
        }
        if (class_exists('Security')) {
            Security::clear_token();
        }
    }
}

$token = class_exists('Security') ? Security::get_token() : '';

Display::display_header('Course evaluation setup');
echo '<div style="max-width:40rem;font-family:sans-serif;">';
echo '<h1>Course evaluation setup</h1>';

if ('' !== $error) {
    echo '<pre style="padding:1rem;background:#fdecec;border:1px solid #a32020;white-space:pre-wrap;">'
        .htmlspecialchars($error, ENT_QUOTES, 'UTF-8')
        .'</pre>';
}
if ($done) {
    echo '<div style="padding:1rem;background:#e8f6ee;border:1px solid #1f7a3a;">The plugin is installed and enabled.</div>';
}
if ('' !== $toolMessage) {
    echo '<div style="padding:1rem;background:#e8f6ee;border:1px solid #1f7a3a;">'
        .htmlspecialchars($toolMessage, ENT_QUOTES, 'UTF-8')
        .'</div>';
}

echo '<p style="margin-top:1rem;"><a href="admin.php">Edit the global course evaluation templates</a></p>';

echo '</div>';
Display::display_footer();
