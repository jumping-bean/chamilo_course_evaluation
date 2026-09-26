# Course evaluation plugin for Chamilo 3

Teachers copy a global questionnaire into a course. Each session keeps its own evaluation, and a course opened with no session is treated as self-paced. Learners submit once. Reports group scores by category, question, instructor, and course, and list improvement comments for a date range.

## Install

Copy this directory to `public/plugin/CourseEvaluation` inside a Chamilo 3 installation.

From the Chamilo root:

```bash
composer dump-autoload
php bin/console cache:clear
```

Then install and enable **Course evaluation** in Administration → Plugins. Installing creates the tables, adds the course tool to existing courses, and seeds a standard global template. Courses created later get the tool from the plugin subscriber, which is only registered after the commands above.

`composer dump-autoload` has to finish. If it stops because `public/main/install` is missing, create that empty directory and run it again. The course home loads `CourseEvaluationPlugin` from the Composer classmap. Without that, the tool record exists but no card is drawn.

## What each role does

- **Platform admin** (`admin.php`): create global templates, add or edit questions, and run reports across courses, instructors, and periods. Export CSV from the report page.
- **Teacher or session coach** (course tool): copy a global template into the current course, add questions, and set the evaluation period for the current session. With no session, the same screen opens the self-paced evaluation. The instructor is taken from the session course coach, then the session general coach, then a course teacher. Reports on the course tool stay limited to that course. Optional: require the evaluation for the certificate, which adds a zero-weight assessment item that must be completed before Chamilo issues the certificate.
- **Learner**: fills the open evaluation once. The form always ends with a course improvement comment. Names stay hidden when the evaluation is anonymous.

Question types are a 1–5 rating scale, yes/no, and an open comment. Categories are instructor, course content, delivery, organization, and improvement.

A questionnaire that already has responses cannot have its questions changed. Copy the template again to start a new questionnaire for a later session.

## Uninstall

Disabling the plugin keeps the data. Uninstalling it drops the plugin tables, removes the course tool, and deletes the certificate assessments this plugin added to the gradebook, including their results.

## Database changes in a later release

Chamilo does not migrate these tables. `CourseEvaluationPlugin::update()` does. It runs from `install()` and on the first page load after the new files are copied. The applied version is stored in `plugin_course_evaluation_schema`.

A new table or column is a new step in `schemaSteps()`, for example `'1.2.0' => 'migrate120'`, plus the same version in `CourseEvaluationPlugin::VERSION` and the fallback in `plugin.php`. The step checks that the table or column is missing, then adds it. Changing only the `CREATE TABLE` text leaves an existing database as it is. Version 1.1.0 renames `plugin_course_evaluation_campaign` to `plugin_course_evaluation_evaluation`.
