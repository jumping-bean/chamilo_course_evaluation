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

$strings['plugin_title'] = 'Course evaluation';
$strings['QuestionTemplates'] = 'Course evaluation question templates';
$strings['plugin_comment'] = 'Collect course, instructor, and delivery evaluations for sessions and self-paced courses, then report by period.';
$strings['anonymous_by_default'] = 'Anonymous responses by default';
$strings['allow_delete_completed'] = 'Allow deletion of evaluations that already have responses';
$strings['UnexpectedError'] = 'The course evaluation could not be opened. The details were written to the server log.';
$strings['LearnerNotEnrolled'] = 'Choose a learner enrolled in this course.';
$strings['InstructorNotOnCourse'] = 'Choose an instructor of this course.';
$strings['UseCourseEvaluation'] = 'Use the course evaluation';
$strings['UseCourseEvaluationHelp'] = 'Copy the course evaluation into this session. The question about which instructors were consulted is left out. Session dates are taken from the session.';

$strings['CourseEvaluation'] = 'Course evaluation';
$strings['CourseEvaluationQuestions'] = 'Course evaluation questions';
$strings['CourseEvaluationReport'] = 'Course evaluation report';
$strings['Templates'] = 'Templates';
$strings['GlobalTemplates'] = 'Global templates';
$strings['CourseQuestionnaires'] = 'Course questionnaires';
$strings['CopyToCourse'] = 'Use for this course';
$strings['AddEvaluation'] = 'Add evaluation';
$strings['AddEvaluationHelp'] = 'Choose a questionnaire to copy, then change its questions for this course or session.';
$strings['ChooseTemplate'] = 'Questionnaire';
$strings['ChooseTemplatePlaceholder'] = 'Choose a questionnaire';
$strings['ChooseTemplateHelp'] = 'Choose the global questionnaire to copy. The questions appear after you save, and you can then change them for this course or session.';
$strings['ChooseTemplateFirst'] = 'Choose a questionnaire to copy. The questions appear here after you save.';
$strings['EditEvaluation'] = 'Edit evaluation';
$strings['QuestionsFromTemplate'] = 'These questions come from the template. Save the evaluation, then you can change them for this course or session.';
$strings['ReplaceQuestions'] = 'Replace questions';
$strings['KeepQuestions'] = 'Keep the current questions';
$strings['ReplaceQuestionsHelp'] = 'Choosing a global template replaces these questions. This is available only before a learner submits.';
$strings['QuestionSource'] = 'Questions';
$strings['ReplaceWithTemplate'] = 'Replace with a template';
$strings['QuestionSourceHelp'] = 'Choose one. Keep these questions, replace them with a template, or restore an earlier version. Responses already submitted stay on the version the learner completed.';
$strings['NewTemplate'] = 'New template';
$strings['EditTemplate'] = 'Edit template';
$strings['BackToTemplates'] = 'Back to templates';
$strings['AddToExistingCourses'] = 'Add to existing courses';
$strings['AddToExistingCoursesHelp'] = 'Courses that already exist do not get this tool until you add it. New courses get it when they are created. On the course home, use the eye to show or hide it.';
$strings['AddedToExistingCourses'] = 'Course evaluation was added to %d existing courses.';
$strings['Title'] = 'Title';
$strings['Description'] = 'Description';
$strings['Active'] = 'Active';
$strings['Save'] = 'Save';
$strings['Cancel'] = 'Cancel';
$strings['Edit'] = 'Edit';
$strings['Delete'] = 'Delete';
$strings['Questions'] = 'Questions';
$strings['AddQuestion'] = 'Add question';
$strings['EditQuestion'] = 'Edit question';
$strings['NoQuestions'] = 'This questionnaire has no questions yet.';
$strings['Prompt'] = 'Question';
$strings['Category'] = 'Category';
$strings['Type'] = 'Type';
$strings['Required'] = 'Required';
$strings['HelpText'] = 'Help text';
$strings['Position'] = 'Position';
$strings['ScaleMax'] = 'Scale maximum';
$strings['ScaleLow'] = 'Low label';
$strings['ScaleHigh'] = 'High label';
$strings['Yes'] = 'Yes';
$strings['No'] = 'No';
$strings['ConfirmDelete'] = 'Delete this item?';
$strings['DeleteEvaluation'] = 'Delete evaluation';
$strings['DeleteEvaluationHelp'] = 'Removes this session evaluation, including its questions. The course evaluation is left as it is.';
$strings['ConfirmDeleteEvaluation'] = 'Delete this session evaluation?';
$strings['ConfirmDeleteEvaluationWithResponses'] = 'This evaluation has %count% responses. Deleting it removes those responses and its assessment item. Continue?';
$strings['DeleteCompletedBlocked'] = 'This evaluation already has responses, so it was not deleted. Turn on "Allow deletion of evaluations that already have responses" in the plugin settings to delete it.';
$strings['EvaluationDeleted'] = 'The session evaluation was deleted.';
$strings['Saved'] = 'Saved.';
$strings['Deleted'] = 'Deleted.';
$strings['Copied'] = 'Template copied to this course. You can add questions before opening the evaluation.';
$strings['TemplateInUse'] = 'This questionnaire already has responses, so it was not deleted.';

