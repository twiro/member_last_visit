<?php

if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

Class fieldMember_Last_Visit extends Field
{
    /*-------------------------------------------------------------------------
        DEFINITION
    -------------------------------------------------------------------------*/

    /**
     *
     * constructor for the Field object
     * @param mixed $parent
     *
     * @since 1.0.0
     */

    public function __construct()
    {
        // call the parent constructor
        parent::__construct();
        // set the name of the field
        $this->_name = __('Member: Last Visit');
        // ???
        $this->_showassociation = false;
    }

    public function isSortable()
    {
        return true;
    }

    public function canFilter()
    {
        return true;
    }

    public function mustBeUnique()
    {
        return true;
    }

    public function canImport()
    {
        return false;
    }
    public function canPrePopulate()
    {
        return false;
    }


    /*-------------------------------------------------------------------------
        SETUP
    -------------------------------------------------------------------------*/

    /**
     * create table
     *
     * Creates the table needed for the extensions entries
     */

    public function createTable()
    {
        $tbl = "tbl_entries_data_" . $this->get('id');

        return Symphony::Database()->query("
            CREATE TABLE IF NOT EXISTS `$tbl` (
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
        SETTINGS
    -------------------------------------------------------------------------*/

    /**
     *
     * display settings panel
     *
     * @since version 1.0.0
     */

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        $div = new XMLElement('div', null, array('class' => 'compact'));
        $this->appendRequiredCheckbox($div);
        $this->appendShowColumnCheckbox($div);
        $wrapper->appendChild($div);
    }

    /**
     *
     * commit field settings
     *
     * @since version 2.0.0
     */

    public function commit()
    {
        if (!parent::commit()) return false;
        $id = $this->get('id');
        if ($id === false) return false;
        return FieldManager::saveSettings($id, $settings=null);
    }


    /*-------------------------------------------------------------------------
        DATA SOURCE OUTPUT
    -------------------------------------------------------------------------*/

    /**
     *
     * append formmatted element
     *
     * @since version 1.0.0
     */

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        if(isset($data['value']) && !is_null($data['value'])) {

            // Get date
            if(is_array($data['value'])) {
                $date = current($data['value']);
            } else {
                $date = $data['value'];
            }

            // Append date
            $wrapper->appendChild(General::createXMLDateObject($date, $this->get('element_name')));
        }
    }


    /*-------------------------------------------------------------------------
        UI
    -------------------------------------------------------------------------*/

    /**
     *
     * prepare table value
     *
     * @since version 1.0.0
     */

    public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
    {
        $value = null;

        if (isset($data['value'])) {
            $value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($data['value']));
        }

        return parent::prepareTableValue(array('value' => $value), $link, $entry_id = null);
    }

    /**
     *
     * display publish panel
     *
     * @since version 1.0.0
     */

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        $label = Widget::Label($this->get('label'));
        $name = $this->get('element_name');

        $datetime_formatted = DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($data['value']));

        // due to styling changes in symphony 2.6.5 we need to switch from <i> to <time>

        $symphony_version = Symphony::Configuration()->get('version', 'symphony');
        if (version_compare($symphony_version, '2.6.5', '<')) {
            $html_element = 'i';
        } else {
            $html_element = 'time';
        }

        if (isset($data['value'])) {
            $label->appendChild(
                new XMLElement($html_element, $datetime_formatted, array('class' => 'field-value-readonly'))
            );
            $label->appendChild(
                Widget::Input("fields{$prefix}[{$name}]", $data['value'], 'hidden')
            );
        } else {
            $label->appendChild(
                new XMLElement($html_element, __('No visit yet.'), array('class' => 'field-value-readonly'))
            );
        }

        $wrapper->appendChild($label);
    }
}
