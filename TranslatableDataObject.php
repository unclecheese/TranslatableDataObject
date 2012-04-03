<?php

/**
 * A simple decorator class that adds translatable fields to a given DataObject subclass.
 * Unlike the {@link Translatable} module, this class does not require a CMS interface
 * and therefore can be applied beyond SiteTree decendants.
 *
 * @todo: Does not support $has_one relations.
 *
 *
 * <b>Usage</b>:
 *
 * ---------- MyDataObject.php ----------
 * <code>
 * static $db = array (
 *	'Title' => 'Varchar',
 *  'Description' => 'Text'
 * );
 * </code>
 *
 *
 * ------------- _config.php ------------
 * <code>
 * TranslatableDataObject::set_locales(array(
 *	'en_GB',
 *	'fr_FR',
 *	'it_IT'
 * ));
 *
 * TranslatableDataObject::register('MyDataObject', array(
 * 	'Title',
 *  'Description'
 * ));
 * </code>
 *
 * Always run /dev/build after adding new locales.
 *
 *
 * ---------- MyDataObject::getCMSFields() -------------
 * <code>
 *  // Option 1: Add all translations for all fields to a given tab
 *	foreach($this->getTranslationFields() as $field) {
 *		$f->addFieldToTab("Root.Translations", $field);
 *	}
 *
 * // Option 2: Add all the translations for a given field name to a tab
 *	foreach($this->getTranslationFields("Description") as $field) {
 *		$f->addFieldToTab("Root.Descriptions", $field);
 *	}
 *
 *	foreach($this->getTranslationFields("Title") as $field) {
 *		$f->addFieldToTab("Root.Titles", $field);
 *	}
 *
 *
 * // Option 3: Add all the fields for a given translation to a tab
 *	foreach($this->getTranslationFields(null, "fr_FR") as $field) {
 *		$f->addFieldToTab("Root.FR", $field);
 *	}
 *
 *	foreach($this->getTranslationFields(null, "en_GB") as $field) {
 *		$f->addFieldToTab("Root.EN", $field);
 *	}
 *
 * </code>
 *
 *
 * -------------------- Template ---------------------
 * Use the $T() function to get the translation for a given field.
 * <code>
 * <h2>$T(Title)</h2>
 * <p>$T(Description)</p>
 * </code>
 *
 *
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 * 
 */
class TranslatableDataObject extends Extension {

	/**
	 * @var array A list of all the locales that are registered as translations
	 */
	public static $locales = array();
	
	
	/**
	 * @var array Stores all the classes that are registered as translatable and their
	 * 			  associated $db arrays.
	 */
	protected static $translation_manifest = array ();
	
	
	/**
	 * Given a field name and a locale name, create a composite string to represent
	 * the field in the database.
	 *
	 * @param string $field The field name
	 * @param string $locale The locale name
	 * @return string
	 */
	public static function i18n_field($field, $locale = null) {
		if(!$locale) $locale = i18n::get_locale();
		return "{$field}__{$locale}";
	}
	
	
	/**
	 * Adds translatable locales
	 *
	 * @param mixed A list of locales, either as an array or argument list 
	 */
	public static function set_locales() {
		$args = func_get_args();
		if(empty($args)) {
			trigger_error("TranslatableDataObject::set_locales() called with no arguments.",E_USER_ERROR);
		}
		$locales = (isset($args[0]) && is_array($args[0])) ? $args[0] : $args;
		foreach($locales as $l) {
			if(!i18n::validate_locale($l)) {
				trigger_error("TranslatableDataObject::set_locales() -- Locale '$l' is not a valid locale.", E_USER_ERROR);
			}
			self::$locales[$l] = $l;
		}		
	}
	
	
	
	/**
	 * Given a translatable field name, pull out the locale and 
	 * return the raw field name.
	 *
	 * ex: "Description__fr_FR" -> "Description"
	 *
	 * @param string $field The name of the translated field
	 * @return string
	 */
	public function get_basename($field) {
		return reset(explode("__", $field));
	}


	/**
	 * Given a translatable field name, pull out the raw field name and 
	 * return the locale
	 *
	 * ex: "Description__fr_FR" -> "fr_FR"
	 *
	 * @param string $field The name of the translated field
	 * @return string
	 */
	public function get_locale($field) {
		return end(explode("__", $field));
	}


	
	/**
	 * Registers a class as translatable and adds translatable columns
	 * to a given list of fields
	 *
	 * @param string $class The class to register as translatable
	 * @param array $fields The list of fields to translate (must all exist in $db)
	 */
	public static function register($class, $fields = array()) {
		self::$translation_manifest[$class] = array();
		$SNG = singleton($class);
		foreach($fields as $f) {
			if($type = $SNG->db($f)) {
				foreach(self::$locales as $locale) {
					self::$translation_manifest[$class][self::i18n_field($f, $locale)] = $type;
				}
			}
		}
		Object::add_extension($class, 'TranslatableDataObject');	
	}
	

	
	/**
	 * Dynamically generate the $db array for a class given all of its
	 * registered translations
	 *
	 * @param string $class The class that is being decorated
	 */
	public function extraStatics($class) {
		return array (
			'db' => self::$translation_manifest[$class]		
		);
	}
	

	
	/**
	 * A template accessor used to get the translated version of a given field
	 * 
	 * ex: $T(Description) in the locale it_IT returns $yourClass->obj('Description__it_IT');
	 *
	 * @param string $field The field name to translate
	 * @return string
	 */
	public function T($field) {
		$i18nField = self::i18n_field($field);
		return $this->owner->hasField($i18nField) ? $this->owner->getField($i18nField) : $this->owner->getField($field);
	}
	

	/**
	 * Gets all of the {@link FormField} objects for all the translations
	 * and all fields. Can be used as a loop in getCMSFields() to generate
	 * an edit interface for all the translations.
	 *
	 * @example
	 * <code>
	 *	foreach($this->getTranslationFields() as $field) {
	 *		$f->addFieldToTab("Root.Translations", $field);
	 *	}
	 * </code>
	 *
	 * @param string $filter_name If provided, filter the translations by a given field name
	 * @param string $filter_locale If provided, filter the translations by a given locale
	 * @return array
	 */
	public function getTranslationFields($filter_name = null, $filter_locale = null) {
		$fields = array ();
		foreach(self::$translation_manifest[$this->owner->class] as $field => $type) {
			if($filter_name && $filter_name != self::get_basename($field)) continue;
			foreach(self::$locales as $locale) {
				if($filter_locale && $filter_locale != $locale) continue;
				if($o = $this->owner->obj($field)) {
					$formField = $o->scaffoldFormField();
					$fields[] = $formField;				
				}
			}
		}
		return $fields;
	}
	
		
}