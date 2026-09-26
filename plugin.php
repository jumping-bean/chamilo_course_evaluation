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
