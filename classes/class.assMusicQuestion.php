<?php

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for assMusicQuestion Question
 *
 * @author Mohammed Helwani <mohammed.helwani@llz.uni-halle.de>
 * @version    $Id:  $
 * @ingroup ModulesTestQuestionPool
 */
class assMusicQuestion extends assQuestion
{
    var $keysValue = "";
    private $plugin = null;

    /**
     * assMusicQuestion constructor
     *
     * The constructor takes possible arguments an creates an instance of the assMusicQuestion object.
     *
     * @param string $title A title string to describe the question
     * @param string $comment A comment string to describe the question
     * @param string $author A string containing the name of the questions author
     * @param integer $owner A numerical ID to identify the owner/creator
     * @param string $question The question string of the single choice question
     * @access public
     * @see assQuestion:assQuestion()
     */
    function __construct(
        $title = "",
        $comment = "",
        $author = "",
        $owner = -1,
        $question = ""
    )
    {
        // needed for excel export
        $this->getPlugin()->loadLanguageModule();

        parent::__construct($title, $comment, $author, $owner, $question);
    }

    /**
     * @return object The plugin object
     */
    public function getPlugin()
    {
        if ($this->plugin == null) {
            include_once "./Services/Component/classes/class.ilPlugin.php";
            $this->plugin = ilPlugin::getPluginObject(IL_COMP_MODULE, "TestQuestionPool", "qst", "assMusicQuestion");
        }
        return $this->plugin;
    }

    /**
     * Returns true, if the question is complete
     *
     * @return boolean True, if the question is complete for use, otherwise false
     */
    public function isComplete()
    {
        // Please add here your own check for question completeness
        // The parent function will always return false
        if (($this->title) and ($this->author) and ($this->question) and ($this->getMaximumPoints() > 0)) {
            return true;
        } else {
            return false;
        }
    }

    // Getter/Setter

    /**
     * Returns the maximum points, a learner can reach answering the question
     * @access public
     * @see $points
     */
    function getMaximumPoints()
    {
        return $this->points;
    }

    function getKeysValue()
    {
        return $this->keysValue;
    }

    //END Getter/Setter

    function setKeysValue($keysValue)
    {
        $this->keysValue = $keysValue;
    }

