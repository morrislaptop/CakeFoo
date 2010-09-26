<?php
/**
 * Wufoo Behavior class file.
 *
 * @filesource
 * @author Craig Morris
 * @link http://waww.com.au/wufoo-behavior
 * @version	0.1
 * @license	http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package app
 * @subpackage app.models.behaviors
 */

/**
 * Model behavior to support synchronisation of member records with a form in Wufoo
 *
 * Features:
 * - Will add members to campaign monitor after saving.
 *
 * Usage:
 *
 	var $actsAs = array(
		'Wufoo.Entry' => array(
			'form' => 'form-key-or-hash',
		)
	);
 *
 *
 * @package app
 * @subpackage app.models.behaviors
 */
class EntryBehavior extends ModelBehavior
{

	function setup(&$model, $settings) {
		if (!isset($this->settings[$model->alias])) {
			$this->settings[$model->alias] = array(
				'form' => '',
			);
		}
		$this->settings[$model->alias] = array_merge($this->settings[$model->alias], (array) $settings);
	}

	/**
	* Submits data to Wufoo!
	*
	* @param mixed $model
	* @param mixed $created
	*/
	function beforeSave(&$model, $created)
	{
		$settings = $this->settings[$model->alias];
		extract($settings);
		$fields = $this->fieldsMap($model);

		// Construct data array
		$data = array();
		foreach ($model->data[$model->alias] as $field => $value) 
		{
			// Determine a friendly name which might be used in the Wufoo field title
			$underscored = Inflector::underscore($field);
			$title = Inflector::humanize($underscored);
			$combo = $field . ' ' . $value;

			// First look for the field name
			if ( array_key_exists($field, $fields) ) {
				$data[$fields[$field]] = $value;
			}
			// Try the friendly name eg. receipt_no might be named Receipt No in Wufoo
			else if ( array_key_exists($title, $fields) ) {
				$data[$fields[$title]] = $value;
			}
			// Try the field and label combination
			else if ( array_key_exists($combo, $fields) ) {
				$data[$fields[$combo]] = $value;
			}
			
		}

		// Send to Wufoo!
		$saveEntry = $model->saveEntry($form, $data);
		if ( is_numeric($saveEntry) ) {
			return true;
		}

		// Uhoh - something went wrong - investigate the returning array and invalidate fields...
		$errors = $saveEntry['PostResponse']['FieldErrors']['FieldError'];
		$fields_flipped = array_flip($fields); // makes Title => WufooID to WufooID => Title

		// Case when there is only one error... convert it into an array which can be used below..
		if ( array_key_exists('ID', $errors) ) {
			$errors = array($errors);
		}

		foreach ($errors as $error)
		{
			if ( array_key_exists($error['ID'], $fields_flipped) )
			{
				$field = $fields_flipped[$error['ID']];
				$field = strtolower(Inflector::slug($field));
				$model->invalidate($field);
			}
		}
		return false;
	}

	/**
	 * Constructs a map of model fields to wufoo field ids
	 * Title => WufooID
	 *
	 * @todo Support for subfields
	 * @param <type> $model
	 */
	function fieldsMap(&$model) {
		$settings = $this->settings[$model->alias];
		extract($settings);

		$wufooFields = $model->findFields($form);

		$titles_ids = array();
		foreach ($wufooFields as $field)
		{
			if ( !array_key_exists('SubFields', $field) ) {
				$titles_ids[$field['Title']] = $field['ID'];
				continue;
			}

			// Convert to friendly foreach array if only one subfield in there
			$subFields = $field['SubFields']['Subfield'];
			if ( array_key_exists('ID', $subFields) ) {
				$subFields = array($subFields);
			}

			foreach ($subFields as $subField)
			{
				$titles_ids[$field['Title'] . ' ' . $subField['Label']] = $subField['ID'];
			}
		}

		return $titles_ids;
	}

}
?>