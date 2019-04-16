<?php
	include_once "./Modules/TestQuestionPool/classes/class.ilQuestionsPlugin.php";
	
	/**
	* assMusicQuestion plugin
	*
	* @author Christoph Jobst <christoph.jobst@llz.uni-halle.de>
	* @version $Id$
	* * @ingroup ModulesTestQuestionPool
	*
	*/
	class ilassMusicQuestionPlugin extends ilQuestionsPlugin
	{
		final function getPluginName()
		{
			return "assMusicQuestion";
		}
		
		final function getQuestionType()
		{
			return "assMusicQuestion";
		}
		
		final function getQuestionTypeTranslation()
		{
			return $this->txt('questionType');
		}
	}
?>