    /**
     * Loads a question object from a database
     * This has to be done here (assQuestion does not load the basic data)!
     *
     * @param integer $question_id A unique key which defines the question in the database
     * @see assQuestion::loadFromDb()
     */
    public function loadFromDb($question_id)
    {
        global $ilDB;

        // load the basic question data
        $result = $ilDB->query("SELECT qpl_questions.* FROM qpl_questions WHERE question_id = "
            . $ilDB->quote($question_id, 'integer'));

        $data = $ilDB->fetchAssoc($result);
        $this->setId($question_id);
        $this->setTitle($data["title"]);
        $this->setComment($data["description"]);
        $this->setSuggestedSolution($data["solution_hint"]);
        $this->setOriginalId($data["original_id"]);
        $this->setObjId($data["obj_fi"]);
        $this->setAuthor($data["author"]);
        $this->setOwner($data["owner"]);
        $this->setPoints($data["points"]);


        include_once("./Services/RTE/classes/class.ilRTE.php");
        $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"], 1));
        $this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));

        $resultCheck = $ilDB->queryF("SELECT keys_value FROM il_qpl_qst_music_data WHERE question_fi = %s", array('integer'), array($question_id));
        if ($ilDB->numRows($resultCheck) == 1) {
            $data = $ilDB->fetchAssoc($resultCheck);

            $this->setKeysValue($data["keys_value"]);
        }

        try {
            $this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
        } catch (ilTestQuestionPoolException $e) {
        }

        // loads additional stuff like suggested solutions
        parent::loadFromDb($question_id);
    }

    /**
     * Duplicates an assMusicQuestion
     *
     * @access public
     */
    function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return;
        }
        // duplicate the question in database
        $this_id = $this->getId();

        if ((int)$testObjId > 0) {
            $thisObjId = $this->getObjId();
        }

        $clone = $this;
        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
        $original_id = assQuestion::_getOriginalId($this->id);
        $clone->id = -1;

        if ((int)$testObjId > 0) {
            $clone->setObjId($testObjId);
        }

        if ($title) {
            $clone->setTitle($title);
        }
        if ($author) {
            $clone->setAuthor($author);
        }
        if ($owner) {
            $clone->setOwner($owner);
        }
        if ($for_test) {
            $clone->saveToDb($original_id);
        } else {
            $clone->saveToDb();
        }

        // copy question page content
        $clone->copyPageOfQuestion($this_id);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($this_id);

        $clone->onDuplicate($thisObjId, $this_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }

    /**
     * Saves a assMusicQuestion object to a database
     *
     * @param $original_id
     * @access public
     */
    function saveToDb($original_id = "")
    {
        global $ilDB, $ilLog;
        $this->saveQuestionDataToDb($original_id);
        // delete old image

        $affectedRows = $ilDB->manipulateF("DELETE FROM il_qpl_qst_music_data WHERE question_fi = %s",
            array("integer"),
            array($this->getId())
        );
        $affectedRows = $ilDB->manipulateF("INSERT INTO il_qpl_qst_music_data (question_fi, keys_value) VALUES (%s, %s)",
            array("integer", "text"),
            array(
                $this->getId(),
                $this->keysValue
            )
        );

        parent::saveToDb();
    }

    /**
     * Copies an assMusicQuestion object
     *
     * @access public
     */
    function copyObject($target_questionpool_id, $title = "")
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return;
        }
        // duplicate the question in database
        $clone = $this;
        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
        $original_id = assQuestion::_getOriginalId($this->id);
        $clone->id = -1;
        $source_questionpool_id = $this->getObjId();
        $clone->setObjId($target_questionpool_id);
        if ($title) {
            $clone->setTitle($title);
        }
        $clone->saveToDb();

        // copy question page content
        $clone->copyPageOfQuestion($original_id);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($original_id);

        $clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }

    /**
     * Returns the points, a learner has reached answering the question
     * The points are calculated from the given answers.
     *
     * @param integer $active The Id of the active learner
     * @param integer $pass The Id of the test pass
     * @param boolean $returndetails (deprecated !!)
     * @return integer/array $points/$details (array $details is deprecated !!)
     * @access public
     * @see  assQuestion::calculateReachedPoints()
     */
    function calculateReachedPoints($active_id, $pass = NULL, $authorizedSolution = true, $returndetails = false)
    {
        if ($returndetails) {
            throw new ilTestException('return details not implemented for ' . __METHOD__);
        }

        global $ilDB;

        if (is_null($pass)) {
            $pass = $this->getSolutionMaxPass($active_id);
        }

        $query = "SELECT value1 FROM tst_solutions "
            . " WHERE active_fi = %s AND question_fi = %s AND pass = %s ";

        $result = $ilDB->queryF($query,
            array('integer', 'integer', 'integer'),
            array($active_id, $this->getId(), $pass)
        );
        $resultrow = $ilDB->fetchAssoc($result);

        $points = 0;
        //Apply patch to prevent doublegrading see Mantis 110%Testresult-Bugs
        if ($this->keysValue == $resultrow["value1"]) {
            $points = $this->getMaximumPoints();
        }

        return $points;
    }

    /**
     * Saves the learners input of the question to the database
     *
     * @param integer $test_id The database id of the test containing this question
     * @return boolean Indicates the save status (true if saved successful, false otherwise)
     * @access public
     * @see $answers
     */
    function saveWorkingData($active_id, $pass = NULL, $authorized = true)
    {
        global $ilDB;

        if (is_null($pass))
        {
            include_once "./Modules/Test/classes/class.ilObjTest.php";
            $pass = ilObjTest::_getPass($active_id);
        }

        $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            array(
                "integer", "integer", "integer"),
            array(
                $active_id,	$this->getId(),	$pass)
        );

        $entered_values = false;
        $keysValue = $_POST['selectedValues'];

        if (strlen($keysValue) > 0)
        {
            $entered_values = true;
            $this->saveCurrentSolution($active_id, $pass, $keysValue, null, $authorized);
        }

        if ($entered_values)
        {
            include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
            if (ilObjAssessmentFolder::_enabledAssessmentLogging())
            {
                $this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
            }
        }
        else
        {
            include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
            if (ilObjAssessmentFolder::_enabledAssessmentLogging())
            {
                $this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
            }
        }
        return true;
    }

    /**
     * Returns the question type of the question
     *
     * @return integer The question type of the question
     * @access public
     */
    function getQuestionType()
    {
        return "assMusicQuestion";
    }

    /**
     * Returns the name of the additional question data table in the database
     *
     * @return array The additional table name
     * @access public
     */
    function getAdditionalTableName()
    {
        return array('il_qpl_qst_music_data');
    }

    /**
     * Returns the name of the answer table in the database
     *
     * @return string The answer table name
     * @access public
     */
    function getAnswerTableName()
    {
        return "";
    }

    /**
     * Collects all text in the question which could contain media objects
     * which were created with the Rich Text Editor
     */
    function getRTETextWithMediaObjects()
    {
        $text = parent::getRTETextWithMediaObjects();
        return $text;
    }

    /**
     * Creates an Excel worksheet for the detailed cumulated results of this question
     *
     * @param object $worksheet Reference to the parent excel worksheet
     * @param object $startrow Startrow of the output in the excel worksheet
     * @param object $active_id Active id of the participant
     * @param object $pass Test pass
     *
     * @return object
     */
    public function setExportDetailsXLS($worksheet, $startrow, $active_id, $pass)
    {
        global $lng;
        parent::setExportDetailsXLS($worksheet, $startrow, $active_id, $pass);

        $solutions = $this->getSolutionValues($active_id, $pass);

        $i = 1;
        $worksheet->setCell($startrow + $i, 0, "Values");
        $worksheet->setBold($worksheet->getColumnCoord(0) . ($startrow + $i));

        if (strlen($solutions[0]["value1"]))
        {
            $worksheet->setCell($startrow + $i, 1, $solutions[0]["value1"]);
        }
        $i++;

        return $startrow + $i + 1;
    }

    /**
     * Creates a question from a QTI file
     *
     * Receives parameters from a QTI parser and creates a valid ILIAS question object
     *
     * @param object $item The QTI item object
     * @param integer $questionpool_id The id of the parent questionpool
     * @param integer $tst_id The id of the parent test if the question is part of a test
     * @param object $tst_object A reference to the parent test object
     * @param integer $question_counter A reference to a question counter to count the questions of an imported question pool
     * @param array $import_mapping An array containing references to included ILIAS objects
     * @access public
     */
    function fromXML(&$item, &$questionpool_id, &$tst_id, &$tst_object, &$question_counter, &$import_mapping)
    {
        $this->getPlugin()->includeClass("import/qti12/class.assMusicQuestionImport.php");
        $import = new assMusicQuestionImport($this);
        $import->fromXML($item, $questionpool_id, $tst_id, $tst_object, $question_counter, $import_mapping);
    }

    /**
     * Returns a QTI xml representation of the question and sets the internal
     * domxml variable with the DOM XML representation of the QTI xml representation
     *
     * @return string The QTI xml representation of the question
     * @access public
     */
    function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false)
    {
        $this->getPlugin()->includeClass("export/qti12/class.assMusicQuestionExport.php");
        $export = new assMusicQuestionExport($this);
        return $export->toXML($a_include_header, $a_include_binary, $a_shuffle, $test_output, $force_image_references);
    }

    /**
     * Reworks the allready saved working data if neccessary
     *
     * @abstract
     * @access protected
     * @param integer $active_id
     * @param integer $pass
     * @param boolean $obligationsAnswered
     * @param boolean $authorized
     */
    protected function reworkWorkingData($active_id, $pass, $obligationsAnswered, $authorized)
    {
        // nothing to rework!
    }
}

?>
