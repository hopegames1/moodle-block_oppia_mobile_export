<?php

class mobile_activity_feedback extends mobile_activity {
	
	private $supported_types = array('multichoicerated', 'textarea', 'multichoice','numeric','textfield');
	private $courseversion;
	private $summary;
	private $shortname;
	private $content = "";
	private $feedback_image = null;
	private $is_valid = true; //i.e. doesn't only contain essay or random questions.
	private $no_questions = 0; // total no of valid questions
	private $server_connection;
	
	function init($server_connection, $shortname, $summary, $courseversion){
		$this->shortname = strip_tags($shortname);
		$this->summary = $summary;
		$this->courseversion = $courseversion;
		$this->server_connection = $server_connection;
	}
	
	
	function preprocess(){
		global $DB,$CFG,$USER;
		$cm = get_coursemodule_from_id('feedback', $this->id);
		$context = context_module::instance($cm->id);
		$feedback = $DB->get_record('feedback', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$select = 'feedback = ?';
		$params = array($feedback->id);
		$feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');
		
		$count_omitted = 0;
		foreach($feedbackitems as $fi){
			if(in_array($fi->typ,$this->supported_types)){
				$this->no_questions++;
			} else {
				$count_omitted++;
			}
		}
		if($count_omitted == count($feedbackitems)){
			$this->is_valid = false;
		}
	}
	function process(){
		global $DB,$CFG,$USER;
		$cm = get_coursemodule_from_id('feedback', $this->id);
		$context = context_module::instance($cm->id);
		$feedback = $DB->get_record('feedback', array('id'=>$cm->instance), '*', MUST_EXIST);
		
		$select = 'feedback = ?';
		$params = array($feedback->id);
		$feedbackitems = $DB->get_records_select('feedback_item', $select, $params, 'position');
		
		
		$mQH = new QuizHelper();
		$mQH->init($this->server_connection);
		
		$this->md5 = md5(serialize($feedbackitems)).$this->id;
		
		// find if this quiz already exists
		$resp = $mQH->exec('quizprops/digest/'.$this->md5, array(),'get');
		if(!isset($resp->quizzes)){
			echo get_string('error_connection','block_oppia_mobile_export');
			die;
		}
		
		$filename = extractImageFile($feedback->intro,
				'mod_feedback',
				'intro',
				'0',
				$context->id,
				$this->courseroot,
				$cm->id);
			
		if($filename){
			$this->feedback_image = "/images/".resizeImage($this->courseroot."/".$filename,
					$this->courseroot."/images/".$cm->id,
					$CFG->block_oppia_mobile_export_thumb_width,
					$CFG->block_oppia_mobile_export_thumb_height);
			//delete original image
			unlink($this->courseroot."/".$filename) or die(get_string('error_file_delete','block_oppia_mobile_export'));
		}
		
		
		if(count($resp->quizzes) > 0){
			$quiz_id = $resp->quizzes[0]->quiz_id;
			$quiz = $mQH->exec('quiz/'.$quiz_id, array(),'get');
			$this->content = json_encode($quiz);
			return;
		}
		
		$props = array();
		$props[0] = array('name' => "digest", 'value' => $this->md5);
		$props[1] = array('name' => "courseversion", 'value' => $this->courseversion);
		
		$nameJSON = extractLangs($cm->name,true);
		$descJSON = extractLangs($this->summary,true);
		
		//create the quiz
		$post = array('title' => $nameJSON,
				'description' => $descJSON,
				'questions' => array(),
				'props' => $props);
		$resp = $mQH->exec('quiz', $post);
		$quiz_uri = $resp->resource_uri;
		$quiz_id = $resp->id;
		
		$i = 1;
		foreach($feedbackitems as $fi){

			if(!in_array($fi->typ,$this->supported_types)){
				continue;
			}
			
			if($fi->required){
				$value = "true";
			} else {
				$value = "false";
			}
			$props = array();
			$props[0] = array('name' => "required", 'value' => $value);

			$qtitle = extractLangs($fi->name, true);
			
			// create the question
			if(strpos($fi->presentation, 'r') === 0 && ($fi->typ == "multichoice" || $fi->typ == "multichoicerated")){
				$post = array('title' => $qtitle,
						'type' => "multichoice",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			if(strpos($fi->presentation, 'c') === 0 && $fi->typ == "multichoice"){
				$post = array('title' => $qtitle,
						'type' => "multiselect",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			if($fi->typ == "textarea"){
				$post = array('title' => $qtitle,
						'type' => "essay",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			if($fi->typ == "numeric"){
				$post = array('title' => $qtitle,
						'type' => "numerical",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			if($fi->typ == "textfield"){
				$post = array('title' => $qtitle,
						'type' => "shortanswer",
						'responses' => array(),
						'props' => $props);
				$resp = $mQH->exec('question', $post);
				$question_uri = $resp->resource_uri;
			}
			
			// add the response options
			if($fi->typ == "multichoice"){
				$j = 1;
				$presentation = preg_replace("(r[>]+)",'',$fi->presentation);	
				$presentation = preg_replace("(c[>]+)",'',$presentation);
				$response_options = explode("|",$presentation);
				foreach($response_options as $ro){
					$responseopt = extractLangs($ro,true);
					$post = array('question' => $question_uri,
							'order' => $j,
							'title' => $responseopt,
							'score' => 0,
							'props' => array());
					$resp = $mQH->exec('response', $post);
					$j++;
				}
				
			}

			if($fi->typ == "multichoicerated"){
				$j = 1;
				$presentation = preg_replace("(r[>>]+)",'',$fi->presentation);				
				$response_options = explode("|",$presentation);
				foreach($response_options as $ro){
					$new_ro = preg_replace("([0-9]+[#]+)",'',$ro);
					$responseopt = extractLangs($new_ro,true);
					$post = array('question' => $question_uri,
							'order' => $j,
							'title' => $responseopt,
							'score' => 0,
							'props' => array());
					$resp = $mQH->exec('response', $post);
					$j++;
				}
			}
			
			// add question to quiz
			$post = array('quiz' => $quiz_uri,
					'question' => $question_uri,
					'order' => $i);
			$resp = $mQH->exec('quizquestion', $post);
			
			$i++;
		}
		
		// get the final quiz object
		$feedback = $mQH->exec('quiz/'.$quiz_id, array(),'get');
		$this->content = json_encode($feedback);
	}
	
	
	function export2print(){
		global $DB,$CFG,$USER,$QUIZ_CACHE;
		$cm = get_coursemodule_from_id('feedback', $this->id);
	}
	
	function getXML($mod,$counter,$activity=true,&$node,&$xmlDoc){
		global $DEFAULT_LANG;
		$act = $xmlDoc->createElement("activity");
		$act->appendChild($xmlDoc->createAttribute("type"))->appendChild($xmlDoc->createTextNode($mod->modname));
		$act->appendChild($xmlDoc->createAttribute("order"))->appendChild($xmlDoc->createTextNode($counter));
		$act->appendChild($xmlDoc->createAttribute("digest"))->appendChild($xmlDoc->createTextNode($this->md5));
		
		$title = extractLangs($mod->name);
		if(is_array($title) && count($title)>0){
			foreach($title as $l=>$t){
				$temp = $xmlDoc->createElement("title");
				$temp->appendChild($xmlDoc->createTextNode(strip_tags($t)));
				$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($l));
				$act->appendChild($temp);
			}
		} else {
			$temp = $xmlDoc->createElement("title");
			$temp->appendChild($xmlDoc->createTextNode(strip_tags($mod->name)));
			$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode($DEFAULT_LANG));
			$act->appendChild($temp);
		}
		
		$temp = $xmlDoc->createElement("content");
		$temp->appendChild($xmlDoc->createTextNode($this->content));
		$temp->appendChild($xmlDoc->createAttribute("lang"))->appendChild($xmlDoc->createTextNode("en"));
		$act->appendChild($temp);
		
		if($this->feedback_image){
			$temp = $xmlDoc->createElement("image");
			$temp->appendChild($xmlDoc->createAttribute("filename"))->appendChild($xmlDoc->createTextNode($this->feedback_image));
			$act->appendChild($temp);
		}
		$node->appendChild($act);
	}
	
	function get_is_valid(){
		return $this->is_valid;
	}
}

?>