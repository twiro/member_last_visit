<?php

class extension_member_last_visit extends Extension
{
    /**
     *
     * Name of the extension field table
     * @var string
     *
     * @since version 2.0.0
     */

    const FIELD_TBL_NAME = 'tbl_fields_member_last_visit';

    /**
     *
     * Get subscribed delegates
     *
     * @since version 1.0.0
     */

    public function getSubscribedDelegates()
    {
        return array(
            array(
                'page'		=> '/frontend/',
                'delegate'	=> 'FrontendOutputPostGenerate',
                'callback'	=> 'logVisit'
            ),
            array(
                'page'		=> '/system/preferences/',
                'delegate'	=> 'AddCustomPreferenceFieldsets',
                'callback'	=> 'appendPreferences'
            ),
            array(
                'page'		=> '/system/preferences/',
                'delegate'	=> 'Save',
                'callback'	=> 'savePreferences'
            )
        );
    }

    /*-------------------------------------------------------------------------
        INSTALL / UPDATE / UNINSTALL
    -------------------------------------------------------------------------*/

    /**
     *
     * install the extension
     *
     * @since version 1.0.0
     */

    public function install()
    {
        return self::createFieldTable();
    }

    /**
     *
     * update
     *
     * @since version 2.0.0
     */

    public function update($previousVersion = false)
    {
        if (version_compare($previousVersion, '2', '<')) {
            self::updateFieldTable('2');
            self::populateFieldTable();
        }
        return true;
    }

    /**
     *
     * uninstall
     *
     * @since version 1.0.0
     */

    public function uninstall()
    {
        Symphony::Configuration()->remove('member_last_visit');
        Symphony::Configuration()->write();

        return self::deleteFieldTable();
    }

    /**
     *
     * create the field table
     *
     * @since version 2.0.0
     */

    public static function createFieldTable()
    {
        $tbl = self::FIELD_TBL_NAME;

        return Symphony::Database()->query("
            CREATE TABLE IF NOT EXISTS `$tbl` (
                `id`            int(11) unsigned NOT NULL auto_increment,
                `field_id`      int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `field_id` (`field_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");
    }

    /**
     *
     * update field table
     *
     * version 1.X of this extension included 4 unecessary columns in the field
     * table. this function drops all obsolete columns while updating the extension.
     *
     * @since version 2.0.0
     */

    public static function updateFieldTable($version)
    {
        $tbl = self::FIELD_TBL_NAME;

        if ($version === '2') {
            return Symphony::Database()->query("
                ALTER TABLE  `$tbl`
                DROP `allow_multiple_selection`,
                DROP `show_association`,
                DROP `related_field_id`,
                DROP `limit`;
            ");
        }
    }

    /**
     *
     * populate field table
     *
     * version 1.X of this extension didn't include a 'commit'-function, so the
     * extension's field table didn't get populated. this function repopulates
     * the table while updating the extension.
     *
     * @since version 2.0.0
     */

    public static function populateFieldTable()
    {
        // get all field-instances of the type 'member_last_visit' from the 'fields'-table
        $fields = FieldManager::fetch(null, null, 'ASC', 'sortorder', $type='member_last_visit');

        // pupulate the 'fields_member_last_visit'-table
        foreach ($fields as $key => $val) {
            FieldManager::saveSettings($key, $settings=null);
        }
    }

    /**
     *
     * delete the field table
     *
     * @since version 2.0.0
     */

    public static function deleteFieldTable()
    {
        $tbl = self::FIELD_TBL_NAME;

        return Symphony::Database()->query("
            DROP TABLE IF EXISTS `$tbl`
        ");
    }


    /*-------------------------------------------------------------------------
        PREFERENCES
    -------------------------------------------------------------------------*/

    /**
     *
     * append preferences
     * @param array $context
     *
     * @since version 1.0.0
     */

    public function appendPreferences($context)
    {
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
            $options[] = array($value, ($value == self::getInterval()), $value . ' minute');
        }

        $label->appendChild(Widget::Select('settings[member_last_visit][interval]', $options));
        $div->appendChild($label);

        $div->appendChild(
            new XMLElement('p', __('Interval determines how often a Member\'s visit is recorded.'), array('class' => 'help'))
        );

        $fieldset->appendChild($div);

        $context['wrapper']->appendChild($fieldset);
    }

    /**
     *
     * save preferences
     * @param array $context
     *
     * @since version 1.0.0
     */

    public function savePreferences(array &$context)
    {
        $settings = $context['settings'];

        Symphony::Configuration()->set('interval', $settings['member_last_visit']['interval'], 'member_last_visit');

        Symphony::Configuration()->write();
    }


    /*-------------------------------------------------------------------------
        LOG VISIT
    -------------------------------------------------------------------------*/

    /**
     *
     * log visit
     *
     * @since version 1.0.0
     */

    public function logVisit()
    {
        $driver = Symphony::ExtensionManager()->create('members');

        if(!$member_id = $driver->getMemberDriver()->getMemberID()) return false;

        $cookie = new Cookie(
            'member_last_visit', TWO_WEEKS, __SYM_COOKIE_PATH__, null, true
        );

        if ($last_visit_date = $cookie->get('last-visit')) {
            if ( ($last_visit_date + (self::getInterval() * 60)) > time()) {
                return false;
            } else {
                $cookie->set('last-visit', time());
            }
        } else {
            $cookie->set('last-visit', time());
        }

        $last_visit = FieldManager::fetch(null, $driver::getMembersSection(), 'ASC', 'sortorder', 'member_last_visit');

        $last_visit = current($last_visit);
        $status = Field::__OK__;
        $data = $last_visit->processRawFieldData(
            DateTimeObj::get('Y-m-d H:i:s', time()),
            $status
        );
        $data['entry_id'] = $member_id;
        Symphony::Database()->insert($data, 'tbl_entries_data_' . $last_visit->get('id'), true);
    }

    /**
     *
     * get interval
     *
     * @since version 1.0.0
     */

    public static function getInterval()
    {
        return Symphony::Configuration()->get('interval', 'member_last_visit');
    }

}
