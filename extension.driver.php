<?php
	
	class extension_member_last_visit extends Extension {
		
		public static $entryManager = null;


		public function __construct() {
			extension_member_last_visit::$entryManager = new EntryManager(Symphony::Engine());
		}

		public function about() {
			return array(
				'name'			=> 'Member Last Visit',
				'version'		=> '1.0',
				'release-date'	=> '2012-03-06',
				'author'		=> array(
					'name'			=> 'Mark Lewis',
					'website'		=> 'http://casadelewis.com',
					'email'			=> 'mark@casadelewis.com'
				)
			);
		}
				
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendOutputPostGenerate',
					'callback'	=> '__logVisit'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> '__appendPreferences'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'Save',
					'callback'	=> '__savePreferences'
				)
			);
		}

		public function install(){
			try{
				Symphony::Database()->query("
					CREATE TABLE IF NOT EXISTS `tbl_fields_member_last_visit` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`field_id` int(11) unsigned NOT NULL,
						`allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
						`show_association` enum('yes','no') NOT NULL default 'yes',
						`related_field_id` VARCHAR(255) NOT NULL,
						`limit` int(4) unsigned NOT NULL default '20',
						PRIMARY KEY  (`id`),
						KEY `field_id` (`field_id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
				");
			}
			catch(Exception $e){
				return false;
			}

			return true;
		}

		public function __appendPreferences($context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Member Last Visit')));

			$div = new XMLElement('div');
			$label = new XMLElement('label', __('Visit Interval'));

			$intervals = array(1,5,15,30,45,60);

			$options = array(
				array(null, false, null)
			);

			foreach ($intervals as $value) {
				$options[] = array($value, ($value == extension_member_last_visit::getInterval()), $value . ' minute');
			}

			$label->appendChild(Widget::Select('settings[member_last_visit][interval]', $options));
			$div->appendChild($label);

			$div->appendChild(
				new XMLElement('p', __('Interval determines how often a Member\'s visit is recorded.'), array('class' => 'help'))
			);

			$fieldset->appendChild($div);

			$context['wrapper']->appendChild($fieldset);
		}

		public function uninstall(){
			Symphony::Configuration()->remove('member_last_visit');
			Administration::instance()->saveConfig();

			if(parent::uninstall() == true){
				Symphony::Database()->query("DROP TABLE `tbl_fields_member_last_visit`");
				return true;
			}

			return false;
		}

		public function __savePreferences(array &$context){
			$settings = $context['settings'];

			Symphony::Configuration()->set('interval', $settings['member_last_visit']['interval'], 'member_last_visit');

			Administration::instance()->saveConfig();
		}

		public function __logVisit() {
			if(!class_exists('Symphony')) return false;

			$cookie = new Cookie(
				'member_last_visit', TWO_WEEKS, __SYM_COOKIE_PATH__, null, true
			);

			if ($last_visit_date = $cookie->get('last-visit')) {
				if (strtotime('+' . extension_member_last_visit::getInterval() . ' minutes', $last_visit_date) > time()) {
					return false;
				} else {
					$cookie->set('last-visit', time());
				}
			} else {
				$cookie->set('last-visit', time());
			}

			$driver = Symphony::ExtensionManager()->create('members');

			$sectionManager = $driver::$entryManager->sectionManager;
			$membersSectionSchema = array();

			if(
				!is_null($driver::getMembersSection()) &&
				is_numeric($driver::getMembersSection())
			) {
				$memberSection = $sectionManager->fetch(
					$driver::getMembersSection()
				);

				if($memberSection instanceof Section) {
					$membersSectionSchema = $memberSection->fetchFieldsSchema();
				}
				else {
					Symphony::$Log->pushToLog(
						__("The Member's section, %d, saved in the configuration could not be found.", array($driver::getMembersSection())),
						E_ERROR, true
					);
				}
			}

			foreach($membersSectionSchema as $field) {
				if ($field['type'] == 'member_last_visit') {
					$last_visit = extension_member_last_visit::$entryManager->fieldManager->fetch($field['id']);
				}
			}
			$member_id = $driver->getMemberDriver()->getMemberID();

			$status = Field::__OK__;
			$data = $last_visit->processRawFieldData(
				DateTimeObj::get('Y-m-d H:i:s', time()), 
				$status
			);

			Symphony::Database()->update($data, 'tbl_entries_data_' . $last_visit->get('id'), ' `entry_id` = ' . $member_id);
		}

		public static function getInterval() {
			return Symphony::Configuration()->get('interval', 'member_last_visit');
		}
	}
	
?>