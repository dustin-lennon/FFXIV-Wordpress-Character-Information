<?php
/**
 * Wordpress Easy-Form Builder Utility Class
 * 
 * A group of classes designed to make it really easy to create a form that saves data
 * to the database. Child forms allow settings to be saved to a table, or the general
 * WordPress meta settings table.
 * 
 * This code is very much in alpha phase, and should not be distributed with plugins 
 * other than by Dan Harrison.
 * 
 * @author Dan Harrison of WP Doctors (http://www.wpdoctors.co.uk)
 *
 * Version History
 * 
 * V0.01 				 - Initial version released.
 * V0.02 -  5th Jul 2011 - Added support for records form.
 * V0.03 - 30th Nov 2011 - Added support for radio buttons in a form.
 * V0.04 -  4th Apr 2012 - Added hidden field support.  
 */

// Required for setting up forms without writing any HTML.
include_once('utils_formbuilder.inc.php');


/**
 * Base easy-form builder. Does everything except the actual saving.
 * @author Dan Harrison of WP Doctors (http://www.wpdoctors.co.uk)  
 * 
 * 
 */
if (!class_exists('EasyForm')) { class EasyForm
{
	/**
	 * The list of parameters to create the form from.
	 * @var Array
	 */
	protected $paramList;

	/**
	 * The text on the main save button.
	 * @var String
	 */
	protected $buttonText;

	/**
	 * The object that represents the form being used and rendered.
	 * @var FormBuilder
	 */
	protected $formObj;

	/**
	 * The list of messages to show the user relating to manipulating the form details.
	 * @var String
	 */
	protected $messages;

	/**
	 * Function that can be called to filter the form data before it's saved.
	 * @var String
	 */
	public $filterBeforeSaveFunction;

	/**
	 * Function called after the data has been saved.
	 * @var String
	 */
	public $afterSaveFunction;

	/**
	 * Function that can be called to filter the default data before it's loaded into the form.
	 * @var String
	 */
	public $filterBeforeLoadDefaultsFunction;

	/**
	 * The ID given to the form.
	 * @var String
	 */
	private $formID;


	/**
	 * Default constructor that takes in initial parameters and setting prefix.
	 * @param Array $paramList The list of parameters to create the form from.
	 * @param String $formID The optional ID to give to the form. Ideal for when there's more than 1 form on a page. 
	 */
	public function __construct ($paramList, $formID = false)
	{
		$this->paramList = $paramList;
		$this->formID = $formID;

		// Default save text
		$this->buttonText = 'Save';

		// Convert array into form object for use.
		$this->convertToFormObject();
	}


	/**
	 * Convert the created details into a form object for use later.
	 */
	protected function convertToFormObject()
	{
		if (empty($this->paramList)) {
			$this->formObj = false;
			return false;
		}

		$this->formObj = new FormBuilder($this->formID);

		// Start processing...
		foreach ($this->paramList as $fieldName => $fieldDetails)
		{
			$elem = $this->createElementObject($fieldName, $fieldDetails);

			// Finally add the form element
			$this->formObj->addFormElement($elem);
		}
	}

	/**
	 * Convert an array of details into a form element.
	 * @param String $fieldName The name of the form element
	 * @param Array $fieldDetails The list of details for the form element.
	 */ 
	protected function createElementObject($fieldName, $fieldDetails) 
	{
		// Extract fields
		$label 		= $this->formObj->getArrayValue($fieldDetails, 'label');
		$type  		= $this->formObj->getArrayValue($fieldDetails, 'type');


		// Required 'true' or true are valid
		$required = $this->formObj->getArrayValue($fieldDetails, 'required');
		$required = ($required == 'true' || $required == '1');

		// Start creating form element if anything other than a break.
		$elem = false;
		if ($type != 'break') {
			$elem = new FormElement($fieldName, $label, $required);
		}

		// Handle specific types
		switch ($type) 
		{
			// Text Area
			case 'textarea':
				$rows = $this->formObj->getArrayValue($fieldDetails, 'rows') + 0;
				if ($rows == 0) {
					$rows = 5;
				}

				$elem->setTypeAsTextArea($rows, 70); 
				break;

			// Select/Dropdown
			case 'select':
				$options = false;
				if (isset($fieldDetails['data']) && is_array($fieldDetails['data'])) {
					$options = $fieldDetails['data'];
				}
				$elem->setTypeAsComboBox($options);
			break;

			// Radio Buttons
			case 'radio':
				$options = false;
				if (isset($fieldDetails['data']) && is_array($fieldDetails['data'])) {
					$options = $fieldDetails['data'];
				}
				$elem->setTypeAsRadioButtons($options);
			break;

			// Checkbox 
			case 'checkbox':
				$label = false;
				if (isset($fieldDetails['extralabel'])) {
					$label = $fieldDetails['extralabel'];
				}
				$elem->setTypeAsCheckbox($label);
			break;

			// Checkbox List
			case 'checkboxlist':
				$options = false;
				if (isset($fieldDetails['data']) && is_array($fieldDetails['data'])) {
					$options = $fieldDetails['data'];
				}
				$elem->setTypeAsCheckboxList($options);
			break;

			// Merged Fields - process each sub element
			case 'merge':
				$elementList = array();
				if (!empty($fieldDetails['merge'])) 
				{
					foreach ($fieldDetails['merge'] as $fieldName => $fieldDetails)
					{
						$elementList[] = $this->createElementObject($fieldName, $fieldDetails);
					}
				}
				$elem->setTypeAsMergedElements($elementList);
			break;

			// Custom HTML
			case 'custom':
				$elem->setTypeAsCustom($this->formObj->getArrayValue($fieldDetails, 'html'));
				break;

			// Hidden field
			case 'hidden':
				$elem->setTypeAsHidden();
				break;

			// Section break
			case 'break':
				$this->formObj->addBreak($fieldName, $this->formObj->getArrayValue($fieldDetails, 'html'));
				break;

			// Text box
			default: break;
		}

		// Add optional fields
		if ($type != 'break') 
		{
			// Element description
			if ($desc = $this->formObj->getArrayValue($fieldDetails, 'desc')) {
				$elem->description = $desc;
			}

			// Extra CSS
			if ($cssclass = $this->formObj->getArrayValue($fieldDetails, 'cssclass')) {
				$elem->cssclass = $cssclass;
			}

			// Add extra HTML if provided.
			$extraHTML = $this->formObj->getArrayValue($fieldDetails, 'extrahtml');
			if ($extraHTML) {
				$elem->afterFormElementHTML = $extraHTML;
			}

			// Validation rules
			if (isset($fieldDetails['validate']) && is_array($fieldDetails['validate'])) 
			{
				// Is it a custom function? If so, get the function name.
				if ($this->formObj->getArrayValue($fieldDetails['validate'], 'type') == 'function')	{
					$elem->validationFn = $this->formObj->getArrayValue($fieldDetails['validate'], 'fname');
					$elem->errorMessage = $this->formObj->getArrayValue($fieldDetails['validate'], 'error');
				}

				// Ah, no, it's not. Just a text-based validation.
				else {
					$elem->setValidationRules($fieldDetails['validate']);
				} 
			}
		} 

		return $elem;
	}

	/**
	 * Handle processing the form when it's posted, such as saving and handling errors.
	 */
	protected function processPost()
	{
		if (!$this->formObj) {
			return false;
		}

		if ($this->formObj->formSubmitted())
		{
			// Form is valid, so save data
			if ($this->formObj->formValid()) 
			{
				$originalFormValues = $this->formObj->getFormValues();

				// Add optional filter to tweak the form values before saving
				if ($this->filterBeforeSaveFunction && function_exists($this->filterBeforeSaveFunction)) {
					$formValues = call_user_func($this->filterBeforeSaveFunction, $originalFormValues, $this);
				} 

				// No custom filter
				else {
					$formValues = $originalFormValues;
				}

				// Now save the form values as normal.
				$this->handleSave($formValues);

				// Optional function once data has been saved
				if ($this->afterSaveFunction && function_exists($this->afterSaveFunction)) {
					call_user_func($this->afterSaveFunction, $formValues, $originalFormValues, $this);
				} 

			} 

			// Show the errors
			else {
				$this->messages = $this->showListOfErrors($this->formObj->getListOfErrors());
			}
		}
	}


	/**
	 * Method to handle the saving of the data.
	 */
	protected function handleSave() {}


	/**
	 * Shows a status or error message for the user using the standard Wordpress admin format.
	 * @param String $message The message to show.
	 * @param String $errormsg If true, show an error message.
	 * @return String The error message
	 */
	public function showMessage($message = "Settings saved.", $errormsg = false)
	{
		$html = false;
		if ($errormsg) {
			$html .= '<div id="message" class="error">';
		}
		else {
			$html .= '<div id="message" class="updated fade">';
		}

		$html .= "<p><strong>$message</strong></p></div>";

		return $html;
	}

	/**
	 * Show the array of errors as a formatted error message.
	 * @param Array $errors The list of errors.
	 */
	public function showListOfErrors($errors, $customMessage = false)
	{
		$html = false;

		if ($customMessage) {
			$message = $customMessage . '<br/><br/>';
		} else {
			$message = "Sorry, but unfortunately there were some errors. Please fix the errors and try again.<br><br>";
		}

		$message .= "<ul style=\"margin-left: 20px; list-style-type: square;\">";

		// Loop through all errors in the $error list
		foreach ($errors as $errormsg) {
			$message .= "<li>$errormsg</li>";
		}

		$message .= "</ul>";
		$html .= $this->showMessage($message, true);
		return $html;
	}


	/**
	 * Load the form object with the default values from an associative array mapping
	 * field name => field value.
	 * @param Array $valueList The list of values to load as defaults into the form.
	 */
	public function loadDefaults($valueList) 
	{
		if (!$valueList || !is_array($valueList)) {
			error_log('EasyForm:loadDefaults() - $valueList is empty');
			return;
		}

		// Filter the default values before they are loaded into the form.
		if ($this->filterBeforeLoadDefaultsFunction && function_exists($this->filterBeforeLoadDefaultsFunction)) {
			$valueList = call_user_func($this->filterBeforeLoadDefaultsFunction, $valueList);	
		}

		$this->formObj->setDefaultValues($valueList);
	}


	/**
	 * Set the label for the save button.
	 * @param String $string The new caption for the save button.
	 */
	public function setSaveButtonLabel($string) {
		$this->formObj->setSubmitLabel($string);
	}


	/**
	 * Render the form as HTML and return it.
	 * @return String The HTML for the form.
	 */
	public function getHTML()
	{
		if ($this->formObj) {
			$this->processPost();
			return $this->messages . $this->formObj->toString();
		}

		return false;
	}

	/**
	 * Echo the form and it's HTML.
	 */
	public function show() { 

		echo $this->getHTML();
	}
}}

?>