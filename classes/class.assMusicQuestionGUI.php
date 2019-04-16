<?php

include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
require_once './Modules/TestQuestionPool/interfaces/interface.ilGuiQuestionScoringAdjustable.php';
require_once './Modules/TestQuestionPool/interfaces/interface.ilGuiAnswerScoringAdjustable.php';
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * The assMusicQuestionGUI class encapsulates the GUI representation
 * for Question-Type-Plugin.
 *
 * @author Christoph Jobst <christoph.jobst@llz.uni-halle.de>
 * @ingroup ModulesTestQuestionPool
 *
 * @ilctrl_iscalledby assMusicQuestionGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilQuestionEditGUI, ilTestExpressPageObjectGUI
 */
class assMusicQuestionGUI extends assQuestionGUI
{
    /**
     * @var ilassMusicQuestionPlugin    The plugin object
     */
    var $plugin = null;

    /**
     * Constructor
     *
     * @param integer $id The database id of a question object
     * @access public
     */
    public function __construct($id = -1)
    {
        parent::__construct();
        include_once "./Services/Component/classes/class.ilPlugin.php";
        $this->plugin = ilPlugin::getPluginObject(IL_COMP_MODULE, "TestQuestionPool", "qst", "assMusicQuestion");
        $this->plugin->includeClass("class.assMusicQuestion.php");
        $this->object = new assMusicQuestion();
        if ($id >= 0) {
            $this->object->loadFromDb($id);
        }
    }

    /**
     * Command: edit the question
     */
    public function editQuestion()
    {
        $this->initQuestionForm();
        $this->getQuestionTemplate();
        $this->tpl->setVariable("QUESTION_DATA", $this->form->getHTML());
    }

    /**
     * Save data to DB
     */
    function save()
    {
        parent::save();

        // question couldn't be saved
        $this->form->setValuesByPost();
        $this->getQuestionTemplate();
        $this->tpl->setVariable("QUESTION_DATA", $this->form->getHTML());
    }

    /**
     * Command: save and show page editor
     */
    public function saveEdit()
    {
        // assQuestionGUI::saveEdit()
        // - calls writePostData
        // - redirects after successful saving
        // - otherwise does nothing
        parent::saveEdit();

        // question couldn't be saved
        $this->form->setValuesByPost();
        $this->getQuestionTemplate();
        $this->tpl->setVariable("QUESTION_DATA", $this->form->getHTML());
    }

    /**
     * Creates an output of the edit form for the question
     *
     * @param	boolean		add a new booking to the form
     */
    private function initQuestionForm()
    {
        include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->outQuestionType());
        $form->setMultipart(FALSE);
        $form->setTableWidth("100%");
        $form->setId("assMusicQuestion");

        // title, author, description, question, working time (assessment mode)
        $this->addBasicQuestionFormProperties($form);

        // points
        $points = new ilNumberInputGUI($this->plugin->txt("points"), "points");
        $points->setSize(3);
        $points->setMinValue(0);
        $points->allowDecimals(1);
        $points->setRequired(true);
        $points->setValue($this->object->getPoints());
        $form->addItem($points);

        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/jquery.klavier.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/vexflow.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/notelex.js');
        $this->tpl->addCss($this->plugin->getDirectory() . '/css/musiccomponent.css');

        include_once("./Services/Form/classes/class.ilCustomInputGUI.php");
        $item = new ilCustomInputGUI("", "piano");
        $item->setInfo($this->plugin->txt('info_editor'));
        $template = $this->plugin->getTemplate('tpl.edit.html');

        if ($this->object->getKeysValue()) {
            $template->setVariable("selectedValues", $this->object->getKeysValue());
        }
        else {
            $template->setVariable("selectedValues", '[]');
        }
        $item->setHTML($template->get());

        $form->addItem($item);


