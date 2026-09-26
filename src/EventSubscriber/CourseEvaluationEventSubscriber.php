<?php

declare(strict_types=1);

use Chamilo\CoreBundle\Event\AbstractEvent;
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
