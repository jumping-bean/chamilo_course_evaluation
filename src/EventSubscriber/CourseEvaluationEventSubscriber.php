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

use Chamilo\CoreBundle\Event\AbstractEvent;
use Chamilo\CoreBundle\Event\AdminBlockDisplayedEvent;
use Chamilo\CoreBundle\Event\CourseCreatedEvent;
use Chamilo\CoreBundle\Event\CourseDeletedEvent;
use Chamilo\CoreBundle\Event\Events;
use Chamilo\CoreBundle\Event\SessionDeletedEvent;
use Chamilo\PluginBundle\CourseEvaluation\EvaluationManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CourseEvaluationEventSubscriber implements EventSubscriberInterface
{
    private CourseEvaluationPlugin $plugin;

    public function __construct()
    {
        $this->plugin = CourseEvaluationPlugin::create();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::COURSE_CREATED => 'onCourseCreated',
            Events::COURSE_DELETED => 'onCourseDeleted',
            Events::SESSION_DELETED => 'onSessionDeleted',
            Events::ADMIN_BLOCK_DISPLAYED => 'onAdminBlock',
        ];
    }

    public function onCourseCreated(CourseCreatedEvent $event): void
    {
        if (AbstractEvent::TYPE_POST !== $event->getType() || !$this->plugin->isEnabled()) {
            return;
        }

        $course = $event->getCourse();
        $courseId = is_object($course) && method_exists($course, 'getId') ? (int) $course->getId() : 0;
        if ($courseId <= 0) {
            return;
        }

        $this->plugin->course_install($courseId, true);
    }

    public function onCourseDeleted(CourseDeletedEvent $event): void
    {
        if (AbstractEvent::TYPE_PRE !== $event->getType() || !$this->cleanupAllowed()) {
            return;
        }
        $courseId = (int) $event->getCourseId();
        if ($courseId <= 0) {
            return;
        }
        (new EvaluationManager())->deleteForCourse($courseId);
    }

    public function onAdminBlock(AdminBlockDisplayedEvent $event): void
    {
        if (AbstractEvent::TYPE_POST !== $event->getType() || !$this->plugin->isEnabled()) {
            return;
        }
        if (!function_exists('api_is_session_admin') || !api_is_session_admin() || api_is_platform_admin()) {
            return;
        }
        $event->setItems('sessions', [[
            'class' => 'item-course-evaluation-report',
            'url' => api_get_path(WEB_PLUGIN_PATH).'CourseEvaluation/admin.php?action=courses',
            'label' => $this->plugin->get_lang('CourseReport'),
        ]]);
    }

    public function onSessionDeleted(SessionDeletedEvent $event): void
    {
        if (AbstractEvent::TYPE_PRE !== $event->getType() || !$this->cleanupAllowed()) {
            return;
        }
        $sessionId = (int) $event->getSessionId();
        if ($sessionId <= 0) {
            return;
        }
        (new EvaluationManager())->deleteForSession($sessionId);
    }

    private function cleanupAllowed(): bool
    {
        return class_exists('AppPlugin') && AppPlugin::getInstance()->isInstalled($this->plugin->get_name());
    }
}
