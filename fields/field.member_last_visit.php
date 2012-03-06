<?php


	Class fieldMember_Last_Visit extends Field {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Last Visit');
			$this->_showassociation = false;
		}

		public function isSortable(){
			return true;
		}

		public function mustBeUnique(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `value` (`value`),
				  UNIQUE KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', null, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement($wrapper, $data, $encode = false) {
			if(isset($data['value']) && !is_null($data['value'])) {

				// Get date
				if(is_array($data['value'])) {
					$date = current($data['value']);
				}
				else {
					$date = $data['value'];
				}

				// Append date
				$wrapper->appendChild(General::createXMLDateObject($date, $this->get('element_name')));
			}
		}

		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id = null) {
			$value = null;

			if(isset($data['value'])) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($data['value']));
			}

			return parent::prepareTableValue(array('value' => $value), $link, $entry_id = null);
		}

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$label = Widget::Label($this->get('label'));
			$name = $this->get('element_name');

			if (isset($data['value'])) {
				$label->appendChild(
					new XMLElement('i', DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($data['value'])))
				);
				$label->appendChild(
					Widget::Input("fields{$prefix}[{$name}]", $data['value'], 'hidden')
				);
			} else {
				$label->appendChild(
					new XMLElement('i', __('No visit yet.'))
				);
			}
			$wrapper->appendChild($label);
		}
	}
