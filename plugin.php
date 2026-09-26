<?php

/* For license terms, see /license.txt */

$plugin_info = [
    'title' => 'Course evaluation',
    'comment' => '',
    'version' => '1.1.0',
    'source' => 'third_party',
    'commercial_model' => 'free',
];

try {
    require_once __DIR__.'/src/CourseEvaluationPlugin.php';
    $plugin_info = CourseEvaluationPlugin::create()->get_info();
    $plugin_info['source'] = 'third_party';
    $plugin_info['commercial_model'] = 'free';
} catch (Throwable $exception) {
    $plugin_info['comment'] = $exception->getMessage();
}
