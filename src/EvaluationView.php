<?php

declare(strict_types=1);

namespace Chamilo\PluginBundle\CourseEvaluation;

use Chamilo\PluginBundle\CourseEvaluation\Entity\Evaluation;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Question;
use Chamilo\PluginBundle\CourseEvaluation\Entity\Template;

class EvaluationView
{
    public function __construct(private \CourseEvaluationPlugin $plugin)
    {
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public function t(string $key): string
    {
        return (string) $this->plugin->get_lang($key);
    }

    public function anonymousByDefault(): bool
    {
        $value = $this->plugin->get('anonymous_by_default');
        if (null === $value || '' === $value) {
            return true;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function categoryLabel(string $category): string
    {
        return $this->t('category_'.$category);
    }

    public function typeLabel(string $type): string
    {
        return $this->t('type_'.$type);
    }

    public function statusLabel(string $status): string
    {
        return $this->t('status_'.$status);
    }

    public function styles(): string
    {
        $href = api_get_path(WEB_PLUGIN_PATH).'CourseEvaluation/resources/css/course_evaluation.css?v=35';

        return '<link rel="stylesheet" href="'.$this->e($href).'">'
            .'<script>
(function(){if(window.ceLiveFilters){return;}window.ceLiveFilters=true;var request,timer;
function ceRegion(){if(document.getElementById("ce-screen")){return "#ce-screen";}if(document.getElementById("ce-page")){return "#ce-page";}return "#ce-report";}
function ceSameTool(url){var next=new URL(url,location.href);if(next.searchParams.get("export")==="csv"||!/start\\.php$/.test(next.pathname)){return false;}var current=new URL(location.href);var sid=function(params){return params.get("sid")||"0";};return next.searchParams.get("cid")===current.searchParams.get("cid")&&sid(next.searchParams)===sid(current.searchParams);}
function ceCrumbs(html){var match=html.match(/window\\.breadcrumb\\s*=\\s*(\\[[\\s\\S]*?\\])\\s*;/);if(!match){return;}var legacy;try{legacy=JSON.parse(match[1]);}catch(e){return;}if(!legacy.length){return;}var list=document.querySelector(".app-breadcrumb ol");if(!list){return;}var items=[],separator=null;Array.prototype.forEach.call(list.children,function(li){if(li.className.indexOf("separator")!==-1){if(!separator){separator=li;}return;}items.push(li);});if(!items.length){return;}var labelOf=function(node){return (node.textContent||"").replace(/\\s+/g," ").trim();};var keepRoot=labelOf(items[0])!==String(legacy[0].name||"").trim();var linkSample=null,currentSample=items[items.length-1];items.forEach(function(li,index){if(!linkSample&&index>0&&li.querySelector("a")){linkSample=li;}});if(!linkSample){items.forEach(function(li){if(!linkSample&&li.querySelector("a")){linkSample=li;}});}if(!separator){separator=document.createElement("li");separator.className="p-breadcrumb-separator";separator.textContent=" /";}var root=keepRoot?items[0]:null;list.textContent="";if(root){list.appendChild(root);}legacy.forEach(function(crumb,index){if(index||root){list.appendChild(separator.cloneNode(true));}var last=index===legacy.length-1;var sample=last?currentSample:(linkSample||currentSample);var li=sample.cloneNode(true);var textNode=li.querySelector("a, span")||li;textNode.textContent=crumb.name||"";if(!last&&crumb.url&&crumb.url!=="#"){var anchor=li.querySelector("a");if(anchor){anchor.setAttribute("href",crumb.url);}}list.appendChild(li);});}
function ceWizardShow(form,index){var steps=form.querySelectorAll(".ce-step");steps.forEach(function(step){step.classList.toggle("ce-step-hidden",step.getAttribute("data-ce-step")!==String(index));});form.querySelectorAll(".ce-wizard-nav li").forEach(function(item){var on=item.getAttribute("data-ce-step")===String(index);item.classList.toggle("ce-wizard-nav-current",on);if(on){item.setAttribute("aria-current","step");}else{item.removeAttribute("aria-current");}});var progress=form.querySelector(".ce-wizard-progress");if(progress){var label=progress.getAttribute("data-ce-label")||"Step %current% of %total%";progress.textContent=label.replace("%current%",index).replace("%total%",steps.length);}form.setAttribute("data-ce-index",String(index));}
function ceInitWizard(root){(root||document).querySelectorAll(".ce-wizard").forEach(function(form){if(form.getAttribute("data-ce-ready")){return;}form.setAttribute("data-ce-ready","1");var start=1;var steps=form.querySelectorAll(".ce-step");for(var s=0;s<steps.length;s++){var fields=steps[s].querySelectorAll("input,select,textarea");var invalid=false;for(var i=0;i<fields.length;i++){if(!fields[i].checkValidity()){invalid=true;break;}}if(invalid){start=Number(steps[s].getAttribute("data-ce-step"));break;}}ceWizardShow(form,start);form.addEventListener("click",function(event){var next=event.target.closest("[data-ce-next]");var prev=event.target.closest("[data-ce-prev]");if(!next&&!prev){return;}var index=Number(form.getAttribute("data-ce-index")||1);if(next){var current=form.querySelector(".ce-step[data-ce-step=\'"+index+"\']");var fields=current?current.querySelectorAll("input,select,textarea"):[];for(var i=0;i<fields.length;i++){if(!fields[i].checkValidity()){fields[i].reportValidity();return;}}ceWizardShow(form,index+1);}else{ceWizardShow(form,Math.max(1,index-1));}});});}
function ceLoad(url,selector,push){var current=document.querySelector(selector);if(!current){location.href=url;return;}current.classList.add("ce-loading");current.setAttribute("aria-busy","true");if(request){request.abort();}request=new AbortController();fetch(url,{credentials:"same-origin",signal:request.signal}).then(function(response){return response.text();}).then(function(html){var doc=new DOMParser().parseFromString(html,"text/html");var next=doc.querySelector(selector);var node=document.querySelector(selector);if(!next||!node){location.href=url;return;}node.replaceWith(document.importNode(next,true));if(push){history.pushState({ce:selector},"",url);}if(doc.title){document.title=doc.title;}var exportLink=document.querySelector("[data-ce-export]");if(exportLink){var exportUrl=new URL(url,location.href);exportUrl.searchParams.set("export","csv");exportLink.href=exportUrl;}ceCrumbs(html);ceInitWizard(document);}).catch(function(error){if(error&&error.name==="AbortError"){return;}location.href=url;});}
document.addEventListener("click",function(event){var toggle=event.target&&event.target.closest?event.target.closest("[data-ce-search-toggle]"):null;if(!toggle){return;}var panel=document.getElementById("ce-search-panel");if(!panel){return;}event.preventDefault();var open=panel.hasAttribute("hidden");if(open){panel.removeAttribute("hidden");}else{panel.setAttribute("hidden","");}toggle.setAttribute("aria-expanded",open?"true":"false");var icon=toggle.querySelector(".mdi");if(icon){icon.classList.toggle("mdi-chevron-down",!open);icon.classList.toggle("mdi-chevron-up",open);}});
document.addEventListener("submit",function(event){var form=event.target;if(!form||!form.classList||!form.classList.contains("ce-report-filters")){return;}event.preventDefault();clearTimeout(timer);var params=new URLSearchParams(new FormData(form));var url=new URL(form.getAttribute("action")||location.pathname,location.href);url.search=params.toString();ceLoad(url,"#ce-report",true);});
document.addEventListener("click",function(event){var link=event.target&&event.target.closest?event.target.closest("a"):null;if(!link||event.button||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey){return;}var inReport=link.closest(".ce-report-nav");var inAdmin=link.closest(".ce-admin-tabs");var inPage=link.closest("#ce-page")&&ceSameTool(link.href);if(!inReport&&!inAdmin&&!inPage){return;}if(link.classList.contains("ce-menu-current")){event.preventDefault();return;}if(inReport){event.preventDefault();ceLoad(link.href,"#ce-report",true);return;}if(inAdmin){event.preventDefault();ceLoad(link.href,"#ce-screen",true);return;}event.preventDefault();ceLoad(link.href,"#ce-page",true);});
window.addEventListener("popstate",function(){var selector=history.state&&history.state.ce;if(!selector||!document.querySelector(selector)){selector=ceRegion();}if(!document.querySelector(selector)){return;}ceLoad(location.href,selector,false);});
document.addEventListener("DOMContentLoaded",function(){ceInitWizard(document);});
})();
</script>';
    }

    public function courseToolbar(?string $url = null): string
    {
        if (!class_exists('Display') || !function_exists('api_get_course_url')) {
            return '';
        }
        $label = null === $url ? $this->t('BackToCourse') : $this->t('Back');
        $icon = \Display::getMdiIcon('arrow-left-bold-box', 'ch-tool-icon', null, ICON_SIZE_MEDIUM, $label);

        return \Display::toolbarAction('ce-toolbar', [
            \Display::url($icon, $url ?? api_get_course_url(), ['title' => $label]),
        ]);
    }

    public function dialogIcon(string $label, string $dialogId, string $iconClass, bool $primary): string
    {
        $tone = $primary ? ' p-button-success' : ' p-button-secondary p-button-text';

        return '<button type="button" class="p-button p-component p-button-icon-only'.$tone.'" onclick="document.getElementById(\''.$this->e($dialogId).'\').showModal()" title="'.$this->e($label).'" aria-label="'.$this->e($label).'">'
            .'<span class="p-button-icon '.$this->e($iconClass).'"></span></button>';
    }

    public function toolIcon(string $label, string $url, string $iconClass, bool $primary): string
    {
        $tone = $primary ? ' p-button-success' : ' p-button-secondary p-button-text';

        return '<a class="p-button p-component p-button-icon-only'.$tone.'" href="'.$this->e($url).'" title="'.$this->e($label).'" aria-label="'.$this->e($label).'">'
            .'<span class="p-button-icon '.$this->e($iconClass).'"></span></a>';
    }

    public static function logFailure(\Throwable $exception): void
    {
        $line = sprintf(
            "[%s] %s in %s:%d\n",
            date('c'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        $path = dirname(__DIR__, 4).'/var/log/course_evaluation.log';
        @file_put_contents($path, $line, FILE_APPEND);
    }

    public static function renderFailure(\Throwable $exception): never
    {
        self::logFailure($exception);
        $message = 'The course evaluation could not be opened. The details were written to the server log.';
        if (class_exists(\CourseEvaluationPlugin::class)) {
            $translated = \CourseEvaluationPlugin::create()->get_lang('UnexpectedError');
            if (is_string($translated) && '' !== $translated && 'UnexpectedError' !== $translated) {
                $message = $translated;
            }
        }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;margin:2rem;">'
            .'<h1>Course evaluation</h1>'
            .'<p style="padding:1rem;background:#fdecec;border:1px solid #a32020;">'
            .$message
            .'</p></body></html>';
        exit;
    }

    public function flash(?string $message, string $level = 'success'): string
    {
        if (null === $message || '' === $message) {
            return '';
        }

        return '<div class="alert alert-'.$this->e($level).'">'.$this->e($message).'</div>';
    }

    public function tokenField(): string
    {
        if (class_exists('Security') && method_exists('Security', 'get_existing_token')) {
            $token = \Security::get_existing_token();
        } elseif (class_exists('Security')) {
            $token = \Security::get_token();
        } else {
            $token = '';
        }

        return '<input type="hidden" name="sec_token" value="'.$this->e($token).'">';
    }

    public function courseUrl(string $action, array $extra = []): string
    {
        $params = array_merge([
            'action' => $action,
            'cid' => (int) api_get_course_int_id(),
            'sid' => (int) api_get_session_id(),
            'gid' => function_exists('api_get_group_id') ? (int) api_get_group_id() : 0,
            'cidReq' => api_get_course_id(),
            'id_session' => (int) api_get_session_id(),
        ], $extra);

        return 'start.php?'.http_build_query($params);
    }

    /**
     * Same chrome as the administration course list: title, one action, underline tabs.
     *
     * @param list<array{label: string, url: string, active?: bool}> $tabs
     * @param array{label: string, url: string, icon?: string, tone?: string}|null $action
     */
    public function listChrome(string $title, array $tabs, ?array $action = null): string
    {
        $html = '<div class="ce-admin-chrome">'
            .'<h2 class="text-2xl font-semibold text-gray-800">'.$this->e($title).'</h2>';
        if (count($tabs) > 1 || $action) {
            $end = [];
            if ($action) {
                $end[] = [
                    'label' => $action['label'],
                    'url' => $action['url'],
                    'icon' => $action['icon'] ?? 'mdi mdi-plus-box',
                    'primary' => true,
                ];
            }
            $html .= $this->iconMenu('ce-admin-tabs', $tabs, $end);
        }

        return $html.'</div>';
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows cell HTML, already escaped where it is text
     */
    public function dataTable(array $headers, array $rows, string $empty = '', int $numericFrom = 0): string
    {
        $numeric = $numericFrom > 1 ? ' ce-num-'.$numericFrom : '';
        $html = '<div class="p-datatable p-component p-datatable-striped p-datatable-sm'.$numeric.'">'
            .'<div class="p-datatable-table-container">'
            .'<table class="p-datatable-table"><thead class="p-datatable-thead"><tr>';
        foreach ($headers as $header) {
            $html .= '<th><div class="p-datatable-column-header-content"><span class="p-datatable-column-title">'
                .$this->e($header).'</span></div></th>';
        }
        $html .= '</tr></thead><tbody class="p-datatable-tbody">';
        if (!$rows) {
            $html .= '<tr><td colspan="'.max(1, count($headers)).'">'.$this->e($empty).'</td></tr>';
        }
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.$cell.'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table></div></div>';
    }

    public function iconLink(string $label, string $url, string $iconClass): string
    {
        return '<a class="p-button p-component p-button-sm p-button-text p-button-secondary p-button-icon-only" href="'
            .$this->e($url).'" title="'.$this->e($label).'" aria-label="'.$this->e($label).'">'
            .'<span class="p-button-icon '.$this->e($iconClass).'"></span></a>';
    }

    public function iconButton(string $label, string $iconClass, string $tone = 'secondary'): string
    {
        $toneClass = 'danger' === $tone ? ' p-button-danger' : ' p-button-secondary';

        return '<button class="p-button p-component p-button-sm p-button-text p-button-icon-only'.$toneClass.'" type="submit" title="'.$this->e($label).'" aria-label="'.$this->e($label).'">'
            .'<span class="p-button-icon '.$this->e($iconClass).'"></span></button>';
    }

    /**
     * @param Question[] $questions
     */
    public function questionTable(array $questions, string $baseUrl, bool $canEdit): string
    {
        if (!$questions) {
            return '<p class="text-muted">'.$this->e($this->t('NoQuestions')).'</p>';
        }

        $headers = [
            $this->t('Position'),
            $this->t('Category'),
            $this->t('Type'),
            $this->t('Prompt'),
            $this->t('Required'),
        ];
        if ($canEdit) {
            $headers[] = '';
        }
        $rows = [];
        foreach ($questions as $question) {
            $row = [
                (string) (int) $question->getPosition(),
                $this->e($this->categoryLabel($question->getCategory())),
                $this->e($this->typeLabel($question->getType())),
                $this->e($question->getPrompt()),
                $this->e($question->isRequired() ? $this->t('Yes') : $this->t('No')),
            ];
            if ($canEdit) {
                $id = (int) $question->getId();
                $row[] = '<div class="ce-actions">'
                    .$this->iconLink($this->t('Edit'), $baseUrl.'&question_id='.$id, 'mdi mdi-pencil')
                    .'<form method="post" action="'.$this->e($baseUrl).'">'
                    .$this->tokenField()
                    .'<input type="hidden" name="do" value="move_question">'
                    .'<input type="hidden" name="question_id" value="'.$id.'">'
                    .'<input type="hidden" name="direction" value="-1">'
                    .$this->iconButton($this->t('MoveUp'), 'mdi mdi-arrow-up', 'secondary')
                    .'</form>'
                    .'<form method="post" action="'.$this->e($baseUrl).'">'
                    .$this->tokenField()
                    .'<input type="hidden" name="do" value="move_question">'
                    .'<input type="hidden" name="question_id" value="'.$id.'">'
                    .'<input type="hidden" name="direction" value="1">'
                    .$this->iconButton($this->t('MoveDown'), 'mdi mdi-arrow-down', 'secondary')
                    .'</form>'
                    .'<form method="post" action="'.$this->e($baseUrl).'" onsubmit="return confirm(\''.$this->e($this->t('ConfirmDelete')).'\');">'
                    .$this->tokenField()
                    .'<input type="hidden" name="do" value="delete_question">'
                    .'<input type="hidden" name="question_id" value="'.$id.'">'
                    .$this->iconButton($this->t('Delete'), 'mdi mdi-delete', 'danger')
                    .'</form></div>';
            }
            $rows[] = $row;
        }

        return $this->dataTable($headers, $rows);
    }

    public function questionForm(?Question $question, string $action, int $templateId, bool $open = false): string
    {
        $type = $question?->getType() ?? Question::TYPE_SCALE;
        $category = $question?->getCategory() ?? Question::CATEGORY_CONTENT;
        $html = '<dialog id="ce-question-dialog" class="ce-dialog">'
            .'<form method="post" action="'.$this->e($action).'" class="ce-form" onsubmit="return ceSaveQuestion(this)">'
            .$this->tokenField()
            .'<input type="hidden" name="do" value="save_question">'
            .'<input type="hidden" name="template_id" value="'.(int) $templateId.'">'
            .'<input type="hidden" name="question_id" value="'.(int) ($question?->getId() ?? 0).'">'
            .'<h3>'.$this->e($question ? $this->t('EditQuestion') : $this->t('AddQuestion')).'</h3>'
            .'<div class="mb-3"><label class="form-label">'.$this->e($this->t('Prompt')).' *</label>'
            .'<textarea class="form-control" name="prompt" required rows="2">'.$this->e($question?->getPrompt() ?? '').'</textarea></div>'
            .'<div class="row">'
            .'<div class="col-md-4 mb-3"><label class="form-label">'.$this->e($this->t('Category')).' *</label>'
            .'<select class="form-select" name="category" required>';
        foreach ([
            Question::CATEGORY_INSTRUCTOR,
            Question::CATEGORY_CONTENT,
            Question::CATEGORY_DELIVERY,
            Question::CATEGORY_ORGANIZATION,
            Question::CATEGORY_IMPROVEMENT,
        ] as $value) {
            $selected = $value === $category ? ' selected' : '';
            $html .= '<option value="'.$this->e($value).'"'.$selected.'>'.$this->e($this->categoryLabel($value)).'</option>';
        }
        $html .= '</select></div>'
            .'<div class="col-md-4 mb-3"><label class="form-label">'.$this->e($this->t('Type')).' *</label>'
            .'<select class="form-select" name="type" required onchange="ceQuestionType(this)">';
        foreach ([Question::TYPE_SCALE, Question::TYPE_YES_NO, Question::TYPE_TEXT, Question::TYPE_INSTRUCTOR] as $value) {
            $selected = $value === $type ? ' selected' : '';
            $html .= '<option value="'.$this->e($value).'"'.$selected.'>'.$this->e($this->typeLabel($value)).'</option>';
        }
        $html .= '</select></div>'
            .'<div class="col-md-4 mb-3" data-ce-for="scale"><label class="form-label">'.$this->e($this->t('ScaleMax')).'</label>'
            .'<input class="form-control" type="number" min="2" max="10" name="scale_max" value="'.(int) ($question?->getScaleMax() ?? 5).'"></div>'
            .'</div>'
            .'<div class="row" data-ce-for="scale" data-ce-required="1">'
            .'<div class="col-md-6 mb-3"><label class="form-label">'.$this->e($this->t('ScaleLow')).' *</label>'
            .'<input class="form-control" name="scale_low_label" value="'.$this->e($question?->getScaleLowLabel() ?? '').'"></div>'
            .'<div class="col-md-6 mb-3"><label class="form-label">'.$this->e($this->t('ScaleHigh')).' *</label>'
            .'<input class="form-control" name="scale_high_label" value="'.$this->e($question?->getScaleHighLabel() ?? '').'"></div>'
            .'</div>'
            .'<div class="row" data-ce-for="yes_no">'
            .'<div class="col-md-6 mb-3"><label class="form-label">'.$this->e($this->t('YesLabel')).'</label>'
            .'<input class="form-control" name="yes_label" placeholder="'.$this->e($this->t('Yes')).'" value="'.$this->e(Question::TYPE_YES_NO === $type ? (string) $question?->getScaleHighLabel() : '').'"></div>'
            .'<div class="col-md-6 mb-3"><label class="form-label">'.$this->e($this->t('NoLabel')).'</label>'
            .'<input class="form-control" name="no_label" placeholder="'.$this->e($this->t('No')).'" value="'.$this->e(Question::TYPE_YES_NO === $type ? (string) $question?->getScaleLowLabel() : '').'"></div>'
            .'</div>'
            .'<div class="mb-3"><label class="form-label">'.$this->e($this->t('HelpText')).'</label>'
            .'<input class="form-control" name="help_text" value="'.$this->e($question?->getHelpText() ?? '').'"></div>'
            .'<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="required" value="1" id="ce-required"'
            .(($question?->isRequired() ?? true) ? ' checked' : '').'>'
            .'<label class="form-check-label" for="ce-required">'.$this->e($this->t('Required')).'</label></div>'
            .'<div class="ce-dialog-actions">'
            .'<button class="p-button p-component p-button-success" type="submit"><span class="p-button-label">'.$this->e($this->t('Save')).'</span></button>'
            .'<button class="p-button p-component p-button-outlined p-button-secondary" type="button" onclick="ceCloseQuestion(this)"><span class="p-button-label">'.$this->e($this->t('Cancel')).'</span></button>'
            .'</div></form></dialog>'
            .'<script>
function ceQuestionType(select){if(!select||!select.form){return;}var type=select.value;select.form.querySelectorAll("[data-ce-for]").forEach(function(el){var show=el.getAttribute("data-ce-for")===type;el.hidden=!show;el.querySelectorAll("input,select,textarea").forEach(function(input){input.disabled=!show;input.required=show&&el.getAttribute("data-ce-required")==="1";});});}
function ceCloseQuestion(button){button.closest("dialog").close();var url=new URL(location.href);url.searchParams.delete("add");url.searchParams.delete("question_id");history.replaceState(null,"",url);}
function ceSaveQuestion(form){var data=new FormData(form);fetch(form.action,{method:"POST",body:data,credentials:"same-origin"}).then(function(response){return response.text();}).then(function(html){var next=new DOMParser().parseFromString(html,"text/html").querySelector("#ce-root");var current=document.querySelector("#ce-root");if(!next||!current){form.submit();return;}current.replaceWith(next);var url=new URL(form.action,location.href);url.searchParams.delete("add");url.searchParams.delete("question_id");history.replaceState(null,"",url);var typeSelect=document.querySelector("#ce-question-dialog select[name=type]");if(typeSelect){ceQuestionType(typeSelect);}var dialog=document.getElementById("ce-question-dialog");if(dialog&&html.indexOf("alert-danger")!==-1){dialog.showModal();}}).catch(function(){form.submit();});return false;}
ceQuestionType(document.querySelector("#ce-question-dialog select[name=type]"));
</script>'
            .($open ? '<script>document.getElementById("ce-question-dialog").showModal()</script>' : '');

        return $html;
    }

    public function addQuestionButton(string $url): string
    {
        $href = $url.(str_contains($url, '?') ? '&' : '?').'add=1';

        return '<a class="p-button p-component p-button-success" href="'.$this->e($href).'">'
            .'<span class="p-button-icon mdi mdi-plus"></span>'
            .'<span class="p-button-label">'.$this->e($this->t('AddQuestion')).'</span></a>';
    }

    /**
     * @param Question[] $questions
     */
    /**
     * @param list<array{id: int, name: string}> $instructors
     * @param Question[] $questions
     */
    public function studentForm(Evaluation $evaluation, array $questions, string $action, array $instructors = []): string
    {
        $steps = [];
        foreach ($questions as $question) {
            $steps[$question->getCategory()][] = $question;
        }
        if (!isset($steps['improvement'])) {
            $steps['improvement'] = [];
        }
        $total = count($steps);
        $index = 0;
        $comment = (string) ($_POST['improvement_comment'] ?? '');
        $progress = str_replace(['%current%', '%total%'], ['1', (string) $total], $this->t('WizardPage'));
        $nav = '';
        $sections = '';
        foreach ($steps as $category => $group) {
            ++$index;
            $title = 'improvement' === $category
                ? $this->t('ImprovementComments')
                : $this->categoryLabel((string) $category);
            $nav .= '<li data-ce-step="'.$index.'"'.(1 === $index ? ' class="ce-wizard-nav-current" aria-current="step"' : '').'>'
                .'<span>'.$index.'</span>'.$this->e($title).'</li>';
            $sections .= '<section class="ce-step'.(1 === $index ? '' : ' ce-step-hidden').'" data-ce-step="'.$index.'">'
                .'<h3>'.$this->e($title).'</h3>';
            foreach ($group as $question) {
                $sections .= $this->questionInput($question, $instructors);
            }
            if ('improvement' === $category) {
                $sections .= '<div class="mb-3"><label class="form-label">'.$this->e($this->t('ImprovementPrompt')).'</label>'
                    .'<textarea class="form-control" name="improvement_comment" rows="4">'.$this->e($comment).'</textarea></div>';
            }
            $sections .= '<div class="ce-wizard-actions">';
            if ($index > 1) {
                $sections .= '<button class="p-button p-component p-button-outlined p-button-secondary" type="button" data-ce-prev><span class="p-button-label">'.$this->e($this->t('Previous')).'</span></button>';
            }
            if ($index < $total) {
                $sections .= '<button class="p-button p-component" type="button" data-ce-next><span class="p-button-label">'.$this->e($this->t('Next')).'</span></button>';
            } else {
                $sections .= '<button class="p-button p-component p-button-success" type="submit"><span class="p-button-label">'.$this->e($this->t('SubmitEvaluation')).'</span></button>';
            }
            $sections .= '</div></section>';
        }

        return '<form method="post" action="'.$this->e($action).'" class="ce-form ce-wizard">'
            .$this->tokenField()
            .'<input type="hidden" name="do" value="submit_evaluation">'
            .'<p class="ce-wizard-progress" data-ce-label="'.$this->e($this->t('WizardPage')).'" aria-live="polite">'.$this->e($progress).'</p>'
            .'<div class="ce-wizard-layout">'
            .'<ol class="ce-wizard-nav">'.$nav.'</ol>'
            .'<div class="ce-wizard-main">'.$sections.'</div>'
            .'</div></form>';
    }

    /**
     * @param list<array{id: int, name: string}> $instructors
     */
    private function questionInput(Question $question, array $instructors = []): string
    {
        $id = (int) $question->getId();
        $posted = $_POST['answer'][$id] ?? $_POST['answer'][(string) $id] ?? [];
        $postedScore = isset($posted['score']) ? (string) $posted['score'] : '';
        $postedText = $posted['text'] ?? '';
        $postedIds = is_array($postedText) ? array_map('strval', $postedText) : array_filter(explode(',', (string) $postedText));
        $required = $question->isRequired() ? ' required' : '';
        $html = '<fieldset class="ce-question mb-4">'
            .'<legend>'.$this->e($question->getPrompt()).($question->isRequired() ? ' *' : '').'</legend>';
        if ($question->getHelpText()) {
            $html .= '<p class="text-muted">'.$this->e($question->getHelpText()).'</p>';
        }

        if (Question::TYPE_INSTRUCTOR === $question->getType()) {
            $html .= '<select class="form-select" name="answer['.$id.'][text][]" multiple size="'.max(3, min(6, count($instructors) + 1)).'">';
            foreach ($instructors as $instructor) {
                $selected = in_array((string) $instructor['id'], $postedIds, true) ? ' selected' : '';
                $html .= '<option value="'.(int) $instructor['id'].'"'.$selected.'>'.$this->e($instructor['name']).'</option>';
            }
            $html .= '</select>';
        } elseif (Question::TYPE_TEXT === $question->getType()) {
            $html .= '<textarea class="form-control" name="answer['.$id.'][text]" rows="3"'.$required.'>'.$this->e(is_array($postedText) ? '' : (string) $postedText).'</textarea>';
        } elseif (Question::TYPE_YES_NO === $question->getType()) {
            $yes = $question->getScaleHighLabel() ?: $this->t('Yes');
            $no = $question->getScaleLowLabel() ?: $this->t('No');
            $html .= '<div class="form-check"><input class="form-check-input" type="radio" name="answer['.$id.'][score]" value="1" id="q'.$id.'y"'.('1' === $postedScore ? ' checked' : '').$required.'>'
                .'<label class="form-check-label" for="q'.$id.'y">'.$this->e($yes).'</label></div>'
                .'<div class="form-check"><input class="form-check-input" type="radio" name="answer['.$id.'][score]" value="0" id="q'.$id.'n"'.('0' === $postedScore ? ' checked' : '').'>'
                .'<label class="form-check-label" for="q'.$id.'n">'.$this->e($no).'</label></div>';
        } else {
            $html .= '<div class="ce-scale">';
            if ($question->getScaleLowLabel()) {
                $html .= '<span>'.$this->e($question->getScaleLowLabel()).'</span>';
            }
            for ($score = $question->getScaleMin(); $score <= $question->getScaleMax(); ++$score) {
                $checked = (string) $score === $postedScore ? ' checked' : '';
                $html .= '<label class="ce-scale-option"><input type="radio" name="answer['.$id.'][score]" value="'.$score.'"'.$checked.$required.'> '.$score.'</label>';
            }
            if ($question->getScaleHighLabel()) {
                $html .= '<span>'.$this->e($question->getScaleHighLabel()).'</span>';
            }
            $html .= '</div>';
        }

        return $html.'</fieldset>';
    }

    public function searchPanel(string $fields, bool $open): string
    {
        $icon = $open ? 'mdi-chevron-up' : 'mdi-chevron-down';

        return '<div class="ce-search">'
            .'<button type="button" class="p-button p-component p-button-secondary" data-ce-search-toggle aria-expanded="'.($open ? 'true' : 'false').'" aria-controls="ce-search-panel">'
            .'<span class="p-button-icon mdi '.$icon.'"></span>'
            .'<span class="p-button-label">'.$this->e($this->t('AdvancedSearch')).'</span></button>'
            .'<div class="ce-search-panel" id="ce-search-panel"'.($open ? '' : ' hidden').'>'
            .'<div class="ce-search-grid">'.$fields.'</div>'
            .'<button class="p-button p-component" type="submit">'
            .'<span class="p-button-icon mdi mdi-magnify"></span>'
            .'<span class="p-button-label">'.$this->e($this->t('Search')).'</span></button>'
            .'</div></div>';
    }

    public function reportFilters(array $filters, string $action, bool $lockCourse, EvaluationManager $manager): string
    {
        $html = '<form method="get" action="'.$this->e($action).'" class="ce-report-filters">';
        if (isset($filters['action'])) {
            $html .= '<input type="hidden" name="action" value="'.$this->e($filters['action']).'">';
        }
        if (!empty($filters['tab'])) {
            $html .= '<input type="hidden" name="tab" value="'.$this->e((string) $filters['tab']).'">';
        }
        $fields = '';
        if ($lockCourse) {
            $html .= '<input type="hidden" name="cid" value="'.(int) api_get_course_int_id().'">'
                .'<input type="hidden" name="sid" value="'.(int) api_get_session_id().'">'
                .'<input type="hidden" name="gid" value="'.(function_exists('api_get_group_id') ? (int) api_get_group_id() : 0).'">'
                .'<input type="hidden" name="cidReq" value="'.$this->e(api_get_course_id()).'">'
                .'<input type="hidden" name="id_session" value="'.(int) api_get_session_id().'">'
                .'<input type="hidden" name="course_id" value="'.(int) ($filters['course_id'] ?? 0).'">';
            if (empty($filters['filter_session'])) {
                $scope = (string) ($filters['scope'] ?? '');
                $fields .= $this->filterField(
                    $this->t('ReportType'),
                    '<select class="form-select" name="scope">'
                    .'<option value="">'.$this->e($this->t('All')).'</option>'
                    .'<option value="self_paced"'.('self_paced' === $scope ? ' selected' : '').'>'.$this->e($this->t('SelfPaced')).'</option>'
                    .'<option value="session"'.('session' === $scope ? ' selected' : '').'>'.$this->e($this->t('Sessions')).'</option>'
                    .'</select>'
                );
                $coachOptions = '<option value="">'.$this->e($this->t('All')).'</option>';
                foreach ($manager->courseCoaches((int) ($filters['course_id'] ?? 0)) as $coach) {
                    $selected = (int) ($filters['coach_id'] ?? 0) === (int) $coach['id'] ? ' selected' : '';
                    $coachOptions .= '<option value="'.(int) $coach['id'].'"'.$selected.'>'.$this->e($coach['name']).'</option>';
                }
                $fields .= $this->filterField($this->t('Coach'), '<select class="form-select" name="coach_id">'.$coachOptions.'</select>');
            }
        } else {
            $choices = $manager->reportChoices();
            $fields .= $this->filterSelect($this->t('Course'), 'course_id', $choices['courses'], (int) ($filters['course_id'] ?? 0), false);
            $fields .= $this->filterSelect($this->t('Session'), 'session_id', $choices['sessions'], (int) ($filters['session_id'] ?? 0), !empty($filters['filter_session']));
            $fields .= $this->filterSelect($this->t('Instructor'), 'instructor_id', $choices['instructors'], (int) ($filters['instructor_id'] ?? 0), false);
        }
        $fields .= $this->filterField($this->t('From'), '<input class="form-control" type="date" name="from" value="'.$this->e($filters['from'] ?? '').'">');
        $fields .= $this->filterField($this->t('To'), '<input class="form-control" type="date" name="to" value="'.$this->e($filters['to'] ?? '').'">');
        $categoryOptions = '<option value="">'.$this->e($this->t('All')).'</option>';
        foreach ([
            Question::CATEGORY_INSTRUCTOR,
            Question::CATEGORY_CONTENT,
            Question::CATEGORY_DELIVERY,
            Question::CATEGORY_ORGANIZATION,
            Question::CATEGORY_IMPROVEMENT,
        ] as $category) {
            $selected = ($filters['category'] ?? '') === $category ? ' selected' : '';
            $categoryOptions .= '<option value="'.$this->e($category).'"'.$selected.'>'.$this->e($this->categoryLabel($category)).'</option>';
        }
        $fields .= $this->filterField($this->t('Category'), '<select class="form-select" name="category">'.$categoryOptions.'</select>');
        $open = !empty($filters['scope']) || !empty($filters['coach_id']) || !empty($filters['from']) || !empty($filters['to']) || !empty($filters['category'])
            || !empty($filters['course_id']) || !empty($filters['session_id']) || !empty($filters['instructor_id']);

        return $html.$this->searchPanel($fields, $open).'</form>';
    }

    /**
     * @param list<array{id: int, label: string}> $options
     */
    private function filterSelect(string $label, string $name, array $options, int $selectedId, bool $allowZero): string
    {
        $html = '<option value="">'.$this->e($this->t('All')).'</option>';
        foreach ($options as $option) {
            $matches = $selectedId === $option['id'] && ($allowZero || $option['id'] > 0);
            $html .= '<option value="'.(int) $option['id'].'"'.($matches ? ' selected' : '').'>'.$this->e($option['label']).'</option>';
        }

        return $this->filterField($label, '<select class="form-select" name="'.$this->e($name).'">'.$html.'</select>');
    }

    public function filterField(string $label, string $control): string
    {
        return '<div class="ce-filter-field"><label class="form-label">'.$this->e($label).'</label>'.$control.'</div>';
    }

    public function reportMenu(array $filters): string
    {
        [$tabs, $tab] = $this->reportTabState($filters);

        return $this->reportNav($tabs, $tab, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function reportTabState(array $filters): array
    {
        $courseReport = empty($filters['filter_session']);
        $tabs = [
            'results' => $this->t($courseReport ? 'CourseResults' : 'BySection'),
            'mentors' => $this->t('MentorPerformance'),
            'sessions' => $this->t('Sessions'),
            'responses' => $this->t('ViewResponses'),
        ];
        if (!$courseReport) {
            unset($tabs['mentors'], $tabs['sessions']);
        }
        $tab = (string) ($filters['tab'] ?? 'results');
        if (!isset($tabs[$tab])) {
            $tab = 'results';
        }

        return [$tabs, $tab];
    }

    public function reportTables(EvaluationManager $manager, array $filters, bool $revealNames): string
    {
        $courseReport = empty($filters['filter_session']);
        [, $tab] = $this->reportTabState($filters);
        $html = '';
        if ('mentors' === $tab) {
            $mentorRows = [];
            foreach ($manager->mentorPerformance($filters) as $row) {
                $mentorRows[] = [
                    $this->e($manager->userName((int) $row['mentor_id'])),
                    $this->e($this->formatAverage($row['average_score'])),
                    (string) (int) $row['score_count'],
                    (string) (int) $row['response_count'],
                    (string) (int) $row['named_count'],
                ];
            }

            return $html.$this->dataTable(
                [$this->t('Coach'), $this->t('Average'), $this->t('Scores'), $this->t('Responses'), $this->t('Named')],
                $mentorRows,
                $this->t('NoResponses'),
                2
            );
        }

        if ('sessions' === $tab) {
            $sessionRows = [];
            foreach ($manager->sessionAggregates($filters) as $row) {
                $sessionId = (int) $row['session_id'];
                $label = $sessionId > 0 ? $manager->sessionTitle($sessionId) : $this->t('SelfPaced');
                $link = $sessionId > 0
                    ? '<a href="'.$this->e($this->sessionReportUrl((int) ($filters['course_id'] ?? 0), $sessionId)).'">'.$this->e($label).'</a>'
                    : $this->e($label);
                $coach = (int) $row['instructor_id'] > 0 ? $manager->userName((int) $row['instructor_id']) : '';
                $sessionRows[] = [
                    $link,
                    $this->e($coach),
                    $this->e($this->formatAverage($row['average_score'])),
                    (string) (int) $row['response_count'],
                ];
            }

            return $html.$this->dataTable(
                [$this->t('Session'), $this->t('Coach'), $this->t('Average'), $this->t('Responses')],
                $sessionRows,
                $this->t('NoResponses'),
                3
            );
        }

        if ('responses' === $tab) {
            $responseRows = [];
            foreach ($manager->reportSubmissions($filters) as $row) {
                $who = 1 === (int) $row['anonymous']
                    ? $this->t('Anonymous')
                    : $manager->userName((int) $row['user_id']);
                $url = $this->courseUrl('response', ['submission_id' => (int) $row['id']]);
                $responseRows[] = [
                    '<a href="'.$this->e($url).'">'.$this->e($who).'</a>',
                    $this->e($row['session_id'] > 0 ? $manager->sessionTitle((int) $row['session_id']) : $this->t('SelfPaced')),
                    $this->e(substr($row['submitted_at'], 0, 16)),
                    $this->e($this->t('Version').' '.(int) $row['version']),
                ];
            }

            return $html.$this->dataTable(
                [$this->t('Learner'), $this->t('Session'), $this->t('SubmittedStatus'), $this->t('Version')],
                $responseRows,
                $this->t('NoResponses'),
                3
            );
        }

        $questions = $manager->scoredQuestionReport($filters);
        $byCategory = [];
        foreach ($questions as $question) {
            $byCategory[(string) $question['category']][] = $question;
        }
        $html .= '<p><strong>'.$this->e($this->t('Responses')).':</strong> '.$manager->responseCount($filters).'</p>';
        $categories = $manager->categoryReport($filters);
        if (!$categories) {
            $html .= '<p class="text-muted">'.$this->e($this->t('NoResponses')).'</p>';
        }
        foreach ($categories as $category) {
            $key = (string) $category['category'];
            $html .= $this->categoryDetails($key, $category, $byCategory[$key] ?? [], $courseReport);
        }

        return $html;
    }

    /**
     * Icon toolbar used by the gradebook: the current item is a green square, the others are quiet icons.
     *
     * @param list<array{label: string, url: string, icon: string, active?: bool, primary?: bool}> $items
     * @param list<array{label: string, url: string, icon: string, active?: bool, primary?: bool}> $end
     */
    public function iconMenu(string $navClass, array $items, array $end = []): string
    {
        $html = '<div class="ce-menu '.$this->e($navClass).'" role="navigation"><div class="ce-menu-start">';
        foreach ($items as $item) {
            $html .= $this->menuButton($item);
        }
        $html .= '</div>';
        if ($end) {
            $html .= '<div class="ce-menu-end">';
            foreach ($end as $item) {
                $html .= $this->menuButton($item);
            }
            $html .= '</div>';
        }

        return $html.'</div>';
    }

    /**
     * @param array{label: string, url: string, icon: string, active?: bool, primary?: bool} $item
     */
    private function menuButton(array $item): string
    {
        $active = !empty($item['active']);
        $tone = ($active || !empty($item['primary'])) ? ' p-button-success' : ' p-button-secondary p-button-text';

        return '<a class="p-button p-component p-button-icon-only'.$tone.($active ? ' ce-menu-current' : '').'" href="'.$this->e($item['url']).'" title="'.$this->e($item['label']).'" aria-label="'.$this->e($item['label']).'"'.($active ? ' aria-current="page"' : '').'>'
            .'<span class="p-button-icon '.$this->e($item['icon']).'"></span></a>';
    }

    /**
     * @param array<string, string> $tabs
     * @param array<string, mixed> $filters
     */
    private function reportNav(array $tabs, string $active, array $filters): string
    {
        $icons = [
            'results' => 'mdi mdi-chart-box',
            'mentors' => 'mdi mdi-account',
            'sessions' => 'mdi mdi-google-classroom',
            'responses' => 'mdi mdi-eye',
        ];
        $items = [];
        foreach ($tabs as $id => $label) {
            $items[] = [
                'label' => $label,
                'url' => $this->reportTabUrl($id, $filters),
                'icon' => $icons[$id] ?? 'mdi mdi-chart-box',
                'active' => $id === $active,
            ];
        }

        return $this->iconMenu('ce-report-nav', $items);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function reportTabUrl(string $tab, array $filters): string
    {
        $params = [
            'action' => 'report',
            'tab' => $tab,
            'cid' => function_exists('api_get_course_int_id') ? (int) api_get_course_int_id() : (int) ($filters['course_id'] ?? 0),
            'sid' => function_exists('api_get_session_id') ? (int) api_get_session_id() : 0,
            'gid' => function_exists('api_get_group_id') ? (int) api_get_group_id() : 0,
            'cidReq' => function_exists('api_get_course_id') ? api_get_course_id() : '',
            'id_session' => function_exists('api_get_session_id') ? (int) api_get_session_id() : 0,
        ];
        foreach (['from', 'to', 'category', 'course_id', 'coach_id', 'scope'] as $key) {
            if (!empty($filters[$key])) {
                $params[$key] = $filters[$key];
            }
        }

        return 'start.php?'.http_build_query($params);
    }

    private function categoryDetails(string $category, array $summary, array $questions, bool $showLastAnswered): string
    {
        $html = '<details class="ce-category">'
            .'<summary>'
            .'<span>'.$this->e($this->categoryLabel($category)).'</span>'
            .'<span>'.$this->e($this->formatAverage($summary['average_score'] ?? null)).'</span>'
            .'<span>'.(int) ($summary['response_count'] ?? 0).' '.$this->e($this->t('Responses')).'</span>'
            .'</summary>';
        $rows = [];
        foreach ($questions as $question) {
            $row = [
                $this->e((string) $question['prompt']),
                $this->e($this->formatAverage($question['average_score'])),
                (string) (int) $question['score_count'],
            ];
            if ($showLastAnswered) {
                $row[] = $this->e(substr((string) $question['last_answered'], 0, 16));
            }
            $rows[] = $row;
        }
        $headers = [$this->t('Prompt'), $this->t('Average'), $this->t('Answers')];
        if ($showLastAnswered) {
            $headers[] = $this->t('LastAnswered');
        }

        return $html.$this->dataTable($headers, $rows, $this->t('NoResponses')).'</details>';
    }

    private function sessionReportUrl(int $courseId, int $sessionId): string
    {
        return 'start.php?'.http_build_query([
            'action' => 'report',
            'cid' => $courseId,
            'sid' => $sessionId,
            'gid' => function_exists('api_get_group_id') ? (int) api_get_group_id() : 0,
            'cidReq' => function_exists('api_get_course_id') ? api_get_course_id() : '',
            'id_session' => $sessionId,
        ]);
    }

    public function categoryCourseComparison(EvaluationManager $manager, array $filters): string
    {
        $grouped = [];
        foreach ($manager->categoryByCourse($filters) as $row) {
            $grouped[$row['category']][] = $row;
        }
        $html = '<h3>'.$this->e($this->t('CourseComparison')).'</h3>'
            .'<p class="text-muted">'.$this->e($this->t('CourseComparisonHelp')).'</p>';
        if (!$grouped) {
            return $html.'<p class="text-muted">'.$this->e($this->t('NoResponses')).'</p>';
        }
        $order = [
            Question::CATEGORY_INSTRUCTOR,
            Question::CATEGORY_CONTENT,
            Question::CATEGORY_DELIVERY,
            Question::CATEGORY_ORGANIZATION,
            Question::CATEGORY_IMPROVEMENT,
        ];
        foreach ($order as $category) {
            $courses = $grouped[$category] ?? [];
            if (!$courses) {
                continue;
            }
            $weight = 0;
            $total = 0.0;
            foreach ($courses as $course) {
                $weight += $course['score_count'];
                $total += $course['average_score'] * $course['score_count'];
            }
            $overall = $weight > 0 ? $total / $weight : null;
            usort($courses, static fn (array $left, array $right): int => $left['average_score'] <=> $right['average_score']);
            $rows = [];
            foreach ($courses as $course) {
                $difference = null === $overall ? 0.0 : $course['average_score'] - $overall;
                if (abs($difference) < 0.005) {
                    $mark = $this->t('AtAverage');
                    $class = 'ce-average';
                } elseif ($difference > 0) {
                    $mark = $this->t('AboveAverage');
                    $class = 'ce-above';
                } else {
                    $mark = $this->t('BelowAverage');
                    $class = 'ce-below';
                }
                $signed = ($difference > 0 ? '+' : '').number_format($difference, 2);
                $rows[] = [
                    $this->e($manager->courseTitle((int) $course['course_id'])),
                    $this->e($this->formatAverage($course['average_score'])),
                    $this->e($signed),
                    (string) (int) $course['response_count'],
                    '<span class="'.$class.'">'.$this->e($mark).'</span>',
                ];
            }
            $html .= '<h3>'.$this->e($this->categoryLabel($category)).' · '.$this->e($this->t('OverallAverage')).' '.$this->e($this->formatAverage($overall)).'</h3>'
                .$this->dataTable(
                    [$this->t('Course'), $this->t('Average'), $this->t('Difference'), $this->t('Responses'), $this->t('CourseComparison')],
                    $rows,
                    $this->t('NoResponses'),
                    2
                );
        }

        return $html;
    }

    public function formatAverage(mixed $value): string
    {
        if (null === $value || '' === $value) {
            return '—';
        }

        return number_format((float) $value, 2);
    }
}