        $this->populateTaxonomyFormSection($form);
        $this->addQuestionFormCommandButtons($form);
        $this->form = $form;
    }

    /**
     * Evaluates a posted edit form and writes the form data in the question object
     * @return integer A positive value, if one of the required fields wasn't set, else 0
     */
    function writePostData($always = false)
    {
        $this->initQuestionForm();
        if ($this->form->checkInput())
        {
            $error = '';

            // write the basic data
            $this->writeQuestionGenericPostData();

            $this->object->setPoints(str_replace( ",", ".", $_POST["points"] ));
            $this->object->setKeysValue($_POST["selectedValues"]);

            // save taxonomy assignment
            $this->saveTaxonomyAssignments();

            // indicator to save the question
            return 0;

        }
        else
        {
            // indicator to show the edit form with errors
            return 1;
        }
    }

    /**
     * check input fields
     */
    function checkInput()
    {
        if ((!$_POST["title"]) or (!$_POST["author"]) or (!$_POST["question"]) or (strlen($_POST["points"]) == 0) or ($_POST["points"] < 0)) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Get the HTML output of the question for a test
     *
     * @param integer $active_id The active user id
     * @param integer $pass The test pass
     * @param boolean $is_postponed Question is postponed
     * @param boolean $use_post_solutions Use post solutions
     * @param boolean $show_feedback Show a feedback
     * @return string
     */
    function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
    {
        // get the solution of the user for the active pass or from the last pass if allowed
        $user_solution = array();
        if ($active_id) {
            include_once "./Modules/Test/classes/class.ilObjTest.php";
            if (!ilObjTest::_getUsePreviousAnswers($active_id, true)) {
                if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
            }
            $user_solution =& $this->object->getSolutionValues($active_id, $pass);
            if (!is_array($user_solution)) {
                $user_solution = array();
            }
        }

        $template = $this->plugin->getTemplate("tpl.output.html");
        $output = $this->object->getQuestion();

        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/jquery.klavier.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/vexflow.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/notelex.js');
        $this->tpl->addCss($this->plugin->getDirectory() . '/css/musiccomponent.css');

        $template->setVariable("ID", uniqid(''));
        if ($user_solution[0]["value1"]) {
            $template->setVariable("selectedValues", $user_solution[0]["value1"]);
        } else {
            $template->setVariable("selectedValues", "[]");
        }

        $template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($output, TRUE));
        $questionoutput = $template->get();
        $pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
        return $pageoutput;
    }

    /**
     * Get the output for question preview
     * (called from ilObjQuestionPoolGUI)
     *
     * @param boolean    show only the question instead of embedding page (true/false)
     */
    function getPreview($show_question_only = false, $showInlineFeedback = false)
    {

        $template = $this->plugin->getTemplate("tpl.output_preview.html");

        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/jquery.klavier.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/vexflow.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/notelex.js');
        $this->tpl->addCss($this->plugin->getDirectory() . '/css/musiccomponent.css');

        $template->setVariable("ID", uniqid(''));
        $template->setVariable("selectedValues", $this->object->getKeysValue());

        $template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
        $template->setVariable("RESUME", "");

        $questionoutput = $template->get();
        if (!$show_question_only) {
            // get page object output
            $questionoutput = $this->getILIASPage($questionoutput);
        }

        return $questionoutput;
    }



    /**
     * Get the question solution output
     *
     * @param integer $active_id The active user id
     * @param integer $pass The test pass
     * @param boolean $graphicalOutput Show visual feedback for right/wrong answers
     * @param boolean $result_output Show the reached points for parts of the question
     * @param boolean $show_question_only Show the question without the ILIAS content around
     * @param boolean $show_feedback Show the question feedback
     * @param boolean $show_correct_solution Show the correct solution instead of the user solution
     * @param boolean $show_manual_scoring Show specific information for the manual scoring output
     * @param boolean $show_question_text
     * @return string The solution output of the question as HTML code
     */
    function getSolutionOutput(
        $active_id,
        $pass = NULL,
        $graphicalOutput = FALSE,
        $result_output = FALSE,
        $show_question_only = TRUE,
        $show_feedback = FALSE,
        $show_correct_solution = FALSE,
        $show_manual_scoring = FALSE,
        $show_question_text = TRUE
    )
    {
        $template = $this->plugin->getTemplate("tpl.solution.html");
        // get the solution of the user for the active pass or from the last pass if allowed

        $user_solution = array();
        if (($active_id > 0) && (!$show_correct_solution)) {
            // get the solutions of a user
            $user_solution =& $this->object->getSolutionValues($active_id, $pass);
            if (!is_array($user_solution)) {
                $user_solution = array();
            }
        }

        $output = $this->object->getQuestion();

        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/jquery.klavier.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/vexflow.min.js');
        $this->tpl->addJavaScript($this->plugin->getDirectory() . '/js/notelex.js');
        $this->tpl->addCss($this->plugin->getDirectory() . '/css/musiccomponent.css');


        if ($show_correct_solution) {
            $template->setVariable("ID", $this->object->getId().'CORRECT_SOLUTION');
        } else {
            $template->setVariable("ID", $this->object->getId());
        }

        $template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($output, TRUE));

        //GeoJSON
        // show last saved input
        $usrSolPoint = "";

        if ($user_solution[0]["value1"]) {
            // use previously created geojson from students value-table
            $template->setVariable("selectedValues", $user_solution[0]["value1"]);
        } else {
            if ($show_correct_solution) {
                $template->setVariable("selectedValues", $this->object->getKeysValue());
            } else {
                $template->setVariable("selectedValues", "");
            }
        }


        $solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", TRUE, TRUE, "Modules/TestQuestionPool");
        $questionoutput = $template->get();

        $feedback = ($show_feedback) ? $this->getGenericFeedbackOutput($active_id, $pass) : "";
        if (strlen($feedback)) {
            $solutiontemplate->setVariable("FEEDBACK", $this->object->prepareTextareaOutput($feedback, true));
        }

        $solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);

        $solutionoutput = $solutiontemplate->get();

        if (!$show_question_only) {
            // get page object output
            $solutionoutput = $this->getILIASPage($solutionoutput);
        }

        return $solutionoutput;
    }

    /**
     * Saves the feedback for the question
     *
     * @access public
     */
    function saveFeedback()
    {
        include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
        $this->object->saveFeedbackGeneric(0, $_POST["feedback_incomplete"]);
        $this->object->saveFeedbackGeneric(1, $_POST["feedback_complete"]);
        $this->object->cleanupMediaObjectUsage();
        parent::saveFeedback();
    }

    /**
     * Sets the ILIAS tabs for this question type
     * @access public
     */
    function setQuestionTabs()
    {
        global $rbacsystem, $ilTabs;

        $this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", $_GET["q_id"]);
        include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
        $q_type = $this->object->getQuestionType();

        if (strlen($q_type)) {
            $classname = $q_type . "GUI";
            $this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
            $this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
        }

        if ($_GET["q_id"]) {
            if ($rbacsystem->checkAccess('write', $_GET["ref_id"])) {
                // edit page
                $ilTabs->addTarget("edit_content",
                    $this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
                    array("edit", "insert", "exec_pg"),
                    "", "", $force_active);
            }

            // edit page
            $ilTabs->addTarget("preview",
                $this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "preview"),
                array("preview"),
                "ilAssQuestionPageGUI", "", $force_active);
        }

        $force_active = false;
        if ($rbacsystem->checkAccess('write', $_GET["ref_id"])) {
            $url = "";

            if ($classname) $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
            $commands = $_POST["cmd"];
            if (is_array($commands)) {
                foreach ($commands as $key => $value) {
                    if (preg_match("/^suggestrange_.*/", $key, $matches)) {
                        $force_active = true;
                    }
                }
            }
            // edit question properties
            $ilTabs->addTarget("edit_properties",
                $url,
                array(
                    "editQuestion", "save", "cancel", "addSuggestedSolution",
                    "cancelExplorer", "linkChilds", "removeSuggestedSolution",
                    "saveEdit", "suggestRange"
                ),
                $classname, "", $force_active);
        }
        // add tab for question feedback within common class assQuestionGUI
        $this->addTab_QuestionFeedback($ilTabs);
        // add tab for question hint within common class assQuestionGUI
        $this->addTab_QuestionHints($ilTabs);

        if ($_GET["q_id"]) {
            $ilTabs->addTarget("solution_hint",
                $this->ctrl->getLinkTargetByClass($classname, "suggestedsolution"),
                array("suggestedsolution", "saveSuggestedSolution", "outSolutionExplorer", "cancel",
                    "addSuggestedSolution", "cancelExplorer", "linkChilds", "removeSuggestedSolution"
                ),
                $classname,
                ""
            );
        }

        // Assessment of questions sub menu entry
        if ($_GET["q_id"]) {
            $ilTabs->addTarget("statistics",
                $this->ctrl->getLinkTargetByClass($classname, "assessment"),
                array("assessment"),
                $classname, "");
        }

        if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0)) {
            $ref_id = $_GET["calling_test"];
            if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];
            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
        } else {
            $ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
        }
    }

    /**
     * Returns the answer specific feedback for the question
     *
     * @param integer $active_id Active ID of the user
     * @param integer $pass Active pass
     * @return string HTML Code with the answer specific feedback
     * @access public
     */
    public function getSpecificFeedbackOutput($active_id, $pass)
    {
        return "";
    }
}

?>