$strings['category_instructor'] = 'Instructor';
$strings['category_content'] = 'Course content';
$strings['category_delivery'] = 'Delivery';
$strings['category_organization'] = 'Organization';
$strings['category_improvement'] = 'Improvement';

$strings['type_scale'] = 'Rating scale';
$strings['type_yes_no'] = 'Yes / No';
$strings['type_text'] = 'Open comment';
$strings['type_instructor'] = 'Instructor list';
$strings['NoInstructorConsulted'] = 'I did not consult an instructor';

$strings['status_draft'] = 'Draft';
$strings['status_open'] = 'Open';
$strings['status_closed'] = 'Closed';

$strings['ThisContext'] = 'This evaluation context';
$strings['BackToCourse'] = 'Back to course';
$strings['SelfPaced'] = 'Self-paced (no session)';
$strings['ReportType'] = 'Report type';
$strings['Session'] = 'Session';
$strings['Instructor'] = 'Instructor';
$strings['NoInstructor'] = 'No instructor assigned';
$strings['OpenEvaluation'] = 'Evaluation period';
$strings['DateRangeHelp'] = 'Learners in this session can submit only between these dates. After they submit, their answers stay locked.';
$strings['SelfPacedAlwaysOpen'] = 'Self-paced learners can submit at any time. After they submit, their answers stay locked.';
$strings['NoCourseEvaluation'] = 'This course does not have an evaluation yet. Add evaluation asks which questionnaire to copy.';
$strings['NoSessionEvaluation'] = 'This session does not have an evaluation yet. Add evaluation asks which questionnaire to copy.';
$strings['StudentExtension'] = 'Open for one learner';
$strings['StudentExtensionHelp'] = 'After the closing date, you can give one learner a later date. Other learners stay closed. A submitted evaluation stays locked.';
$strings['Learner'] = 'Learner';
$strings['LearnerStatus'] = 'Learners';
$strings['Status'] = 'Status';
$strings['Date'] = 'Date';
$strings['SubmittedStatus'] = 'Submitted';
$strings['NotSubmitted'] = 'Not submitted';
$strings['ExtensionOpen'] = 'Open until';
$strings['NoLearners'] = 'No learners are enrolled in this course or session.';
$strings['AllowUntil'] = 'Allow until';
$strings['ExtensionSaved'] = 'That learner can submit until the date you set.';
$strings['ExtensionRemoved'] = 'The extra date for that learner was removed.';
$strings['DateRangeRequired'] = 'Choose an opening date and a closing date. The closing date must be on or after the opening date.';
$strings['Questionnaire'] = 'Questionnaire';
$strings['OpensAt'] = 'Opens';
$strings['ClosesAt'] = 'Closes';
$strings['AnonymousResponses'] = 'Hide respondent names in reports';
$strings['RequireCertificate'] = 'Add to assessment';
$strings['RequireCertificateHelp'] = 'Adds this evaluation to the assessment for this course or session. Create the assessment in Assessments, before or after this evaluation. The item has no weight, so the grade does not change. Set attendance and other requirements on the assessment, and choose there whether a certificate is issued. If it is, Chamilo waits until the learner submits. You can turn this on or off later.';
$strings['RequireCertificateUnavailable'] = 'Create an assessment for this course or session to enable this. You can do that before or after the evaluation, then come back and turn this on.';
$strings['CertificateNeedsAssessment'] = 'Open Assessments for this course and save the gradebook once, then turn this option on again.';
$strings['CertificateRequiredNotice'] = 'This evaluation is part of your course assessment. If this course issues a certificate, it stays unavailable until you submit.';
$strings['EvaluationOpened'] = 'Saved. Learners can submit during this date range, and they cannot change a submitted evaluation.';
$strings['EvaluationSaved'] = 'Saved. Learners can submit at any time, and they cannot change a submitted evaluation.';
$strings['ChooseQuestionnaire'] = 'Copy a template to this course before opening an evaluation.';
$strings['Submissions'] = 'Submissions';
$strings['AlreadySubmitted'] = 'You have already submitted this evaluation. Thank you.';
$strings['ViewYourEvaluation'] = 'View your evaluation';
$strings['NotOpen'] = 'The evaluation is not available today.';
$strings['FillEvaluation'] = 'Fill out the evaluation';
$strings['StartEvaluation'] = 'Start evaluation';
$strings['ContinueEvaluation'] = 'Continue evaluation';
$strings['WizardPage'] = 'Step %current% of %total%';
$strings['SubmitEvaluation'] = 'Submit evaluation';
$strings['Next'] = 'Next';
$strings['Previous'] = 'Previous';
$strings['Submitted'] = 'Your evaluation was submitted. Thank you.';
$strings['MissingRequired'] = 'Please answer every required question.';
$strings['ScaleLabelsRequired'] = 'A rating scale needs a low label and a high label.';
$strings['YesLabel'] = 'Yes label';
$strings['NoLabel'] = 'No label';
$strings['ImprovementComments'] = 'Course improvement comments';
$strings['ImprovementPrompt'] = 'What should change so the next group has a better course?';
$strings['Reports'] = 'Reports';
$strings['ViewResponses'] = 'View responses';
$strings['NoResponses'] = 'No learners have submitted this evaluation yet.';
$strings['Answers'] = 'Answers';
$strings['Version'] = 'Version';
$strings['Anonymous'] = 'Anonymous';
$strings['ByCategory'] = 'By category';
$strings['BySection'] = 'By section';
$strings['BySession'] = 'Break down by session';
$strings['CourseResults'] = 'Course results';
$strings['MentorPerformance'] = 'Mentor performance';
$strings['InstructorReport'] = 'Instructor report';
$strings['CourseReport'] = 'Course report';
$strings['AcrossCourses'] = 'Each row is one course or session. The summary above combines them.';
$strings['CourseComparison'] = 'Course comparison';
$strings['CourseComparisonHelp'] = 'Each course is compared with the average of all courses in that category.';
$strings['OverallAverage'] = 'Overall average';
$strings['Difference'] = 'Difference';
$strings['AboveAverage'] = 'Above average';
$strings['BelowAverage'] = 'Below average';
$strings['AtAverage'] = 'At average';
$strings['Sessions'] = 'Sessions';
$strings['Session'] = 'Session';
$strings['Coach'] = 'Coach';
$strings['Named'] = 'Named';
$strings['SessionCoach'] = 'Session coach';
$strings['SelfPacedMentor'] = 'Self-paced mentor';
$strings['InstructorRoleHelp'] = 'Session coach counts evaluations where this person led the session. Self-paced mentor counts evaluations where a learner said they consulted this person.';
$strings['LastAnswered'] = 'Last answered';
$strings['Section'] = 'Section';
$strings['EarlierVersion'] = 'Earlier version';
$strings['EarlierVersionHelp'] = 'Choosing an earlier version makes that set of questions the current questionnaire. Responses already submitted stay on the version the learner completed.';
$strings['SaveChanges'] = 'Save changes';
$strings['DiscardChanges'] = 'Discard changes';
$strings['QuestionBatchHelp'] = 'Added and removed questions stay on this version until you save. Saving once counts as one new version.';
$strings['QuestionBatchSaved'] = 'Saved. Added and removed questions are now one new version.';
$strings['QuestionBatchDiscarded'] = 'Those question changes were discarded.';
$strings['ByQuestion'] = 'By question';
$strings['ByInstructor'] = 'By instructor and course';
$strings['Average'] = 'Average';
$strings['Scores'] = 'Scores';
$strings['Responses'] = 'Responses';
$strings['From'] = 'From';
$strings['To'] = 'To';
$strings['Filter'] = 'Filter';
$strings['Search'] = 'Search';
$strings['AdvancedSearch'] = 'Advanced search';
$strings['All'] = 'All';
$strings['Course'] = 'Course';
$strings['CourseId'] = 'Course id';
$strings['SessionId'] = 'Session id';
$strings['InstructorId'] = 'Instructor id';
$strings['ExportCsv'] = 'Export CSV';
$strings['NoComments'] = 'No improvement comments for this filter.';
$strings['StudentsOnly'] = 'Only enrolled learners can submit an evaluation.';
$strings['SwitchToStudentView'] = 'Switch to student view';
$strings['SwitchToTeacherView'] = 'Switch to teacher view';
$strings['StudentViewPreview'] = 'This is the learner questionnaire. Responses are not saved while student view is on.';
$strings['ManageQuestions'] = 'Manage questions';
$strings['EvaluationDetails'] = 'Evaluation details';
$strings['Back'] = 'Back';
$strings['MoveUp'] = 'Move up';
$strings['MoveDown'] = 'Move down';
$strings['CurrentStatus'] = 'Status';
$strings['LockedQuestionnaire'] = 'Responses already exist. Add questions on a new copy if you need a different questionnaire.';
